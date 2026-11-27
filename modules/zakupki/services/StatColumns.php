<?php
namespace app\modules\zakupki\services;

/**
 * Колонки списка «Статистики» по фактическим полям результата.
 *
 * В Access состав и подписи колонок задавала подформа frmMinimum, а fnRepaintFormStat
 * при смене «Вида» правила:
 *   - Caption подписей (Надпись19 у txtSV, МинКоличество_Надпись, Надпись42 у LowR,
 *     FZ275Kol_N, Надпись57 у OrderKat, Надпись51 у SostProblEl, Надпись70 у DfltIsp);
 *   - Visible у части колонок (FZ275Kol — только виды 1 и 3; вид 5 скрывает
 *     МинКоличество и txtSV; вид b прячет KolPok/NotRec/VP5/NoAnalog).
 *
 * Значения в CAPTIONS — дословно из VBA. Вид, которого нет в списке, оставляет
 * подпись по умолчанию из META.
 */
class StatColumns
{
    /**
     * Служебные поля набора: в подформе frmMinimum таких колонок нет.
     *
     * Список сверен с перечнем контролов frmMinimum (Begin TextBox/Label): в подформе
     * выводятся только Код, Наименование, Аналог, Номинал, Корпус, Изготовитель,
     * Покупать в, Долг/Минимум/Резерв, по ФЗ, Кол-во, Заказать, Катушка, КП, Недопол.,
     * Примечание, Дешевле, Не рек, ВП5, Без аналог., Счёт запр.
     * Остальные поля процедуры — технические (группировка, расчёт, ключи).
     */
    public const HIDDEN = [
        'IDCursor', 'reqSchColor', 'chk', 'IDInvoice', 'Colr', 'IDRes', 'IsBoth', 'TakeMe4St',
        'MainPlace', 'Элемент', 'Заказано', 'ЦенаОптовая', 'FirstName', 'ProblKol', 'UchetID',
        'SharpName', 'NmeCat', 'CreatorCode', 'ExistMove', 'UPKoef', 'UpKoef', 'MinToBuy', 'TS',
        'FullDolg', 'KolInRes', 'SrcProblEl', 'KolZak', 'Свободно', 'DtaPost', 'IsExactDate',
        'MinStartZak', 'AppearMinTxt', 'AppearMinByAdd', 'NedopolByNakl', 'Avt', 'IsDelayed',
        'WhatFirmTxt', 'NU', 'flgNU', 'Активный', 'Примечание', 'Серия',
    ];

    /**
     * Порядок колонок подформы frmMinimum (по свойству Left контролов слева направо).
     *
     * В Access порядок задавали сами контролы; в вебе колонки строятся из набора,
     * поэтому порядок фиксируем здесь. Поля набора, которых нет в списке, идут в конец.
     * «Долг» / «МинКоличество» / «Резерв» / «OrderEls» — одно и то же место подформы:
     * у разных видов туда подставляется разный ControlSource (см. CAPTIONS).
     */
    public const ORDER = [
        'Код', 'Наименование', 'Analog', 'Номинал', 'Корпус', 'Изготовитель', 'ГдеКупил',
        'Долг', 'МинКоличество', 'Резерв', 'FZ275Kol', 'Количество', 'Кол',
        'txtSV', 'OrderEls', 'OrderKat', 'KolPok', 'Недополучено', 'ПримечаниеДолг',
        'LowR', 'SostProblEl', 'VP5', 'NoAnalog', 'DfltIsp', 'RequestSch',
    ];

    /** [подпись, ширина px, выравнивание вправо]. Поля, которых здесь нет, выводятся как есть. */
    public const META = [
        'Код'            => ['Код', 70, false],
        'Наименование'   => ['Наименование', 300, false],
        'Analog'         => ['Аналог', 200, false],
        'Номинал'        => ['Номинал', 110, false],
        'Корпус'         => ['Корпус', 100, false],
        'Изготовитель'   => ['Изготовитель', 150, false],
        'ГдеКупил'       => ['Покупать в', 140, false],
        'Долг'           => ['Долг', 80, true],
        'МинКоличество'  => ['Минимум', 80, true],
        'Резерв'         => ['Резерв', 80, true],
        'FZ275Kol'       => ['по ФЗ', 70, true],
        'Количество'     => ['Кол-во', 80, true],
        'Кол'            => ['Кол-во', 80, true],
        'txtSV'          => ['Заказать', 80, true],
        'OrderEls'       => ['Заказать', 80, true],
        'OrderKat'       => ['Катушка', 80, true],
        'KolPok'         => ['КП', 50, false],
        'Недополучено'   => ['Недопол.', 80, true],
        'ПримечаниеДолг' => ['Примечание', 260, false],
        'LowR'           => ['Дешевле,руб', 100, true],
        'SostProblEl'    => ['Не рек', 70, false],
        'NotRec'         => ['Не рек.', 60, false],
        'VP5'            => ['ВП5', 50, false],
        'NoAnalog'       => ['Без аналог.', 80, false],
        'DfltIsp'        => ['Информация', 100, false],
        'RequestSch'     => ['Счёт запр.', 90, false],
        'Страна'         => ['Страна', 110, false],
        'Производитель'  => ['Производитель', 150, false],
        'Движение с'     => ['Движение с', 100, false],
        'Кратко'         => ['Кратко', 150, false],
        'Исполнитель'    => ['Исполнитель', 120, false],
    ];

    /**
     * Колонки, которые Access скрывает в отдельных видах (fnRepaintFormStat).
     *
     * Вид 5: `fBl = Not lgOp = "5"` — МинКоличество и txtSV (и их подписи) скрыты;
     *        ControlSource у них очищается, поэтому скрываем и варианты поля расчёта.
     * Вид b: в Case "b" явно KolPok/NotRec/VP5/NoAnalog.Visible = False, FZ275Kol там тоже False.
     */
    public const HIDDEN_IN = [
        '5' => ['МинКоличество', 'txtSV', 'OrderEls', 'Долг', 'Резерв'],
        'b' => ['KolPok', 'NotRec', 'VP5', 'NoAnalog', 'FZ275Kol', 'RequestSch'],
    ];

    /**
     * Колонки, которые в остальных видах скрыты, а в перечисленных показываются.
     *
     * FZ275Kol: Visible = True только в ветках 1 и 3.
     * OrderKat и KolPok: Надпись57.Visible = lgOp = "1", OrderKat/KolPok.Visible = Надпись57.Visible;
     *        в Case "b" OrderKat.Width = 900 и подпись «Ближ. поставка» (KolPok там скрыт).
     * DfltIsp: в frmMinimum Visible = NotDefault, то есть скрыт; включается только в Case "b".
     * RequestSch: True в ветках 1, 2, 3; в 4, 5, 7, 9, a, b — False.
     */
    public const SHOWN_IN = [
        'FZ275Kol'   => ['1', '3'],
        'OrderKat'   => ['1', 'b'],
        'KolPok'     => ['1'],
        'DfltIsp'    => ['b'],
        'RequestSch' => ['1', '2', '3'],
    ];

    /** Подписи колонок, которые fnRepaintFormStat меняет при смене вида (дословно из VBA). */
    public const CAPTIONS = [
        'txtSV' => [
            '1' => 'Заказать', '2' => 'Заказать', '3' => 'Долг', '4' => 'Заказать',
            '7' => 'Заказать', '9' => 'Заказать', 'a' => 'Заказать', 'b' => 'Миним. сработал',
        ],
        'OrderEls' => [
            '1' => 'Заказать', '2' => 'Заказать', '3' => 'Долг', '4' => 'Заказать',
            '7' => 'Заказать', '9' => 'Заказать', 'a' => 'Заказать',
        ],
        'МинКоличество' => [
            '2' => 'Минимум', '3' => 'Минимум', '9' => 'Мин.ПАНТЕС', 'a' => 'Мин.ТОРЭКС',
            'b' => 'Мин. срок годн',
        ],
        'Долг'          => ['1' => 'Долг', '7' => 'Долг'],
        'Резерв'        => ['4' => 'Резерв'],
        'Недополучено'  => ['b' => 'Заказано'],
        'LowR'          => ['7' => 'Дешевле,$', 'b' => 'Бл.старт заказа'],
        'MinStartZak'   => ['b' => 'Бл.старт заказа'],
        'FZ275Kol'      => ['1' => 'Заказать', '3' => 'Заказать', 'b' => 'Мин.'],
        'OrderKat'      => ['1' => 'Ближ. поставка', 'b' => 'Ближ. поставка'],
        'SostProblEl'   => ['b' => 'Ком.произв.'],
        'DfltIsp'       => ['b' => 'Информация'],
        'Analog'        => ['b' => 'Серия'],
    ];

    /**
     * Колонки по фактическим полям результата: у каждого вида своя процедура,
     * поэтому состав колонок берём из данных, а порядок — из ORDER (как в frmMinimum).
     *
     * @param array  $rows строки списка
     * @param string $vid  вид списка (1…b); пусто — без правок по виду
     * @return array<int, array{0:string,1:string,2:int,3:bool}> [поле, подпись, ширина, вправо]
     */
    public static function build(array $rows, string $vid = ''): array
    {
        $fields = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) {
                if (!in_array($k, self::HIDDEN, true)) $fields[$k] = true;
            }
        }

        // Порядок как в подформе: сначала ORDER, затем всё остальное в порядке набора
        $ordered = [];
        foreach (self::ORDER as $f) {
            if (isset($fields[$f])) {
                $ordered[$f] = true;
                unset($fields[$f]);
            }
        }
        foreach (array_keys($fields) as $f) {
            $ordered[$f] = true;
        }

        $out = [];
        foreach (array_keys($ordered) as $f) {
            // Скрыто целиком в этом виде (вид 5 — МинКоличество и txtSV)
            if (in_array($f, self::HIDDEN_IN[$vid] ?? [], true)) continue;
            // Показывается только в перечисленных видах (FZ275Kol, OrderKat, KolPok, DfltIsp, RequestSch)
            if (isset(self::SHOWN_IN[$f]) && $vid !== '' && !in_array($vid, self::SHOWN_IN[$f], true)) continue;

            [$title, $width, $right] = self::META[$f] ?? [$f, 0, false];
            // Caption по виду перекрывает подпись по умолчанию
            $title = self::CAPTIONS[$f][$vid] ?? $title;
            $out[] = [$f, $title, $width, $right];
        }
        return $out;
    }
}
