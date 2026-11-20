<?php
/**
 * Подформа договоров lstDogs (subfrmFObnovlen1_01), та же, что во «Входящих счетах».
 * Активный договор (IsActive) подсвечивается голубым в столбце «Дата дог».
 * @var array $rows
 */
$cols = [
    ['NumDog',   '№ дог',              90,  []],
    ['DataDog',  'Дата дог',           59,  ['cf' => fn($r) => (int)($r['IsActive'] ?? 0) ? 'background:#99ccff;' : '']],
    ['SrokWar',  'Срок гарантии,мес.', 82,  []],
    ['Org',      'Организация',        164, []],
    ['DescrDog', 'Описание',           110, []],
];
echo $this->render('@app/views/access/_grid', ['tid' => 'dogs', 'key' => 'schet.dogs', 'cols' => $cols, 'rows' => $rows]);
