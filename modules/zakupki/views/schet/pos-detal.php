<?php
/**
 * «Позиция счёта для деталей» (frmScetaDetalAdd) — диалог поверх карточки счёта на детали.
 * Раскладка: layout/frmScetaDetalAdd.php. Открывается в iframe (web/js/access-dialog.js).
 *
 * @var yii\web\View $this
 * @var int $id     счёт (отрицательный)
 * @var int $idPos  позиция (0 — новая)
 * @var array $row  временная запись из xp_ProdPlanUpdPosSch
 */
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Позиция счёта для деталей';
$layout = require __DIR__ . '/layout/frmScetaDetalAdd.php';
// Form_Open: подпись цены зависит от «Цена с НДС» счёта
foreach ($layout as &$c) {
    if ($c['n'] === 'Prc_Надпись') $c['c'] = (int)($row['PrcWNDS'] ?? 0) === 0 ? 'Цена без НДС' : 'Цена';
    if ($c['n'] === 'Nme') $c['tip'] = 'Двойной щелчок — выбрать из списка';
}
unset($c);
$w = 0; $h = 0;
foreach ($layout as $c) {
    if (empty($c['hidden'])) { $w = max($w, $c['L'] + $c['W']); $h = max($h, $c['T'] + $c['H']); }
}
$edit = array_fill_keys(['FullNme'], 'text') + array_fill_keys(['Kol', 'Prc', 'NDS', 'Sm', 'SmNDS', 'Vsego'], 'num');
$slots = [
    'AccptRec' => '<button type="button" class="a-btn" id="AccptRec" title="Сохранить позицию">✓</button>',
    'ClsFrm'   => '<button type="button" class="a-btn" id="ClsFrm" title="Закрыть без сохранения">✕</button>',
];
$this->registerCssFile('@web/css/access-form.css');
$this->registerJsFile('@web/js/access-dialog.js');
?>
<div class="access-form" id="frmScetaDetalAdd" style="width:<?= $w + 4 ?>px;height:<?= $h + 4 ?>px">
<?= $this->render('@app/views/access/_form', [
    'layout' => $layout, 'rec' => $row, 'slots' => $slots, 'edit' => $edit,
    'note' => 'ещё не перенесено',
]) ?>
</div>
<?php
$saveUrl = Json::encode(Url::to(['pos-detal-save', 'id' => $id]));
$accUrl  = Json::encode(Url::to(['pos-detal-accept', 'id' => $id, 'pos' => $idPos]));
$nomUrl  = Json::encode(Url::to(['pos-detal-nomenkl', 'id' => $id]));
$fobUrl  = Json::encode(Url::to(['fobnov/view', 'id' => $id]));
$this->registerJs(<<<JS
(function () {
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    let queue = Promise.resolve();
    function post(url, data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
        if (csrfParam) fd.append(csrfParam, csrfToken);
        const p = queue.then(() => fetch(url, { method: 'POST', body: fd }))
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })));
        queue = p.catch(() => {});
        return p;
    }
    // Поле сохраняется сразу после изменения (AfterUpdate); Kol и Prc пересчитывают суммы и переводят курсор дальше
    const next = { Kol: 'Prc', Prc: 'Sm' };
    document.querySelectorAll('.ed').forEach(el => {
        el.dataset.old = el.value;
        el.addEventListener('keydown', e => { if (e.key === 'Enter') el.blur(); });
        el.addEventListener('change', () => {
            el.classList.add('saving'); el.classList.remove('err');
            post($saveUrl, { field: el.dataset.field, value: el.value }).then(async res => {
                el.classList.remove('saving');
                if (res.set) Object.entries(res.set).forEach(([f, v]) => {
                    const t = document.querySelector('.ed[data-field="' + f + '"]'); if (t) { t.value = v; t.dataset.old = v; }
                });
                if (res.ok) {
                    el.dataset.old = el.value;
                    if (res.set && next[el.dataset.field]) document.querySelector('.ed[data-field="' + next[el.dataset.field] + '"]')?.focus();
                } else { el.value = el.dataset.old; el.classList.add('err'); }
                if (res.msg) await msgBox(res.msg);
            });
        });
    });
    document.getElementById('AccptRec').addEventListener('click', () => {
        document.activeElement?.blur();                       // сначала сохранить поле, где стоит курсор
        post($accUrl, {}).then(async res => {
            if (res.msg) await msgBox(res.msg);
            if (res.close) closeAccessDialog(true);
        });
    });
    // Nme_DblClick: окно fObnov (оно больше этого окна — открывается в главном), выбранный элемент → xp_ProdPlanUpdPosNomenkl, поля заново
    const nme = document.getElementById('Nme');
    if (nme) { nme.title = 'Двойной щелчок — выбрать из списка'; nme.style.cursor = 'pointer'; }
    nme?.addEventListener('dblclick', async () => {
        let dlg = accessDialog;
        try { if (window.top !== window && window.top.accessDialog) dlg = window.top.accessDialog; } catch (e) {}
        const f = await dlg($fobUrl, 1336, 505);
        if (!f.value) return;
        const res = await post($nomUrl, { sklad: f.value });
        if (res.set) Object.entries(res.set).forEach(([k, v]) => {
            const t = document.querySelector('.ed[data-field="' + k + '"]') || document.getElementById(k);
            if (t) { t.value = v; t.dataset.old = v; t.title = v; }
        });
        if (res.msg) await msgBox(res.msg);
    });
    document.getElementById('ClsFrm').addEventListener('click', () => closeAccessDialog(false));
    document.querySelector('.ed[data-field=FullNme]')?.focus();
})();
JS, View::POS_END);
