<?php
/**
 * frmfndDocs, режим 3 «Письма». Раскладка из SaveAsText и Form_Open (Case 3): InsideWidth 6360 (424 px), InsideHeight 3000 (200);
 * шапка 277 (18) — поле поиска скрыто; подформа lstDoc 56,56 — 6300 × 2000 (420 × 133); внизу только ClsFrm (5782 — 385 px, 576 × 576).
 * Подформа subfrmFndDocs: NumTip «Номер» 2800 (187 px), HaveIdx «Дата» 2800 (187); Tip, vkMark, otkMark скрыты.
 * Остальные поля процедуры (если есть) — после них. Двойной щелчок по строке — в подформе subfrmFndDocs (её выгрузки нет).
 * @var yii\web\View $this
 * @var int $id
 * @var array $rows
 */
use yii\web\View;

$this->title = 'Письма';
echo $this->render('_dlg', ['id' => $id]);
$cols = [['NumTip', 'Номер', 187, []], ['HaveIdx', 'Дата', 187, ['fmt' => 'dd.mm.yyyy']]];
foreach ($rows ? array_keys($rows[0]) : [] as $f) {
    if (!in_array($f, ['NumTip', 'HaveIdx', 'Tip', 'vkMark', 'otkMark'], true)) $cols[] = [$f, $f, 96, []];
}
?>
<div class="access-form" id="frmfndDocs" style="width:424px;height:200px">
    <div class="dlg-sub" style="left:4px;top:22px;width:420px;height:133px">
        <?= $this->render('@app/views/access/_grid', ['tid' => 'lstDoc', 'key' => 'vhDlg.docs3', 'rows' => $rows, 'idField' => 'NumTip', 'cols' => $cols]) ?>
    </div>
    <div class="dlg-ft" style="top:162px;height:38px"></div>
    <button type="button" class="a-btn big" id="ClsFrm" title="Закрыть форму" style="left:385px;top:162px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    dlgRows(document.getElementById('lstDoc'));
    document.getElementById('ClsFrm').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
