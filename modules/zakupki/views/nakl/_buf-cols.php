<?php
/**
 * Столбцы subProdPlanSnd2Buf (таблица, ширины = ColumnWidth / 15). IDStr и AllID скрыты (Form_Open).
 * ЦенаОпт и Сумма — «Основной» (General Number): число как есть, DecimalPlaces Access при нём не действует.
 */
return [
    'tid' => 'gbuf', 'key' => 'buf.sub', 'idField' => 'IDStr', 'dataFields' => ['AllID', 'MnlKol', 'MnlSm'],
    'cols' => [
        ['Chk',         '',               32,  ['chk' => true, 'cls' => 'g-chk']],
        ['SostDet',     'Составляющая',    200, []],
        ['MainDet',     'Головная деталь', 200, []],
        ['NameProcess', 'Заказ',           50,  []],
        ['Sost',        'Состояние',       267, []],
        ['PrcOpt',      'ЦенаОпт,$',       75,  ['al' => 'r', 'fmt' => 'General Number']],
        ['MnlSm',       'Сумма, руб.',     75,  ['al' => 'r', 'fmt' => 'General Number', 'cls' => 'g-sm']],
        ['MnlKol',      'Кол',             53,  ['al' => 'r', 'cls' => 'g-kol']],
    ],
];
