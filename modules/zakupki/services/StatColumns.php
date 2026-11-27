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
        'MinStartZak', 'AppearMinByAdd', 'NedopolByNakl', 'Avt', 'IsDelayed',
        'WhatFirmTxt', 'WF', 'NeedOzn', 'NU', 'flgNU', 'Активный', 'Примечание', 'Серия', 'KursUSD',
    ];

    /**
     * Порядок мест подформы frmMinimum (по свойству Left контролов слева направо).
     *
     * Устарело как источник порядка: порядок задаёт SLOTS (там же перечислены
     * поля-двойники одного места). Здесь оставлен перечень мест для справки.
     */
    public const ORDER = [
        'Код', 'Наименование', 'Analog', 'Номинал', 'Корпус', 'Изготовитель', 'ГдеКупил',
        'МинКоличество', 'FZ275Kol', 'Количество', 'txtSV', 'OrderKat', 'KolPok',
        'Недополучено', 'ПримечаниеДолг', 'LowR', 'SostProblEl', 'NotRec', 'VP5',
        'NoAnalog', 'DfltIsp', 'RequestSch',
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
        'AppearMinTxt'   => ['Миним. сработал', 90, true],
        'OrderKat'       => ['Катушка', 80, true],
        'KolPok'         => ['КП', 50, false],
        'Недополучено'   => ['Недопол.', 80, true],
        'ПримечаниеДолг' => ['Примечание', 260, false],
        'LowR'           => ['Дешевле,руб', 100, true],
        'SostProblEl'    => ['Ком.произв.', 90, false],
        // «Не рек» — это CheckBox NotRec (подпись отдельным Label в Access, Left 22109);
        // SostProblEl в списке не выводится, кроме вида b, см. SHOWN_ONLY_IN
        'NotRec'         => ['Не рек', 64, false],
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
     * Места контролов подформы frmMinimum в порядке Left (слева направо).
     *
     * В Access часть мест переиспользуется: контрол `МинКоличество` (Left = 12468)
     * в разных видах показывает `Долг`, `МинКоличество` или `Резерв` (fnRepaintFormStat
     * меняет ControlSource), контрол `txtSV` (Left = 14823) — `txtSV`, `OrderEls`
     * или вычисляемое поле. Поэтому место — это список полей-кандидатов: берём первое,
     * которое реально пришло в наборе, остальные не выводим (иначе колонки дублируются
     * и съезжает порядок).
     *
     * Порядок сверен с Left контролов: Код 450, Наименование 1095, Analog 3921,
     * Номинал 5899, Корпус 7819, Изготовитель 9394, ГдеКупил 10967,
     * МинКоличество 12468, FZ275Kol 13203, Количество 13938, txtSV 14823,
     * OrderKat 15708, KolPok 16443, Недополучено 16863, Поле18 17758, LowR 21015,
     * SostProblEl 22095, NotRec 22326, VP5 22847, NoAnalog 23312, DfltIsp 23470,
     * RequestSch 24661.
     */
    public const SLOTS = [
        ['Код'],
        ['Наименование'],
        ['Analog'],
        ['Номинал'],
        ['Корпус'],
        ['Изготовитель'],
        ['ГдеКупил'],
        ['Долг', 'МинКоличество', 'Резерв'],
        ['FZ275Kol'],
        ['Количество', 'Кол'],
        ['txtSV', 'OrderEls', 'AppearMinTxt'],
        ['OrderKat'],
        ['KolPok'],
        ['Недополучено'],
        ['ПримечаниеДолг'],
        ['LowR'],
        ['SostProblEl'],
        ['NotRec'],
        ['VP5'],
        ['NoAnalog'],
        ['DfltIsp'],
        ['RequestSch'],
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

    /**
     * Колонки, которые видны только в перечисленных видах и скрыты во всех остальных.
     *
     * SostProblEl: в макете подпись «Не рек» стоит над этим полем, но в списке «Не рек» —
     * это CheckBox NotRec. SostProblEl показывается только в виде b, где fnRepaintFormStat
     * делает его видимым под подписью «Ком.произв.» (комментарий производства).
     * Отличие от SHOWN_IN: здесь скрыто и при неизвестном/пустом виде.
     */
    public const SHOWN_ONLY_IN = [
        'SostProblEl' => ['b'],
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
        'AppearMinTxt' => ['b' => 'Миним. сработал'],
        'МинКоличество' => [
            '2' => 'Минимум', '3' => 'Минимум', '9' => 'Мин.ПАНТЕС', 'a' => 'Мин.ТОРЭКС',
            'b' => 'Мин. срок годн',
        ],
        'Долг'          => ['1' => 'Долг', '7' => 'Долг'],
        'Резерв'        => ['4' => 'Резерв'],
        'Недополучено'  => ['b' => 'Заказано'],
        'LowR'          => ['7' => 'Дешевле,$', 'b' => 'Бл.старт заказа'],
        'MinStartZak'   => ['b' => 'Бл.старт заказа'],
        'FZ275Kol'      => ['3' => 'Заказать', 'b' => 'Мин.'],
        'OrderKat'      => ['b' => 'Ближ. поставка'],
        'SostProblEl'   => ['b' => 'Ком.произв.'],
        'DfltIsp'       => ['b' => 'Информация'],
        'Analog'        => ['b' => 'Серия'],
    ];

    /**
     * Колонки подформы резервов SubMinimFilter.
     *
     * Источник — exec dbo.xp_SubMinimumFilter @txtID, @Rgm. Состав полей и подписи
     * сверены с колонками формы (ControlSource и Caption подписей Надпись54/51/23/39/41/42/30/56):
     *   Дата, В (=IIf([IsExactDate]=True,"!","")), Осталось (поле Razn — «5н.3д.»),
     *   Расч.ст-ть,$ (CalcPrc), Таргет,USD (Trg), Макс. цена закуп.,USD (MaxPrc),
     *   Название темы, Примечание (Prim), Заказчик (Firma), Срок не согл. (DisagreeDate),
     *   План.старт (PlandateCmplx), ФЗ275., Ответственный, ИНН, Исп-ль (Isp),
     *   Отв.техподдержки (OtvIng), Изделий, Кол-во (Резерв), Всего (AllKol),
     *   Уровень токсичности, Заказ (NumZakas), Область применения (ApplyArea).
     *
     * @var array<string, array{0:string,1:int,2:bool}>
     */
    public const RESERVE_META = [
        'IsGuilty'      => ['Пробл', 45, false],
        'В'             => ['В', 30, false],
        'Дата'          => ['Дата', 70, false],
        'CalcPrc'       => ['Расч.ст-ть,$', 70, true],
        'DtaPost'       => ['Жел.дата пост.р-ва', 70, false],
        'PlandateCmplx' => ['План.старт', 75, false],
        'Trg'           => ['Таргет,USD', 70, true],
        'MaxPrc'        => ['Максим. цена закуп.,USD', 85, true],
        'Razn'          => ['Осталось', 70, false],
        'Название темы' => ['Название темы', 150, false],
        'Prim'          => ['Примечание', 160, false],
        'Firma'         => ['Заказчик', 130, false],
        'Isp'           => ['Исп-ль', 80, false],
        'INN'           => ['ИНН', 90, false],
        'ApplyArea'     => ['Область применения', 110, false],
        'ToxicLvl'      => ['Уровень токсичности', 110, false],
        'Ответственный' => ['Ответственный', 100, false],
        'OtvIng'        => ['Отв.техподдержки', 100, false],
        'Изделия'       => ['Изделий', 70, true],
        'Резерв'        => ['Кол-во', 60, true],
        'AllKol'        => ['Всего', 60, true],
        'DisagreeDate'  => ['Срок не согл.', 90, false],
        'FZ275'         => ['ФЗ275.', 55, false],
        'NumZakas'      => ['Заказ', 100, false],
    ];

    /**
     * Порядок колонок резервов — ColumnOrder формы после Form_Load.
     *
     * В макете CalcPrc.ColumnOrder = 4, из-за чего DtaPost и следующие сдвигаются
     * на единицу; здесь уже итоговый порядок отображения.
     */
    public const RESERVE_ORDER = [
        'IsGuilty', 'В', 'Дата', 'CalcPrc', 'DtaPost', 'PlandateCmplx', 'Trg', 'MaxPrc',
        'Razn', 'Название темы', 'Prim', 'Firma', 'Isp', 'INN', 'ApplyArea', 'ToxicLvl',
        'Ответственный', 'OtvIng', 'Изделия', 'Резерв', 'AllKol', 'DisagreeDate', 'FZ275', 'NumZakas',
    ];

    /**
     * Служебные поля резервов.
     *
     * Colr скрыт в frmSt (ColumnHidden = True), Idres/IDProcess — технические ключи,
     * IsExactDate нужен только для вычисления «В».
     */
    public const RESERVE_HIDDEN = ['Colr', 'Idres', 'IDRes', 'IDProcess', 'IsExactDate'];

    /**
     * Колонки резервов, скрываемые по виду (fnRepaintFormStat: f = lgOp = "b").
     *
     * При виде b в подформе скрыты все «закупочные» колонки; остаются Пробл, Дата,
     * Осталось, Примечание, Заказчик, Ответственный, Отв.техподдержки и Заказ.
     */
    public const RESERVE_HIDDEN_IN = [
        'b' => [
            'Название темы', 'DtaPost', 'Trg', 'MaxPrc', 'Изделия', 'Резерв', 'AllKol',
            'PlandateCmplx', 'В', 'CalcPrc', 'INN', 'ToxicLvl', 'ApplyArea', 'Isp',
            'DisagreeDate', 'FZ275',
        ],
    ];

    /**
     * Колонки подформы s (SubFrmStatSch): счета элемента, в виде b — история элемента.
     *
     * Источник — exec dbo.xp_SubFrmStatSch @txtID, @Rgm. Дизайна подформы в выгрузке Forms/ нет,
     * поэтому состав колонок собран из того, что известно:
     *   - frmSt (fnRepaintFormStat) обращается к полям подформы Количество и Недополучено
     *     (в виде b их ControlSource меняется на Кол и NedopolByNakl), к колонкам ProductDate,
     *     WarrantyDate, WarrantySrok (видны только в виде b) и к кнопке ShowSch;
     *   - вне вида b процедура отдаёт открытые позиции счетов (НомерСчета, ДатаСчета, Количество,
     *     Недополучено, СрокПоставки, Организация, Покупатель, …), в виде b — историю элемента
     *     (xp_HistoryElem: приход и оплаты, сроки, гарантия).
     *
     * Подписи и ширины полей истории — как во вкладке «История» карточки элемента
     * (subfrmObnovlen1, ElemService::COLS). Ширины колонок счетов ориентировочные.
     * Поля, которых здесь нет, выводятся в конце как есть.
     *
     * @var array<string, array{0:string,1:int,2:bool}>
     */
    public const SCH_META = [
        // счета (все виды, кроме b)
        'НомерСчета'      => ['№ счёта', 110, false],
        'ДатаСчета'       => ['Дата счёта', 80, false],
        'Организация'     => ['Организация', 220, false],
        'Покупатель'      => ['Покупатель', 120, false],
        'Количество'      => ['Кол-во', 70, true],
        'Недополучено'    => ['Недополучено', 90, true],
        'СрокПоставки'    => ['Срок поставки', 90, false],
        'Probl'           => ['Проблема', 70, false],
        // те же места подформы в виде b (ControlSource меняет fnRepaintFormStat)
        'Кол'             => ['Кол-во', 70, true],
        'NedopolByNakl'   => ['Недополучено', 90, true],
        'ProductDate'     => ['Дата выпуска', 94, false],
        'WarrantySrok'    => ['Срок гарантии', 94, false],
        'WarrantyDate'    => ['Гарантия до', 94, false],
        // история элемента (вид b): остальное — как во вкладке «История» карточки
        'НомерНакладной'   => ['№ накладной', 76, false],
        'ДатаНакладной'    => ['Дата накладной', 62, false],
        'ЦенаОптовая'      => ['Опт, $.', 60, true],
        'Цена с НДС (руб)' => ['Цена с НДС (руб)', 78, true],
        'ТочноеНазвание'   => ['Точное название', 285, false],
        'Prim'             => ['Примечание', 200, false],
        'IsProblem'        => ['Проблема', 60, false],
        'Кто'              => ['Кто', 60, false],
        'Когда'            => ['Когда', 80, false],
        'План.дата'        => ['План. дата', 80, false],
        'Оплачен'          => ['Оплачен', 80, false],
        'План.срок'        => ['План. срок', 80, false],
        'Реал.срок'        => ['Реал. срок', 80, false],
        'DelayedDays'      => ['Задержка, дн.', 70, true],
        'ГТД'              => ['ГТД', 155, false],
        'Страна'           => ['Страна', 80, false],
        'KolDataCode'      => ['Кол-во кодов', 80, true],
        'Ostatok'          => ['Остаток', 80, true],
        'FirmaBig'         => ['Фирма', 120, false],
    ];

    /**
     * Порядок колонок подформы s. В наборе присутствует либо «Количество» с «Недополучено»
     * (счета), либо «Кол» с «NedopolByNakl» (история), поэтому места не пересекаются.
     */
    public const SCH_ORDER = [
        'НомерСчета', 'ДатаСчета', 'Организация', 'Покупатель',
        'Количество', 'Кол', 'Недополучено', 'NedopolByNakl',
        'СрокПоставки', 'Probl', 'IsProblem',
        'ProductDate', 'WarrantySrok', 'WarrantyDate',
    ];

    /** Колонки-даты подформы s: выводятся как в Access, dd.mm.yy. */
    public const SCH_DATES = ['ДатаСчета', 'СрокПоставки', 'ProductDate', 'WarrantyDate', 'ДатаНакладной'];

    /**
     * Служебные поля подформы s.
     *
     * PredShcID и AllID — ключи; Tip, OverrideProblem — признаки; данные самого элемента
     * (Код, Элемент, Серия, …) повторяются в каждой строке — они уже есть в строке списка.
     * Технические поля истории (UpKoef, IDSchEl, ImportDate, …) скрыты и во вкладке «История»
     * карточки элемента (ElemService::COLS).
     */
    public const SCH_HIDDEN = [
        'PredShcID', 'AllID', 'Tip', 'OverrideProblem',
        'Код', 'Элемент', 'Серия', 'Наименование', 'Номинал', 'Корпус', 'Изготовитель',
        'ГдеКупил', 'ПримечаниеДолг', 'Kolvo',
        'UpKoef', 'Repl4Kfs', 'Реал.дата', 'kolPhoto', 'IDSchEl', 'WarrantyExpired',
        'InSklad', 'ImportDate', 'AdditFile',
    ];

    /**
     * Колонки подформы s по фактическим полям результата.
     *
     * @param array  $rows строки источника
     * @param string $vid  вид списка: в виде b признак IsProblem остаётся (в истории нет готовой
     *                     колонки Probl), в остальных видах скрыт — Probl уже выведен
     * @return array<int, array{0:string,1:string,2:int,3:bool}> [поле, подпись, ширина, вправо]
     */
    public static function schColumns(array $rows, string $vid = ''): array
    {
        $hidden = $vid === 'b' ? self::SCH_HIDDEN : array_merge(self::SCH_HIDDEN, ['IsProblem']);

        $fields = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) {
                if (!in_array($k, $hidden, true)) $fields[$k] = true;
            }
        }

        $out = [];
        foreach (self::SCH_ORDER as $f) {
            if (!isset($fields[$f])) continue;
            [$title, $width, $right] = self::SCH_META[$f] ?? [$f, 0, false];
            $out[] = [$f, $title, $width, $right];
            unset($fields[$f]);
        }
        foreach (array_keys($fields) as $f) {
            [$title, $width, $right] = self::SCH_META[$f] ?? [$f, 0, false];
            $out[] = [$f, $title, $width, $right];
        }
        return $out;
    }

    /**
     * Колонки списка LstZakasTrack главной формы frmSt — заказы, в которых участвует элемент,
     * с отметкой об ознакомлении (список виден только в виде b).
     *
     * Источник — exec dbo.xp_SkladTrackingZakLst @SkladID. Пять колонок процедуры — это те же
     * пять колонок списка (ColumnCount = 5), подписи берутся из набора (ColumnHeads = True).
     * Ширины в Access (510;739;908;567;965 твипов) рассчитаны на узкий список с прокруткой;
     * здесь панель шире, поэтому ширины свои.
     *
     * @var array<string, array{0:string,1:int,2:bool}>
     */
    public const TRACK_META = [
        'Ознакомлен' => ['Ознакомлен', 90, false],
        'Заказ'      => ['Заказ', 220, false],
        'Ближ.старт' => ['Ближ.старт', 90, false],
        'Кол.изд'    => ['Кол.изд', 70, true],
        'Название'   => ['Название', 320, false],
    ];

    /**
     * Колонки списка заказов по фактическим полям результата.
     *
     * @return array<int, array{0:string,1:string,2:int,3:bool}> [поле, подпись, ширина, вправо]
     */
    public static function trackColumns(array $rows): array
    {
        $fields = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) $fields[$k] = true;
        }

        $out = [];
        foreach (self::TRACK_META as $f => [$title, $width, $right]) {
            if (!isset($fields[$f])) continue;
            $out[] = [$f, $title, $width, $right];
            unset($fields[$f]);
        }
        foreach (array_keys($fields) as $f) {
            $out[] = [$f, $f, 0, false];
        }
        return $out;
    }

    /**
     * Колонки резервов по фактическим полям результата.
     *
     * @param array  $rows строки источника
     * @param string $vid  вид списка; влияет только на скрытие колонок (вид b)
     * @return array<int, array{0:string,1:string,2:int,3:bool}> [поле, подпись, ширина, вправо]
     */
    public static function reserveColumns(array $rows, string $vid = ''): array
    {
        $hidden = self::RESERVE_HIDDEN;
        if ($vid !== '') {
            $hidden = array_merge($hidden, self::RESERVE_HIDDEN_IN[$vid] ?? []);
        }

        $fields = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) {
                if (!in_array($k, $hidden, true)) $fields[$k] = true;
            }
        }

        $out = [];
        foreach (self::RESERVE_ORDER as $f) {
            if (!isset($fields[$f])) continue;
            [$title, $width, $right] = self::RESERVE_META[$f] ?? [$f, 0, false];
            $out[] = [$f, $title, $width, $right];
            unset($fields[$f]);
        }
        foreach (array_keys($fields) as $f) {
            $out[] = [$f, $f, 0, false];
        }
        return $out;
    }

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

        // Порядок как в подформе: сначала места контролов (SLOTS), затем всё остальное
        $ordered = [];
        foreach (self::SLOTS as $slot) {
            $pick = null;
            foreach ($slot as $f) {
                if (isset($fields[$f])) { $pick = $f; break; }
            }
            if ($pick === null) continue;
            $ordered[$pick] = true;
            // Альтернативы этого места — то же место подформы, второй раз не выводим
            foreach ($slot as $f) unset($fields[$f]);
        }
        foreach (array_keys($fields) as $f) {
            $ordered[$f] = true;
        }

        $out = [];
        foreach (array_keys($ordered) as $f) {
            // Скрыто целиком в этом виде (вид 5 — МинКоличество и txtSV)
            if (in_array($f, self::HIDDEN_IN[$vid] ?? [], true)) continue;
            // Видно только в перечисленных видах (SostProblEl — только b, «Ком.произв.»)
            if (isset(self::SHOWN_ONLY_IN[$f]) && !in_array($vid, self::SHOWN_ONLY_IN[$f], true)) continue;
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
