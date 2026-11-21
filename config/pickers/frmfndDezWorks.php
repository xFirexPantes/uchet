<?php
/**
 * Режимы окна выбора frmfndDezWorks (OpenArgs → описание). Формат: см. components/Picker.php.
 * Где смотреть в VBA: Form_Open (Case N), SetRow4FndDezWorks (процедура по строке поиска), Accpt_Click (что возвращается).
 */
return [
    // 61: «Валюта» — из карточки счёта (frmMassInputSch.InUEtxt_DblClick). RowSource «exec dbo.xp_GetUE 0», поиска нет,
    // ColumnWidths "0;3000", заголовков нет, BoundColumn 1 → OldLine = код (столбец 0). Форма 4900 твипов (MoveSize).
    61 => [
        'title' => 'Валюта', 'label' => '',
        'proc' => 'dbo.xp_GetUE', 'args' => [0], 'noFind' => true, 'noHead' => true,
        'cols' => [[1, '', 200]], 'key' => 0,
        'width' => 327,
    ],
];
