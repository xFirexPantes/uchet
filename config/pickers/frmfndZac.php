<?php
// Режимы формы frmfndZac (OpenArgs). Источник: Form_Open, FillListZac, AccptZac_Click в VBA формы.
// Правка справочника причин (кнопки добавить/изменить/удалить в Access) пока не перенесена.
return [
    // ТЗ 522: причина удаления позиции из счёта (DelRec в frmMassInputSch). OldLine = UchetID выбранной строки.
    19 => ['title' => 'Выбор причины', 'label' => 'Причина', 'proc' => 'dbo.xp_ChgPredShcetaReasons', 'args' => [0], 'minLen' => 3,
           'cols' => [['Firma', 'Причина', 420]], 'key' => 'UchetID', 'width' => 436],
];
