<?php
namespace app\modules\zakupki\services;

use app\modules\zakupki\models\StatFilter;
use app\services\LegacySp;
use Yii;

/**
 * Процедуры формы frmSt «Статистика» (аналог SetRS4Statistic из модуля формы).
 *
 * Параметры передаются ПО ПОРЯДКУ (знаки ?), как в VhSchetService: настоящих имён
 * параметров процедур мы пока не знаем.
 *
 * Порядок вызова из VBA (SetRS4Statistic):
 *   xp_frmStNewRS{What}  'fnd', 'Post', Otv, Rgm, WhatFirm, SkladID, FZ275, fltStatProblEl
 *   xp_frmStNewRS7_0     'fnd', 'Post', 'Otv', Rgm, WhatFirm, 0, Bnd, Razn, Kurs
 *   xp_frmStNewRS1_1     'fnd', 'Post', Otv, Rgm, WhatFirm, 'yyyymmdd', 'yyyymmdd', 0, FZ275, fltStatProblEl
 *
 * ⚠ Что пока заглушки (см. StatContext): Otv (SkladManagers.Column(2)) и NeedPaintDolg (Column(3)),
 *   в Access они берутся из Forms!frmUchet. Курс USD (KursUSD) тоже оттуда — нужен только виду 7.
 */
class StatService
{
    /** Служебные поля подформы: в Access у них Visible = False либо это технические ключи. */
    public const HIDDEN = [
        'IDCursor', 'reqSchColor', 'chk', 'IDInvoice', 'Colr', 'IDRes', 'IsBoth', 'DfltIsp',
        'SostProblEl', 'TakeMe4St', 'RequestSch', 'MainPlace', 'OrderKat',
    ];

    /** [подпись, ширина px, выравнивание вправо]. Поля, которых здесь нет, выводятся как есть. */
    public const META = [
        'Код'           => ['Код', 70, false],
        'Наименование'  => ['Наименование', 300, false],
        'Analog'        => ['Аналог', 200, false],
        'Страна'        => ['Страна', 110, false],
        'Производитель' => ['Производитель', 150, false],
        'Движение с'    => ['Движение с', 100, false],
        'Кратко'        => ['Кратко', 150, false],
        'Кол-во'        => ['Кол-во', 80, true],
        'txtSV'         => ['Дефицит', 80, true],
        'Примечание'    => ['Примечание', 260, false],
        'Исполнитель'   => ['Исполнитель', 120, false],
        'LowR'          => ['Сумма,$', 100, true],
        'NotRec'        => ['Не рек.', 60, false],
        'VP5'           => ['ВП5', 50, false],
        'NoAnalog'      => ['Нет аналога', 80, false],
        'KolPok'        => ['Кол-во пок.', 90, true],
        'FZ275Kol'      => ['ФЗ-275', 70, true],
    ];

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
    public static function columns(array $rows): array
    {
        $fields = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) {
                if (!in_array($k, self::HIDDEN, true)) $fields[$k] = true;
            }
        }
        return array_map(
            static fn($f) => [$f, ...(self::META[$f] ?? [$f, 0, false])],
            array_keys($fields)
        );
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
            return $this->proc('dbo.xp_GetVidSel')->queryAll();
        } catch (\Throwable $e) {
            Yii::warning('xp_GetVidSel: ' . $e->getMessage(), __METHOD__);
            return [];
        }
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
        // Подготовительные режимы 9/a: сначала PrepareMinKolFrom1C, потом основной вызов.
        // Без этой процедуры список будет неверным, поэтому честно не показываем ничего.
        if (str_starts_with($what, '9') || str_starts_with($what, 'a')) {
            return ['rows' => [], 'note' => null,
                'error' => 'Вид «' . $what . '» пока не перенесён: нужна процедура PrepareMinKolFrom1C.'];
        }

        // FlgPaintDolg = False → к строке поиска добавляется «&» (так в Access по умолчанию).
        $fnd = StatContext::needPaintDolg() ? (string)$f->fnd : '&' . $f->fnd;
        $post = (string)$f->post;
        $otv  = StatContext::otv();                       // заглушка вместо SkladManagers.Column(2)
        $rgm  = (int)$f->typeSel;
        $firm = (int)$f->firm;                            // WhatFirm: значение передаётся всегда, видно только виду 1_0
        $fz   = (int)$f->fz275 === 1 ? -1 : 0;            // флажок Access: True = -1
        $flt  = 255;                                      // fltStatProblEl.DefaultValue = 255

        try {
            // Вид 7 всегда идёт через xp_frmStNewRS7_0 (в VBA проверка If What <> "7_0"),
            // потому что ему нужны Bnd / Razn / Kurs.
            if (str_starts_with($what, '7')) {
                // Внимание: здесь Otv в кавычках — как в VBA, в остальных видах он число.
                // Курс: если в фильтре не задан, берём тот же, что на экране учёта (Forms!frmUchet!KursUSD).
                $kurs = (float)$f->kurs > 0 ? (float)$f->kurs : StatContext::kursUsd();
                $rows = $this->sql('EXEC dbo.xp_frmStNewRS7_0 ?, ?, ?, ?, ?, 0, ?, ?, ?',
                    [$fnd, $post, (string)$otv, $rgm, $firm,
                     (float)$f->bnd, (float)$f->razn, $kurs])->queryAll();
            } elseif ($f->dateS && $f->datePo) {
                // PickDate не пуст → всегда xp_frmStNewRS1_1, независимо от вида
                $rows = $this->proc('dbo.xp_frmStNewRS1_1 ?, ?, ?, ?, ?, ?, ?, 0, ?, ?',
                    [$fnd, $post, $otv, $rgm, $firm, $this->ymd($f->dateS), $this->ymd($f->datePo), $fz, $flt])->queryAll();
            } else {
                $sklad = (int)$f->sklad;
                $rows = $this->proc('dbo.xp_frmStNewRS' . $what . ' ?, ?, ?, ?, ?, ?, ?, ?',
                    [$fnd, $post, $otv, $rgm, $firm, $sklad, $fz, $flt])->queryAll();
            }
        } catch (\Throwable $e) {
            Yii::error('xp_frmStNewRS' . $what . ': ' . $e->getMessage(), __METHOD__);
            return ['rows' => [], 'note' => null, 'error' => 'Не удалось получить список: ' . $e->getMessage()];
        }

        return ['rows' => $rows, 'note' => null, 'error' => null];
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
     * Список состояний проблемы для выбора: exec dbo.xp_StatWorkWithProblemLstSost.
     * Это ветка «25» формы frmfndStatus (двойной щелчок по «Код»):
     * в Access Fields(0) — ключ, Fields(1) — подпись.
     *
     * @return array<int, array{id:int, name:string}>
     */
    public function problemStatuses(): array
    {
        try {
            $rows = $this->proc('dbo.xp_StatWorkWithProblemLstSost')->queryAll(\PDO::FETCH_NUM);
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
