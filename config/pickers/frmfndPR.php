<?php
/**
 * Режимы окна выбора frmfndPR (OpenArgs → описание). Формат: см. components/Picker.php.
 * В Access у формы около 200 режимов; переносим по мере надобности.
 * Где смотреть в VBA frmfndPR: Form_Open (Case N — заголовок, столбцы), ChgFnd (процедура),
 * FillNmes (как строится строка поиска), ChoiceRab_Click (что возвращается в OldLine / KodNullEl).
 */
return [
    // 49: «Каталог» — из frmPosInput.NmeCat_DblClick; в NmeCat пишется OldLine = lstNmes.Value (BoundColumn 2).
    // Список без заголовков, столбцы 2800;4000 твипов. FillNmes Case Else: 'st%'.
    // fnd_AfterUpdate: если ничего не найдено — «Указанного каталога нет в списке. Всё равно использовать его?»
    49 => [
        'title' => 'Каталог', 'label' => 'Строка поиска',
        'proc' => 'dbo.xp_InvoiceGetAllCat4Choice',
        'cols' => [[0, '', 187], [1, '', 267]], 'key' => 1, 'noHead' => true,
        'free' => 'Указанного каталога нет в списке. Всё равно использовать его?',
        'width' => 556,     // ширина формы: 8333 твипа
    ],
];
