<?php
namespace app\modules\zakupki\services;

use app\modules\zakupki\models\StatFilter;
use app\services\LegacySp;
use Yii;

/**
 * Процедуры формы frmSt «Статистика» (аналог SetRS4Statistic из модуля формы).
 *
 * Параметры передаются ПО ПОРЯДКУ (знаки ?), но имена и типы сверены с текстами процедур:
 *   xp_frmStNewRS{What}
 *     (@S nvarchar(255), @f nvarchar(100), @Otv int, @Rgm int, @WhatFirm smallint,
 *      @SkladID int, @FZ275 int, @WorkProblEl tinyint)
 *
 * Особые случаи:
 *   xp_frmStNewRS7_0  (@S, @f, @Otv, @Rgm, @WhatFirm, @SkladID=0, @Bnd, @Razn, @Kurs)
 *   xp_frmStNewRS1_1  (@S, @f, @Otv, @Rgm, @WhatFirm, @DateS yyyymmdd, @DateE yyyymmdd,
 *                      @SkladID=0, @FZ275, @WorkProblEl)
 *
 * @SkladID = -500 — служебный режим «список пуст, установите фильтры» (так форма
 * открывается впервые и так работает вид b).
 *
 * f — мультивыбор фирм (frmFndFirm 7 → xp_frmStMultiSelectResult): «%» или ID через запятую.
 * filt1/filt2/filt3 в эти процедуры не передаются — они работают с временной таблицей счетов.
 *
 * ⚠ Otv (SkladManagers.Column(2)) и NeedPaintDolg (Column(3)) берутся из выбранного менеджера
 *   (StatContext::setManager), курс USD (KursUSD) — из xp_GetKursByVal 840.
 */
class StatService
{
    /** Служебные поля и подписи колонок вынесены в StatColumns (у них своя логика по видам). */
    public const HIDDEN = StatColumns::HIDDEN;
    public const META = StatColumns::META;

    /**
     * Футер списка: граница цены (PriceBound), разница (Razn), курс (KursUSD) и флажок «в долларах» (dl).
     *
     * Как в Access:
     *   - fnRepaintFormStat: PriceBound = GetAdr(60) * KursUSD, а для вида 7 — GetAdr(60);
     *   - ПолеСоСписком24_AfterUpdate: при переходе в вид 7 граница делится на курс (dl = True),
     *     при уходе из вида 7 — умножается (dl = False). Так граница всегда в валюте своего вида;
     *   - Razn виден только в виде 7, TypeSel (фильтр «Сырьё») — наоборот, только вне вида 7;
     *   - WarnTS («Выборка без учёта тех.запаса») скрыт только в виде 1.
     *
     * Метод правит фильтр (bnd/kurs/dl), поэтому вызывается до list().
     *
     * @return array{bnd:float,razn:float,kurs:float,dl:int,is7:bool,isB:bool,warn:bool}
     */
    public function footer(StatFilter $f): array
    {
        $is7  = (string)$f->vid === '7';
        $kurs = (float)$f->kurs > 0 ? (float)$f->kurs : StatContext::kursUsd();
        $dl   = (int)$f->dl === 1;

        // Вид сменили, а граница осталась в валюте прежнего вида — пересчитываем, как Access
        if ($is7 !== $dl) {
            $cur = (float)$f->bnd;
            if ($cur > 0 && $kurs > 0) {
                $f->bnd = $is7 ? $cur / $kurs : $cur * $kurs;
            }
            $f->dl = $is7 ? 1 : 0;
        }

        // Граница не задана — берём значение из настроек (GetAdr(60)) в валюте вида
        if ($f->bnd === null || $f->bnd === '' || (float)$f->bnd <= 0) {
            $adr = StatContext::priceBound();
            $f->bnd = $is7 ? $adr : $adr * $kurs;
        }

        $f->kurs = $kurs;
        $isB = (string)$f->vid === 'b';             // вид b: в Access PriceBound и KursUSD скрыты

        return [
            'bnd'  => (float)$f->bnd,
            'razn' => (float)$f->razn,
            'kurs' => $kurs,
            'dl'   => (int)$f->dl,
            'is7'  => $is7,
            'isB'  => $isB,
            'warn' => (string)$f->vid !== '1',      // WarnTS: «Выборка без учёта тех.запаса»
        ];
    }

    /**
     * Подсказки по колонкам — то, что в Access показывалось в Descr.Caption на GotFocus.
     *
     * Тексты дословно из обработчиков *_GotFocus подформы frmMinimum. Условия показа:
     *   - для вида «b» подсказок нет (в VBA все ветки под If ПолеСоСписком24 <> "b");
     *   - «Изготовитель» и «Недополучено» — только вид «1»;
     *   - «Корпус» и «Долг» — только когда включена раскраска долгов (FlgPaintDolg).
     *
     * @return array<string, string> поле => текст подсказки
     */
    public static function hints(string $vid, bool $paintDolg): array
    {
        if ($vid === 'b') return [];

        $h = [
            'Код'          => 'Код синего цвета — элемент можно забирать',
            'ГдеКупил'     => '«Покупать в» выделено голубым — есть задание на перенос элемента для склада, кирпичным — для участка входного контроля',
            'Количество'   => 'Кол-во выделено жёлтым — есть проблемные элементы',
            'Наименование' => 'Наименование выделено жирным — срок поставки жёсткий. Зелёное — срок поставки эл-та меньше текущей даты',
            'Номинал'      => 'Номинал выделен розовым — элемент, не рекомендуемый к поставке; коричневый — элемент на согласовании',
        ];

        if ($vid === '1') {
            $h['Изготовитель'] = 'Изготовитель: зелёный — в работе; жёлтый — закупка тех.запаса; синий — контроль сроков';
            $h['Недополучено'] = 'Кол-во выделено зелёным — доставка на сокол';
        }

        if ($paintDolg) {
            $h['Корпус'] = 'Корпус: розовый — элемент в резерв не заказан более 1 недели; коричневый — элемент на согласовании';
            $h['Долг'] = $h['МинКоличество'] = 'Долг выделен голубым — элемент заказывается на Торэкс';
        }

        // «Примечание» в списке — это ПримечаниеДолг (Поле18), подсказка та же, что у наименования
        $h['Примечание'] = $h['ПримечаниеДолг'] = $h['Наименование'];

        return $h;
    }

    /**
     * Список фирм: exec dbo.xp_GetWhatFirm (2 столбца: ID, Name).
     * Используется для WhatFirm (одиночный выбор) и для мультивыбора фирм (f).
     *
     * @return array<int, array{id:string, name:string}>
     */
    public function firms(): array
    {
        try {
            $rows = $this->sql('EXEC dbo.xp_GetWhatFirm')->queryAll();
        } catch (\Throwable $e) {
            Yii::warning('xp_GetWhatFirm: ' . $e->getMessage(), __METHOD__);
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $vals = array_values($r);
            $out[] = [
                'id'   => (string)($vals[0] ?? ''),
                'name' => (string)($vals[1] ?? $vals[0] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Список состояний проблемы: exec dbo.xp_StatWorkWithProblemLstSost [mode].
     *
     * mode = 1 — для fltStatProblEl (видимый фильтр, доступен в виде 1).
     * mode = 0 — для двойного щелчка по «Код» (frmfndStatus, OpenArgs 25).
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function problemStatuses(int $mode = 0): array
    {
        try {
            $sql = 'EXEC dbo.xp_StatWorkWithProblemLstSost' . ($mode ? ' ' . $mode : '');
            $rows = $this->sql($sql)->queryAll(\PDO::FETCH_NUM);
        } catch (\Throwable $e) {
            Yii::error('xp_StatWorkWithProblemLstSost: ' . $e->getMessage(), __METHOD__);
            return [];
        }

        return array_map(static fn($r) => [
            'id'   => (int)($r[0] ?? 0),
            'name' => (string)($r[1] ?? ''),
        ], $rows);
    }

    /**
     * Список значений ФЗ-275: exec dbo.xp_frmStLstFZ275.
     *
     * Процедура возвращает 0 «(Все записи)», -2 «ПГ», -3 «не ПГ» (ORDER BY ID desc).
     * В Access это ComboBox с ColumnWidths "0;1419;852": видно текст, значение — ID.
     * Список виден только в виде 1; в остальных видах Access ставит FZ275 = 0.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function fz275List(): array
    {
        try {
            $rows = $this->proc('dbo.xp_frmStLstFZ275')->queryAll(\PDO::FETCH_NUM);
        } catch (\Throwable $e) {
            Yii::warning('xp_frmStLstFZ275: ' . $e->getMessage(), __METHOD__);
            return [];
        }

        return array_map(static fn($r) => [
            'id'   => (int)($r[0] ?? 0),
            'name' => (string)($r[1] ?? ''),
        ], $rows);
    }

    /**
     * Колонки подформы аналогов (subFrmStAnalog): [поле => [подпись, ширина в твипах, вправо]].
     *
     * Порядок — TabIndex из SaveAsText; ширина в твипах делится на 15 → px, как в Access.
     * «Заказано» процедура отдаёт для расчёта txtSV, в самой подформе такой колонки нет — скрываем.
     */
    public const ANALOG_COLS = [
        'Наименование'   => ['Наименование', 1245, false],
        'Номинал'        => ['Номинал', 975, false],
        'Корпус'         => ['Корпус', 1635, false],
        'ПримечаниеДолг' => ['Примечание долга', 1485, false],
        'Izg'            => ['Изг.', 0, false],
        'Количество'     => ['Кол-во', 975, true],
        'Долг'           => ['Долг', 900, true],
        'Недополучено'   => ['Недополучено', 870, true],
        'МинКоличество'  => ['Мин. долг', 825, true],
        'txtSV'          => ['Заказать', 795, true],
        'Код'            => ['Код', 705, false],
        'Серия'          => ['Серия', 1050, false],
        'IsKat'          => ['Кат.', 420, false],
        'Заказано'       => ['Заказано', 0, true],
    ];

    /** Служебные поля аналогов: в подформе не показываются. */
    public const ANALOG_HIDDEN = ['Заказано'];

    /**
     * Колонки аналогов по фактическим полям: порядок и подписи из ANALOG_COLS,
     * незнакомые поля — в конец, как есть.
     *
     * @return array<int, array{0:string,1:string,2:int,3:bool}>
     */
    public static function analogColumns(array $rows): array
    {
        $fields = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) {
                if (!in_array($k, self::ANALOG_HIDDEN, true)) $fields[$k] = true;
            }
        }
        $out = [];
        foreach (self::ANALOG_COLS as $f => [$title, $twips, $right]) {
            if (isset($fields[$f])) {
                $out[] = [$f, $title, $twips > 0 ? (int)round($twips / 15) : 0, $right];
                unset($fields[$f]);
            }
        }
        foreach (array_keys($fields) as $f) {
            $out[] = [$f, $f, 0, false];
        }
        return $out;
    }

    /**
     * Колонки списка по фактическим полям результата: у каждого вида своя процедура,
     * поэтому состав колонок берём из данных, а не из фиксированного списка.
     *
     * @return array<int, array{0:string,1:string,2:int,3:bool}> [поле, подпись, ширина, вправо]
     */
    public static function columns(array $rows, string $vid = ''): array
    {
        return StatColumns::build($rows, $vid);
    }

    /** Подпись поля для человека (шапка таблицы, подпись сортировки). */
    public static function label(string $field): string
    {
        return \app\components\FieldLabel::get($field);
    }

    /** Подписи видов: exec dbo.xp_GetVidSel (2 столбца Name, Num). Нет связи с БД — пустой список. */
    public function vids(): array
    {
        try {
            $rows = $this->proc('dbo.xp_GetVidSel')->queryAll();
        } catch (\Throwable $e) {
            Yii::warning('xp_GetVidSel: ' . $e->getMessage(), __METHOD__);
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $vals = array_values($r);
            $out[] = [
                'num'  => (string)($r['Num'] ?? $vals[1] ?? $vals[0] ?? ''),
                'name' => (string)($r['Name'] ?? $vals[0] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Список статистики: как SetRS4Statistic + SubfrmStat.RecordSource.
     * Возвращает ['rows' => [...], 'note' => '...'|null, 'error' => '...'|null].
     */
    public function list(StatFilter $f): array
    {
        $what = $f->what();
        if (!preg_match('/^[0-9ab](_0)?$/', $what)) {
            return ['rows' => [], 'note' => null, 'error' => 'Неизвестный вид списка.'];
        }
        // Виды 9 и a вызываются штатно (сигнатуры 8 параметров, как у остальных).
        // Их результат строится по dbo.Sklad1C с Who = CURRENT_USER — эту таблицу наполняет
        // PrepareMinKolFrom1C вне формы frmSt, поэтому при незаполненной таблице список пуст.
        // Так же ведёт себя Access: сама форма процедуру подготовки не вызывает.

        // В Access SetRS4Statistic получает уже готовую маску поиска: "%" + st + "%"
        // (frmMinimum: Call SetRS4Statistic(..., "%" + st + "%", ...)). Префикс «&» —
        // признак FlgPaintDolg = False: процедура снимает его и включает раскраску долга.
        // Пустая маска недопустима: в процедурах «... LIKE @S», поэтому при пустом поиске
        // обязательно остаётся «%», иначе список пуст (в Access при этом уходит '&%').
        $s   = '%' . (string)$f->fnd . '%';
        $fnd = StatContext::needPaintDolg() ? $s : '&' . $s;
        // Второй параметр SetRS4Statistic — Forms!frmst!f: мультивыбор фирм («%» или ID через запятую).
        // Поставщик filt1 сюда НЕ передаётся: в Access он работает только с временной таблицей счетов.
        $firmSel = (string)$f->f;
        $otv  = StatContext::otv();                       // SkladManagers.Column(2)
        $rgm  = (int)$f->typeSel;
        $firm = (int)$f->firm;                            // WhatFirm: значение передаётся всегда, видно только виду 1_0
        $fz   = (int)$f->fz275;                           // xp_frmStLstFZ275: 0 — все, -2 — ПГ, -3 — не ПГ
        $flt  = (int)$f->fltStatProblEl;                  // fltStatProblEl.DefaultValue = 255

        try {
            // Вид 7 всегда идёт через xp_frmStNewRS7_0 (в VBA проверка If What <> "7_0"),
            // потому что ему нужны Bnd / Razn / Kurs.
            if (str_starts_with($what, '7')) {
                // Внимание: здесь Otv в кавычках — как в VBA, в остальных видах он число.
                // Курс: если в фильтре не задан, берём тот же, что на экране учёта (Forms!frmUchet!KursUSD).
                $kurs = (float)$f->kurs > 0 ? (float)$f->kurs : StatContext::kursUsd();
                $rows = $this->sql('EXEC dbo.xp_frmStNewRS7_0 ?, ?, ?, ?, ?, 0, ?, ?, ?',
                    [$fnd, $firmSel, (string)$otv, $rgm, $firm,
                     (float)$f->bnd, (float)$f->razn, $kurs])->queryAll();
            } elseif ($f->dateS && $f->datePo) {
                // PickDate не пуст → всегда xp_frmStNewRS1_1, независимо от вида
                $rows = $this->proc('dbo.xp_frmStNewRS1_1 ?, ?, ?, ?, ?, ?, ?, 0, ?, ?',
                    [$fnd, $firmSel, $otv, $rgm, $firm, $this->ymd($f->dateS), $this->ymd($f->datePo), $fz, $flt])->queryAll();
            } else {
                // FirstTime в Access (cboflt_AfterUpdate): при первом применении фильтров,
                // если все они в значениях по умолчанию, передаётся SkladID = -500 —
                // процедура отдаёт пустой список «установите фильтры», а не весь склад.
                $sklad = $f->firstTime() && $f->isDefaultSet() ? -500 : (int)$f->sklad;
                $rows = $this->proc('dbo.xp_frmStNewRS' . $what . ' ?, ?, ?, ?, ?, ?, ?, ?',
                    [$fnd, $firmSel, $otv, $rgm, $firm, $sklad, $fz, $flt])->queryAll();
            }
        } catch (\Throwable $e) {
            Yii::error('xp_frmStNewRS' . $what . ': ' . $e->getMessage(), __METHOD__);
            return ['rows' => [], 'note' => null, 'error' => 'Не удалось получить список: ' . $e->getMessage()];
        }

        // Пустой ответ в режиме -500 — это не ошибка, а приглашение выбрать фильтры
        $note = (!$rows && $f->firstTime() && $f->isDefaultSet())
            ? 'Выберите фильтры, чтобы получить список (в Access форма тоже открывается пустой).'
            : null;

        $rows = $this->addLowR($rows, $what, $f);

        return ['rows' => $rows, 'note' => $note, 'error' => null];
    }

    /**
     * Колонка LowR («Дешевле») — в Access это вычисляемое поле подформы, в наборе его нет.
     *
     * fnRepaintFormStat:
     *   виды 1,2,3,4,5,9,a — LowR.ControlSource = "=[txtSv]*[ЦенаОптовая]*[KursUSD]";
     *   вид 7             — "=[txtSv]*[ЦенаОптовая]" (цена уже в долларах, dl = True);
     *   вид b             — "MinStartZak" (поле приходит из процедуры, ничего не считаем).
     *
     * Значение «Заказать» берём из txtSV, а если процедура назвала его OrderEls
     * (вид 2, 3) — оттуда. Курс — тот же, что на экране учёта (Forms!frmUchet!KursUSD).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private function addLowR(array $rows, string $what, StatFilter $f): array
    {
        if (!$rows || str_starts_with($what, 'b')) return $rows;

        $kurs = str_starts_with($what, '7')
            ? 1.0
            : ((float)$f->kurs > 0 ? (float)$f->kurs : StatContext::kursUsd());

        foreach ($rows as &$r) {
            $kol = $r['txtSV'] ?? $r['OrderEls'] ?? null;
            if ($kol === null || !isset($r['ЦенаОптовая'])) continue;
            $r['LowR'] = round((float)$kol * (float)$r['ЦенаОптовая'] * $kurs, 2);
        }
        unset($r);

        return $rows;
    }

    /**
     * Аналоги текущего элемента — подформа аналогов «Статистики» (subFrmStAnalog).
     *
     * Источник: exec dbo.xp_frmStRS1Analog @SkladID, где @SkladID — «Код» строки списка.
     * Процедура сама подбирает аналоги по Элемент/Серия/Наименование/Номинал исходного
     * элемента и сортирует по Количеству.
     *
     * Долг в процедуре считается как SUM(резервы) − kolWOUpak, поэтому «Заказать» (txtSV)
     * в Access — вычисляемое поле: IIf([Недополучено]-[Долг]<0, [Долг]-[Недополучено], 0).
     *
     * @return array{rows:array, error:?string}
     */
    public function analogs(int $skladId): array
    {
        try {
            $rows = $this->proc('dbo.xp_frmStRS1Analog ?', [$skladId])->queryAll();
        } catch (\Throwable $e) {
            Yii::error('xp_frmStRS1Analog ' . $skladId . ': ' . $e->getMessage(), __METHOD__);
            return ['rows' => [], 'error' => 'Не удалось получить аналоги: ' . $e->getMessage()];
        }

        foreach ($rows as &$r) {
            $need = (float)($r['Долг'] ?? 0) - (float)($r['Недополучено'] ?? 0);
            $r['txtSV'] = $need > 0 ? $need : 0;
        }
        unset($r);

        return ['rows' => $rows, 'error' => null];
    }

    /**
     * Резервы текущего элемента — подформа SubMinimFilter формы frmSt.
     *
     * Источник: exec dbo.xp_SubMinimumFilter @txtID, @Rgm, где
     *   @txtID — «Код» текущей строки списка (SkladID),
     *   @Rgm   — вид списка строкой (в процедуре ветка расчёта долга для '1', '5', '8').
     *
     * Процедура сама определяет, из-за каких резервов элемент попал в долг
     * (IsGuilty = '!'), и отдаёт готовые строки в порядке Access:
     * IDRes = 8579 первым, затем виновные, затем по DtaPost/Дате убыв.
     *
     * В форме колонка «В» вычисляемая: =IIf([IsExactDate]=True,"!",""), поэтому
     * добавляем её здесь, а IsExactDate/DtaPost остаются служебными.
     *
     * @return array{rows:array, error:?string}
     */
    public function reserves(int $skladId, string $vid = '1'): array
    {
        if ($skladId <= 0) {
            return ['rows' => [], 'error' => null];
        }

        // Процедура отдаёт набор только в ветке @Rgm in ('1','5','8'); для остальных
        // видов SELECT не выполняется, и запрос вернул бы ошибку «нет полей».
        if (!in_array($vid, ['1', '5', '8'], true)) {
            return ['rows' => [], 'error' => null];
        }

        try {
            $rows = $this->proc('dbo.xp_SubMinimumFilter ?, ?', [$skladId, $vid])->queryAll();
        } catch (\Throwable $e) {
            Yii::error('xp_SubMinimumFilter ' . $skladId . '/' . $vid . ': ' . $e->getMessage(), __METHOD__);
            return ['rows' => [], 'error' => 'Не удалось получить резервы: ' . $e->getMessage()];
        }

        foreach ($rows as &$r) {
            $r['В'] = !empty($r['IsExactDate']) ? '!' : '';
        }
        unset($r);

        return ['rows' => $rows, 'error' => null];
    }

    /**
     * Примечание элемента (двойной щелчок по «Примечание»): CommonStartSP6(ID, xp_SkladUpdPrim, текст, 0, 500).
     *
     * @return array{ok:bool, error:?string} 1 — изменено, 2 — нет прав, иное — не удалось (как в Access)
     */
    public function setPrim(int $id, string $text): array
    {
        $res = LegacySp::sp6($id, 'dbo.xp_SkladUpdPrim', $text, 0, 500);

        return match ($res) {
            1       => ['ok' => true, 'error' => null],
            2       => ['ok' => false, 'error' => 'У вас нет прав на изменение примечания.'],
            default => ['ok' => false, 'error' => 'Не удалось изменить примечание.'],
        };
    }

    /**
     * Смена состояния проблемы: CommonStartSP4(ID, xp_StatWorkWithProblemUpdSost, Sost, 0, adInteger, adInteger).
     * В Access успех — результат > 0, иначе «Не удалось изменить состояние».
     *
     * @return array{ok:bool, error:?string}
     */
    public function setProblemStatus(int $id, int $sost): array
    {
        $res = (int)LegacySp::sp4($id, 'dbo.xp_StatWorkWithProblemUpdSost', $sost, 0, 'int', 'int');

        return $res > 0
            ? ['ok' => true, 'error' => null]
            : ['ok' => false, 'error' => 'Не удалось изменить состояние.'];
    }

    /**
     * Комментарий производства (вид b, двойной щелчок по «SostProblEl»):
     * CommonStartSP7(ID, xp_SkladTrackingUpdComm, текст, "  ", 1024, 255) → 'ok' или 'er' + текст.
     *
     * @return array{ok:bool, error:?string}
     */
    public function setTrackingComm(int $id, string $text): array
    {
        $res = LegacySp::sp7($id, 'dbo.xp_SkladTrackingUpdComm', $text, '  ', 1024, 255);

        if (str_starts_with($res, 'ok')) {
            return ['ok' => true, 'error' => null];
        }
        if (str_starts_with($res, 'er')) {
            return ['ok' => false, 'error' => mb_substr($res, 2)];
        }
        return ['ok' => false, 'error' => 'Не удалось изменить комментарий производства.'];
    }

    /**
     * Флаг «Счёт запр.» — CheckBox RequestSch подформы frmMinimum.
     *
     * VBA: RequestSch_MouseDown → UpdateNotRecFlg(Me!txtID, "ReqSch") →
     * CommonStartSP6(SkladID, "dbo.xp_UpdateNotRecFlgNew", Rgm, , , 50).
     * Коды результата: 0 — нет прав, 1 — изменено, 2 — не удалось.
     *
     * @return array{ok:bool, error:?string}
     */
    public function toggleRequestSch(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'Не указан элемент.'];
        }

        $res = LegacySp::sp6($id, 'dbo.xp_UpdateNotRecFlgNew', 'ReqSch', 0, 50);

        return match ($res) {
            1       => ['ok' => true, 'error' => null],
            0       => ['ok' => false, 'error' => 'У вас нет прав на изменение этого флажка'],
            default => ['ok' => false, 'error' => 'Не удалось изменить состояние флажка'],
        };
    }

    /**
     * Флажок NotRec подформы frmMinimum (CheckBox без подписи, «не рекомендуется к поставке»).
     *
     * VBA: NotRec_MouseDown → UpdateNotRecFlg(Me!txtID) → Rgm по умолчанию "NotRec" →
     * CommonStartSP6(SkladID, "dbo.xp_UpdateNotRecFlgNew", Rgm, , , 50).
     * Коды результата те же, что у «Счёт запр.»: 0 — нет прав, 1 — изменено, 2 — не удалось.
     *
     * @return array{ok:bool, error:?string}
     */
    public function toggleNotRec(int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'Не указан элемент.'];
        }

        $res = LegacySp::sp6($id, 'dbo.xp_UpdateNotRecFlgNew', 'NotRec', 0, 50);

        return match ($res) {
            1       => ['ok' => true, 'error' => null],
            0       => ['ok' => false, 'error' => 'У вас нет прав на изменение этого флажка'],
            default => ['ok' => false, 'error' => 'Не удалось изменить состояние флажка'],
        };
    }

    /**
     * Признаки строк для раскраски «Наименование» — ConditionalFormat14 подформы frmMinimum.
     *
     * VBA:
     *   ExactDate([txtID]) → CommonStartSP1(txtID, "dbo.xp_IsExactDate", 0) > 0;
     *   DatePost([txtID])  → xp_GetDatePost(txtID, @Dta OUTPUT), затем Dta <= Date().
     * Оба вызываются на каждую строку, а строк в списке бывает несколько сотен,
     * поэтому все вызовы идут одним батчем: отдельный запрос на строку слишком дорог.
     * Ошибка любой процедуры — признаков нет, как On Error в VBA (функция даёт False).
     *
     * @param array $ids коды строк списка
     * @return array<int, array{ex:bool, dp:bool}> по коду: ex — ExactDate, dp — DatePost
     */
    public function dateFlags(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        if (!$ids) {
            return [];
        }

        $sql = "SET NOCOUNT ON;\nDECLARE @t TABLE (id int PRIMARY KEY, ex int NULL, dp datetime NULL);\n"
            . "DECLARE @r int, @d datetime;\n";
        foreach ($ids as $id) {
            $sql .= "SET @r = NULL; SET @d = NULL;"
                . " EXEC dbo.xp_IsExactDate $id, @r OUTPUT;"
                . " EXEC dbo.xp_GetDatePost $id, @d OUTPUT;"
                . " INSERT INTO @t (id, ex, dp) VALUES ($id, @r, @d);\n";
        }
        $sql .= 'SELECT id, ex, dp FROM @t';

        try {
            $rows = Yii::$app->db->createCommand($sql)->queryAll();
        } catch (\yii\db\Exception $e) {
            Yii::error('dateFlags: ' . $e->getMessage(), __METHOD__);
            return [];
        }

        $today = date('Y-m-d');
        $out = [];
        foreach ($rows as $r) {
            if (!isset($r['id'])) {
                continue;                       // процедура вернула свой набор — признаков нет
            }
            $dp = $r['dp'] === null ? null : substr((string)$r['dp'], 0, 10);
            $out[(int)$r['id']] = [
                'ex' => (int)$r['ex'] > 0,
                'dp' => $dp !== null && $dp <= $today,
            ];
        }
        return $out;
    }

    /**
     * Пересчёт таргета и целевого предложения по резерву: dbo.xp_UpdTrgRec(@IDres, @numtbl).
     *
     * В Access результат не проверяется — только ошибки выполнения, после вызова
     * подформа резервов перезапрашивается (TrgRecalc_Click → UpdTrgRec).
     *
     * @return array{ok:bool, error:?string}
     */
    public function updTrgRec(int $idRes, int $numtbl): array
    {
        if ($idRes <= 0) {
            return ['ok' => false, 'error' => 'Не удается найти резерв'];
        }

        try {
            $this->proc('dbo.xp_UpdTrgRec ?, ?', [$idRes, $numtbl])->execute();
        } catch (\Throwable $e) {
            Yii::error('xp_UpdTrgRec ' . $idRes . '/' . $numtbl . ': ' . $e->getMessage(), __METHOD__);
            return ['ok' => false, 'error' => 'Не удалось обновить таргет: ' . $e->getMessage()];
        }

        return ['ok' => true, 'error' => null];
    }

    /**
     * Сортировка списка: в Access OrderBy у подформы (6 переключателей Ord*).
     * Сортируем на сервере, как это делал Access после смены OrderBy.
     */
    public function sort(array $rows, ?string $field, ?string $dir): array
    {
        if (!$field || !$rows || !array_key_exists($field, $rows[0])) return $rows;
        $sign = $dir === 'desc' ? -1 : 1;
        usort($rows, static function ($a, $b) use ($field, $sign) {
            $x = $a[$field] ?? null;
            $y = $b[$field] ?? null;
            if (is_numeric($x) && is_numeric($y)) return $sign * ($x <=> $y);
            return $sign * strnatcasecmp((string)$x, (string)$y);
        });
        return $rows;
    }

    /** '2026-11-19' → '20261119', как Format$(..., "yyyymmdd"). */
    private function ymd(?string $d): string
    {
        return $d ? str_replace('-', '', $d) : '';
    }

    /** EXEC процедура ?, ?, ... с параметрами по порядку (позиционные считаются с 1). */
    private function proc(string $proc, array $values = [])
    {
        return $this->sql('EXEC ' . $proc, $values);
    }

    /** Запрос с параметрами по порядку; SET NOCOUNT ON — без него драйвер иногда отдаёт пустой результат. */
    private function sql(string $sql, array $values = [])
    {
        $bind = [];
        foreach (array_values($values) as $i => $v) {
            $bind[$i + 1] = $v;
        }
        return Yii::$app->db->createCommand('SET NOCOUNT ON; ' . $sql, $bind);
    }
}
