<?php
/**
 * Режимы окна выбора frmfndFirm (OpenArgs → описание). Формат: см. components/Picker.php.
 * Добавляем режимы по мере переноса форм, которые их открывают.
 */
return [
    // 50: «Поиск заказа» для ФЗ-275 из карточки счёта (frmMassInputSch.flgFZ275_MouseUp)
    50 => [
        'title' => 'Поиск заказа', 'label' => 'Заказ',
        'proc' => 'dbo.xp_FZ275GetZakas', 'minLen' => 3,
        'cols' => [['Firma', 'Заказ', 270]], 'key' => 'UchetID',
        'width' => 295,     // ширина формы: 4425 твипов
    ],
    // 9: «Выбор покупателя» для «Долгов» во «Входящих счетах» (frmStat.GetPartSums_Click):
    // FillList Case 9 = exec dbo.xp_GetInnerFirms без параметров, поля поиска нет; выбор — UchetID (litem.Key = "k" & UchetID)
    9 => [
        'title' => 'Выбор покупателя', 'label' => '',
        'proc' => 'dbo.xp_GetInnerFirms', 'noFind' => true,
        'cols' => [['Firma', 'Фирма', 270]], 'key' => 'UchetID',
        'width' => 295,     // ширина формы: 4425 твипов
    ],
];
