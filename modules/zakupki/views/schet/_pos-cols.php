<?php
/**
 * Столбцы подформы позиций счёта на элементы (SubFrmMassInputSch): в карточке счёта (LstMassInpt)
 * и в накладной (lstSch). Порядок как после Form_Open, ширины px = ColumnWidth / 15.
 *
 * Вход: $rec (запись счёта: InUE, PaintIDPost), $all (показать скрытые столбцы — кнопка ShowHiddenBut).
 * Выход: ['cols' => столбцы для views/access/_grid, 'key' => ключ запоминания ширин].
 */
use app\components\AccessFormat as F;

$inUE = (int)($rec['InUE'] ?? 0) !== 0;
$cur = '#,##0.00 ?';
$paint = (int)($rec['PaintIDPost'] ?? 0);
$posCols = [
    ['№',            '№',            20,  ['al' => 'r']],
    ['Количество',   'Кол',          67,  ['al' => 'r']],
    ['KolValues',    'Ед.Изм',       64,  []],
];
if ($all) {   // скрытые столбцы, которые показывает кнопка ShowHiddenBut
    array_push($posCols,
        ['kolPost',      'Кол-во пост.',        96, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 2]],
        ['PostOKEItxt',  'ЕИ пост.',            72, []],
        ['kol2',         'Кол-во в уп.',        96, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 2]],
        ['PostOKEI2txt', 'ЕИ пост. в уп.',      96, []],
        ['PrcPost',      'Цена пост.',          96, ['al' => 'r', 'fmt' => $cur, 'dp' => 2]],
        ['ManualFlg',    'ManualFlg',           96, ['chk' => 1]],
        ['ManualKol',    'Количество Torex',    96, ['al' => 'r']],
        ['ManualNme',    'Точное наименование', 96, []],
        ['IncNDS',       'вкл. НДС',            67, ['chk' => 1]],
    );
}
array_push($posCols, ['ЦенаОптовая', 'ЦенаОптовая', 69, ['al' => 'r']]);
if ($inUE) {
    array_push($posCols,
        ['PrcUE', 'Цена, вал.', 72, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 4]],
        ['UE',    'Вал.',       44, []],
    );
} else {
    $posCols[] = ['Цена,руб.', 'Цена,руб.', 61, ['al' => 'r', 'fmt' => $cur, 'dp' => 2]];
}
$posCols[] = ['PrcIncludeNDS', 'Цена,руб.с НДС', 84, ['al' => 'r', 'fmt' => $cur, 'dp' => 2]];
$posCols[] = $inUE
    ? ['SumUE', 'Сумма, вал.', 94, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 4]]
    : ['Всего', 'Всего',       96, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 2]];
array_push($posCols,
    ['Информация',    'ID',            93,  ['al' => 'r']],
    ['UPKoef',        'Кратность',     67,  ['al' => 'r']],
    ['Заберите меня', 'Заберите меня', 27,  []],
    ['IsProblem',     'Проблемы',      27,  ['chk' => 1]],
    ['Поставка',      'Поставка',      145, ['al' => 'c']],
    ['DtaPost1',      'ДП',            67,  ['al' => 'c']],
    ['Недополучено',  'Недополучено',  49,  ['al' => 'r']],
    // условное форматирование: [ExistNakl]=1 → зелёный фон (по позиции уже есть накладная)
    ['Наименование',  'Наименование',  296, ['cf' => fn($r) => (int)($r['ExistNakl'] ?? 0) === 1 ? 'background:#a7da4e;' : '']],
    ['Элемент',       'Элемент',       75,  []],
    ['Серия',         'Серия',         127, []],
    ['Номинал',       'Номинал',       156, []],
    ['Корпус',        'Корпус',        195, []],
    ['Сумма НДС',     'Сумма НДС',     71,  ['al' => 'r', 'fmt' => $cur, 'dp' => 2]],
    ['Сумма без НДС', 'Сумма без НДС', 89,  ['al' => 'r', 'fmt' => $cur, 'dp' => 2]],
    ['Изготовитель',  'Изготовитель',  88,  []],
    ['NmeCat',        'Каталог',       75,  []],
    ['NDSVl',         'Ставка НДС',    96,  ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 0]],
    // fnPaintIDPost: пустой «ID Пост.» красится цветом PaintIDPost
    ['IDPostVl',      'ID Пост.',      94,  ['cf' => fn($r) => $paint > 0 && ($r['IDPostVl'] ?? '') === '' ? 'background:' . F::color($paint) . ';' : '']],
    ['PrcInvoice',    'Цена согл. КП', 96,  ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 4]],
    ['Trg',           'Таргет,$',      96,  ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 4]],
    ['MaxPrc',        'Максим. цена закуп.,$', 96, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 4]],
    ['ExistNakl',     'ExistNakl',     96,  []],
);
$posKey = 'schet.elem.pos' . ($all ? '.all' : '') . ($inUE ? '.ue' : '');
return ['cols' => $posCols, 'key' => $posKey];
