<?php
// Режимы формы frmfndZac (OpenArgs). Источник: Form_Open, FillListZac, AccptZac_Click в VBA формы.
// Правка справочника причин (кнопки добавить/изменить/удалить в Access) пока не перенесена.
return [
    // ТЗ 522: причина удаления позиции из счёта (DelRec в frmMassInputSch). OldLine = UchetID выбранной строки.
    19 => ['title' => 'Выбор причины', 'label' => 'Причина', 'proc' => 'dbo.xp_ChgPredShcetaReasons', 'args' => [0], 'minLen' => 3,
           'cols' => [['Firma', 'Причина', 420]], 'key' => 'UchetID', 'width' => 436],
    // История изменений счетов (frmChgPredShcetaHistory), фильтры шапки. Form_Open 15: поиска нет (fnd скрыт), FillListZac(15, '%')
    // = exec dbo.xp_Zac3 '%'; выбор — UchetID (Case Else: KodNullEl = UchetID, OldLine = Firma). Ширина формы 4545 твипов.
    15 => ['title' => 'Выбор', 'label' => 'Заказчик', 'proc' => 'dbo.xp_Zac3', 'noFind' => true, 'args' => ['%'],
           'cols' => [['Firma', 'Продавец', 285]], 'key' => 'UchetID', 'width' => 303],
    // 20 — «Выбор причины» (xp_ChgPredShcetaReasons 'st%',1), 21 — «Выбор события» ('st%',2). Возврат: KodNullEl = UchetID & ",".
    // Замена/удаление причин (кнопки справочника) пока не перенесены.
    20 => ['title' => 'Выбор причины', 'label' => 'Причина', 'proc' => 'dbo.xp_ChgPredShcetaReasons', 'args' => [1], 'minLen' => 3,
           'cols' => [['Firma', 'Причина', 387]], 'key' => 'UchetID', 'width' => 436],
    21 => ['title' => 'Выбор события', 'label' => 'Событие', 'proc' => 'dbo.xp_ChgPredShcetaReasons', 'args' => [2], 'minLen' => 3,
           'cols' => [['Firma', 'Событие', 387]], 'key' => 'UchetID', 'width' => 436],
];
