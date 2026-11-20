<?php
/**
 * Столбцы окна ГТД (frmLoadKompel): позиции счёта (lstSch = SubFrmMassInputSch, как в карточке) и позиции в файле
 * (lstNakl = subfrmLoadKompel, xp_LoadNaklKompelGetRSNakl). Порядок subfrmLoadKompel — по TabIndex, ширины = ColumnWidth / 15;
 * SkladID, IDStr, IDSchEl, OverLoad скрыты (Form_Open).
 * Условное форматирование «Маркировки»: [OverLoad]=1 — жёлтый, [IDSchEl]>0 — зелёный (сопоставлена); «Применение»: [IDSchEl]>0 — зелёный.
 *
 * Вход: $d['cardRec'] — запись счёта (InUE, PaintIDPost). Выход: ['sch' => параметры _grid, 'file' => параметры _grid].
 */
$green = fn($r) => (int)($r['IDSchEl'] ?? 0) > 0 ? 'background:#ccffcc;' : '';
$fileCols = [
    ['Kol',         'В накл',       60,  ['al' => 'r', 'cls' => 'g-kol']],
    ['PrcOpt',      'Опт.цена',     51,  ['al' => 'r']],
    ['Naim',        'Маркировка',   320, ['cf' => fn($r) => (int)($r['OverLoad'] ?? 0) === 1 ? 'background:#ffff00;' : $green($r)]],
    ['Primen',      'Применение',   123, ['cf' => $green]],
    ['OrigCountry', 'Страна',       103, []],
    ['GTD',         'ГТД',          200, []],
    ['Nme',         'Наименование', 118, []],
    ['Nom',         'Номинал',      106, []],
    ['Krp',         'Корпус',       118, []],
    ['Izg',         'Изготовитель', 105, []],
    ['Prc',         'Цена',         91,  ['al' => 'r']],
    ['Sm',          'Сумма',        112, ['al' => 'r']],
];
['cols' => $schCols, 'key' => $schKey] = (function () use ($d) { $rec = $d['cardRec']; $all = false; return require __DIR__ . '/../schet/_pos-cols.php'; })();
return [
    'sch'  => ['tid' => 'gsch', 'key' => 'gtd.' . $schKey, 'cols' => $schCols, 'idField' => 'AllID', 'dataFields' => ['Наименование']],
    'file' => ['tid' => 'gfile', 'key' => 'gtd.file', 'cols' => $fileCols, 'idField' => 'IDStr', 'dataFields' => ['IDSchEl', 'Naim']],
];
