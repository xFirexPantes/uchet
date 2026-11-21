<?php
// Режимы формы frmfndHolding (OpenArgs: первый знак — Rgm, остальное — Args). FillList = exec dbo.xp_GetHoldingName 'st%', Rgm, Args;
// txtFirm_Change: пусто — весь список, 1–3 знака — ничего ('Empty%'), от 4 знаков — поиск. Выбор: Holding (litem.Key = "k" & _Code).
// Режим 0 (заведение фирмы с холдингом, CheckHolding / AddFirm) пока не перенесён.
return [
    // 1: из «фильтра по счетам» (frmfltSch.cboFirma_DblClick), Args = даты 'ггггммддггггммдд' — с экрана (extra). Ширина формы 4535 твипов.
    1 => ['title' => 'Выбор фирмы и холдинга', 'label' => 'Фирма:', 'proc' => 'dbo.xp_GetHoldingName', 'args' => ['1'], 'extra' => true,
          'minLen' => 4, 'cols' => [['Holding', 'Холдинг', 285]], 'key' => '_Code', 'width' => 302],
];
