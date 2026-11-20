<?php
/**
 * Список накладных lstNakl (ListBox, ColumnHeads = Да, 6 столбцов, ColumnWidths 0;1701;1701;1701;1701;1701).
 * Заголовки = имена полей процедуры, первый столбец (код накладной) скрыт.
 * @var array $rows
 * @var string $key
 */
$names = $rows ? array_keys($rows[0]) : [];
$cols = [];
foreach (array_slice($names, 1, 5) as $f) {
    $cols[] = [$f, $f, 113, []];
}
echo $this->render('@app/views/access/_grid', ['tid' => 'nakl', 'key' => $key, 'cols' => $cols, 'rows' => $rows,
    'idField' => $names[0] ?? null]);
