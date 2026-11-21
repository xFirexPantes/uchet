<?php
/**
 * Подформа-таблица, у которой нет выгрузки SaveAsText: столбцы = поля процедуры (как заголовки по умолчанию в Access).
 * Ширина по длине данных (60…300 px), даты и числа форматируются как в Access (AccessFormat). Столбцы раздвигаются мышью.
 * @var string $tid  id таблицы
 * @var string $key  ключ для запоминания ширин
 * @var array  $rows
 * @var array  $hide поля, которых не видно (ColumnHidden)
 * @var array  $caps подписи вместо имён полей
 * @var string|null $idField
 * @var array  $dataFields
 */
use app\components\AccessFormat as F;

$hide ??= []; $caps ??= []; $idField ??= null; $dataFields ??= [];
$cols = [];
foreach ($rows ? array_keys($rows[0]) : [] as $f) {
    if (in_array($f, $hide, true)) continue;
    $len = mb_strlen($caps[$f] ?? $f);
    foreach (array_slice($rows, 0, 50) as $r) $len = max($len, mb_strlen(F::value($r[$f] ?? null)));
    $num = $rows && is_numeric($rows[0][$f] ?? null) && !preg_match('/^0\d/', (string)$rows[0][$f]);
    $cols[] = [$f, $caps[$f] ?? $f, max(60, min(300, $len * 7 + 10)), $num ? ['al' => 'r'] : []];
}
echo $cols
    ? $this->render('@app/views/access/_grid', ['tid' => $tid, 'key' => $key, 'cols' => $cols, 'rows' => $rows, 'idField' => $idField, 'dataFields' => $dataFields])
    : '<table id="' . htmlspecialchars($tid) . '" class="a-grid"><tbody></tbody></table><div style="padding:6px;color:#666">Нет записей</div>';
