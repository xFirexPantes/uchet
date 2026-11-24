<?php
/**
 * frmFndPR, режим 11 — «Список элементов, требующих внимания». Открывается из ＋ оплат (AddOpl), когда у счёта ещё
 * нет оплат, а xp_WhoBuySch вернула строки. Только просмотр: ✓ (ChoiceRab) — продолжить оплату, ✕ (CloseFrm) — отказаться.
 * Размеры из SaveAsText и Form_Open (11): ширина 13200 твипов = 880 px; заголовок 48, область данных 310, примечание 87 px;
 * lstNmes 8 столбцов, ColumnWidths 1800;1800;1800;2800;1200;1200;1200;1200, ColumnHeads = True (заголовки — имена полей).
 *
 * @var yii\web\View $this
 * @var array $rows  xp_WhoBuySch
 */
use yii\helpers\Html;
use yii\web\View;

$this->title = 'Список элементов, требующих внимания';
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerCssFile('@web/css/dlg-modern.css');          // корпоративные цвета, когда окно открыто поверх современного вида
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/access-dialog.js');
// dlg-modern ставится на <html> только если родитель — современный экран (.vh-modern);
// скрипт в head: класс готов до отрисовки, окно не мигает бежевым
$this->registerJs(<<<'JS'
try {
    if (window.parent !== window && window.parent.document.querySelector('.vh-modern, .vh-card')) {
        document.documentElement.classList.add('dlg-modern');
    }
} catch (e) { /* другой сайт или нет доступа — обычный вид Access */ }
JS, \yii\web\View::POS_HEAD);
$widths = [120, 120, 120, 187, 80, 80, 80, 80];
$cols = $rows ? array_slice(array_keys($rows[0]), 0, 8) : [];
?>
<style>
#frmFndPR .lst{position:absolute;left:4px;top:48px;width:877px;height:295px;overflow:auto;background:#fff;border:1px inset #999}
#frmFndPR table{border-collapse:collapse;table-layout:fixed;font:12px Tahoma,sans-serif}
#frmFndPR th{background:#ece9d8;border:1px solid #aca899;font-weight:normal;text-align:left;padding:1px 3px;position:sticky;top:0}
#frmFndPR td{border-bottom:1px solid #eee;padding:1px 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#frmFndPR tr.sel td{background:#316ac5;color:#fff}
#frmFndPR .ft{position:absolute;left:0;top:358px;width:880px;height:87px;border-top:1px solid #aca899}
</style>
<div class="access-form" id="frmFndPR" style="width:880px;height:445px">
    <div class="lst">
        <table id="lstNmes">
            <colgroup><?php foreach ($cols as $i => $c): ?><col style="width:<?= $widths[$i] ?>px"><?php endforeach ?></colgroup>
            <thead><tr><?php foreach ($cols as $c): ?><th><?= Html::encode($c) ?></th><?php endforeach ?></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr><?php foreach ($cols as $c): ?><td title="<?= Html::encode((string)$r[$c]) ?>"><?= Html::encode((string)$r[$c]) ?></td><?php endforeach ?></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="ft"></div>
    <button type="button" class="a-btn" id="ChoiceRab" title="Выбрать" style="left:2px;top:404px;width:38px;height:38px;font-size:16px">✓</button>
    <button type="button" class="a-btn" id="CloseFrm" title="Закрыть форму" style="left:833px;top:404px;width:38px;height:38px;font-size:16px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const t = document.getElementById('lstNmes');
    colResize(t, 'vhSchet.whoBuy');
    t.tBodies[0].addEventListener('click', e => {
        const tr = e.target.closest('tr'); if (!tr) return;
        t.querySelectorAll('tr.sel').forEach(r => r.classList.remove('sel')); tr.classList.add('sel');
    });
    if (t.tBodies[0].rows[0]) t.tBodies[0].rows[0].classList.add('sel');
    document.getElementById('ChoiceRab').addEventListener('click', () => closeAccessDialog(true, -1));   // frmStat!Prc = -1 — продолжить
    document.getElementById('CloseFrm').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
