<?php
/**
 * frmShowLogSklad в режиме DtaPost «Изменение даты поставки счёта». Раскладка из SaveAsText: 333 × 242 px.
 * Список lstH 4,4 — 329 × 193 (xp_frmMassInputSchDtaLog AllID, вариант), столбцы 113, 49, 144; внизу вариант lstVars
 * (xp_frmMassInputSchDtaLogVars: ID, текст, заголовок окна). После смены варианта заголовок — из его третьего столбца.
 * @var yii\web\View $this
 * @var int $allId
 * @var int $var
 * @var bool $changed
 * @var array $vars
 * @var array $rows
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$cap = 'Изменение даты поставки счёта';
if ($changed) foreach ($vars as $v) if ($v['id'] === $var && $v['cap'] !== '') $cap = $v['cap'];
$this->title = $cap;
echo $this->render('_dlg', ['id' => 0]);
$w = [113, 49, 144];
$fields = $rows ? array_keys($rows[0]) : [];
$url = Json::encode(Url::to(['show-log', 'allId' => $allId]));
?>
<style>#frmShowLogSklad select { font:12px Tahoma; width:100%; }</style>
<div class="access-form" id="frmShowLogSklad" style="width:333px;height:242px">
    <div class="dlg-sub" style="left:4px;top:4px;width:329px;height:193px">
        <table class="a-grid" id="lstH" data-key="vhDlg.showLog">
            <thead><tr><?php foreach ($fields as $i => $f): ?><th style="width:<?= $w[$i] ?? 100 ?>px"><?= Html::encode($f) ?></th><?php endforeach ?></tr></thead>
            <tbody><?php foreach ($rows as $r): ?><tr><?php foreach ($r as $v): ?><td><?= Html::encode((string)$v) ?></td><?php endforeach ?></tr><?php endforeach ?></tbody>
        </table>
    </div>
    <div class="dlg-ft" style="top:200px;height:42px"></div>
    <div class="a-select" style="left:0;top:204px;width:75px;height:19px">
        <select id="lstVars" title="Интервал"><?php foreach ($vars as $v): ?><option value="<?= $v['id'] ?>" <?= $v['id'] === $var ? 'selected' : '' ?>><?= Html::encode($v['text']) ?></option><?php endforeach ?></select>
    </div>
    <div class="a-lbl" id="Hlp" style="left:76px;top:204px;width:215px;height:34px"></div>
    <button type="button" class="a-btn big" id="ExitBut" title="Закрыть форму" style="left:295px;top:204px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    dlgRows(document.getElementById('lstH'));
    // lstVars_AfterUpdate: перечитать список и заголовок под выбранный вариант
    document.getElementById('lstVars').addEventListener('change', e => {
        location.href = $url + '&' + new URLSearchParams({ var: e.target.value, v: 1 });
    });
    document.getElementById('ExitBut').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
