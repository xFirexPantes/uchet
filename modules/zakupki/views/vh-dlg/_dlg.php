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
/* ---------- Корпоративный вид окон vh-dlg: голубой/белый, как современный экран счетов ----------
   Палитра та же, что в index-modern.php (--vh-blue …). Раскладка элементов по-прежнему берётся
   из координат SaveAsText (access-form.css), меняются только цвета, рамки и кнопки.
   Каркас окна держит access-dialog.js: закреплённая шапка + прокручиваемое тело. */
body { background:#fff !important; }   /* макет dialog задаёт бежевый фон inline — здесь он белый */
.access-form { background:#fff; font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d; }
body, .access-form { --vh-blue:#40B3D8; --vh-blue-d:#2f8baa; --vh-blue-l:#eaf7fc; --vh-blue-b:#c5e7f2; --vh-ink:#1b5c72; }
/* Подписи и рамки */
.access-form .a-lbl { color:var(--vh-ink); }
.access-form .a-frame { border:1px solid var(--vh-blue-b); border-radius:.35rem; }
/* Поля ввода */
.access-form .a-txt input, .access-form .a-txt textarea,
.access-form .a-select select { border:1px solid var(--vh-blue-b); border-radius:.3rem; background:#fff; padding:0 4px; }
.access-form .a-txt input:focus, .access-form .a-txt textarea:focus,
.access-form .a-select select:focus { outline:none; border-color:var(--vh-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
.access-form .a-txt.transp input, .access-form .a-txt.transp textarea { border-color:transparent; background:transparent; }
.access-form .a-combo span { background:var(--vh-blue-l); color:var(--vh-ink); border-left:1px solid var(--vh-blue-b); }
/* Кнопки: белые с голубой рамкой, при наведении — голубая заливка */
.access-form .a-btn { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.35rem; color:var(--vh-ink);
    font-weight:600; }
.access-form .a-btn:hover:not(:disabled) { background:var(--vh-blue); border-color:var(--vh-blue); color:#fff; }
.access-form .a-btn:active:not(:disabled), .access-form .a-btn.on { background:var(--vh-blue-d); border-color:var(--vh-blue-d); color:#fff; }
.access-form .a-btn:disabled { background:#f7fbfd; border-color:#e3f0f5; color:#9db9c4; }
.access-form .a-btn.big { font-size:16px; }
/* Списки и подформы */
.access-form .a-list, .access-form .a-slot { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.35rem; }
.dlg-sub { position:absolute; overflow:auto; background:#fff; border:1px solid var(--vh-blue-b); border-radius:.35rem; }
.dlg-ft { position:absolute; left:0; right:0; background:var(--vh-blue-l); border-top:1px solid var(--vh-blue-b); }
.dlg-ro { background:transparent !important; }
/* Таблицы подформ */
.access-form .a-grid th { background:var(--vh-blue-l); color:var(--vh-ink); font-weight:600; border-color:var(--vh-blue-b); }
.access-form .a-grid td { border-color:#e6f2f7; color:#20414d; }
.access-form .a-grid tr.sel td { background:var(--vh-blue) !important; color:#fff !important; }
.access-form .a-grid tr:hover:not(.sel) td { background:#f6fbfd; }
/* Флажки и переключатели — в цвете темы */
.access-form .a-opt { accent-color:var(--vh-blue); }
</style>
