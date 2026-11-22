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

    // 155: «Выбор резерва счёта» — из frmStat.SelResKompel (после загрузки «Сроков КОМПЭЛ»).
    // Form_Open Case 155: ColumnCount 3, BoundColumn 0, ColumnHeads Нет, ColumnWidths "3500;3500;1000",
    // ChoiceRab.Visible = True, fnd.Visible = False; FillNmes → exec dbo.xp_ChoiceRes4KompelSrok <ID строки tmptblKOMPELSrokNew>.
    // ChoiceRab_Click / lstNmes_DblClick: KodNullEl = lstNmes.Column(0) → SetReserv(ID, IDRes).
    155 => [
        'title' => 'Выбор резерва счёта',
        'proc' => 'dbo.xp_ChoiceRes4KompelSrok',
        'cols' => [[0, '', 233], [1, '', 233], [2, '', 67]], 'key' => 0, 'noHead' => true,
        'noFind' => true,   // поле поиска скрыто, список сразу полный
        'extra' => true,    // ID строки tmptblKOMPELSrokNew приходит с экрана
        'width' => 560,     // 8000 твипов столбцов + рамка
    ],
];
