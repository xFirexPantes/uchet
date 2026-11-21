<?php
/**
 * Общее для окон vh-dlg: стили и скрипты форм Access и dlgPost(act, data) — POST на vh-dlg/act?id=<счёт>.
 * @var yii\web\View $this
 * @var int $id  счёт (PredShcID), 0 — без счёта
 */
use yii\helpers\Json;
use yii\helpers\Url;

$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerJsFile('@web/js/colresize.js', ['position' => \yii\web\View::POS_HEAD]);
$this->registerJsFile('@web/js/access-dialog.js', ['position' => \yii\web\View::POS_HEAD]);
$this->registerJsFile('@web/js/picker.js', ['position' => \yii\web\View::POS_HEAD]);
$this->registerJsFile('@web/js/interval.js', ['position' => \yii\web\View::POS_HEAD]);
$act = Json::encode(Url::to(['vh-dlg/act', 'id' => (int)($id ?? 0)]));
$cp = Json::encode(Yii::$app->request->csrfParam);
$ct = Json::encode(Yii::$app->request->csrfToken);
$this->registerJs(<<<JS
window.dlgPost = function (act, data = {}) {
    const fd = new FormData(); fd.append('act', act);
    Object.entries(data).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => fd.append(k + '[]', x)) : fd.append(k, v ?? ''));
    fd.append($cp, $ct);
    return fetch($act, { method: 'POST', body: fd })
        .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })));
};
// раздвигание столбцов и выбор строки таблицы щелчком (как текущая запись подформы); первая выбрана сразу
window.dlgRows = function (t) {
    if (!t || !t.tBodies[0]) return;
    if (t.dataset.key && t.tHead) colResize(t, t.dataset.key);          // столбцы раздвигаются, ширины запоминаются
    t.tBodies[0].addEventListener('click', e => {
        const tr = e.target.closest('tr'); if (!tr) return;
        t.querySelectorAll('tr.sel').forEach(r => r.classList.remove('sel')); tr.classList.add('sel');
    });
    if (t.tBodies[0].rows[0]) t.tBodies[0].rows[0].classList.add('sel');
};
window.dlgSel = t => t && t.tBodies[0] && (t.tBodies[0].querySelector('tr.sel') || t.tBodies[0].rows[0]) || null;
JS, \yii\web\View::POS_HEAD);
?>
<style>
.dlg-sub { position:absolute; overflow:auto; background:#fff; border:1px inset #999; }
.dlg-ft { position:absolute; left:0; right:0; border-top:1px solid #aca899; }
.access-form .a-btn.big { font-size:16px; }
.dlg-ro { background:transparent !important; }
</style>
