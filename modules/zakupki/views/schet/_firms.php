<?php
/**
 * xListINN (ActiveX ListView): фирмы для выбора ИНН, FillListINN → xp_GetFirms4Sch.
 * В Access список неактивен (Enabled = Нет), пока не начали поиск в поле Fnd.
 * @var array $rows
 * @var string $key
 */
$cols = [
    ['Naim', 'Наименование', 180, []],
    ['INN',  'ИНН',          90,  []],
];
echo $this->render('@app/views/access/_grid', ['tid' => 'firms', 'key' => $key, 'cols' => $cols, 'rows' => $rows, 'idField' => 'ID']);
