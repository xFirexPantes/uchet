<?php
/**
 * Облегчённый поиск компонента (frmFndElem, OpenArgs = 1) — диалог из ＋ карточки счёта на элементы.
 * Раскладка: layout/frmFndElem.php. Как Form_Open при Rgm = 1: видны «Принадлежность» (по покупателю счёта,
 * OldLine = "--Post--" & TP), ✓ и ✕; группа «Давальческое» скрыта (IsDav = 2).
 * Поиск — по Enter в строке поиска или при смене принадлежности (fndel_AfterUpdate). ✓ — выбрать (AcceptElem_Click),
 * родителю уходит SkladID выбранной строки.
 *
 * Таблица — подформа subfrmReplacePtrnsEl (таблица, DefaultView = 2): столбцы в порядке TabIndex, ширины = ColumnWidth / 15,
 * заголовки — подписи полей. Скрыты (Form_Open): PriceOpt, PriceRoz, NoAnalog, EFind.
 *
 * @var yii\web\View $this
 * @var int   $id        счёт
 * @var int   $prin      принадлежность по умолчанию (TP счёта)
 * @var array $prinList  [[код, название]] из xp_GetWhatFirm11 1
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Поиск элемента';
$layout = require __DIR__ . '/layout/frmFndElem.php';
// Form_Open, Case Else (Rgm = 1): примечание 600 твипов, ✓ и ✕ видны высотой 576, ✓ на уровне ✕; Hlp01 не видна.
// Группа GrpDav скрыта — вместе с ней её переключатели и подписи; у скрытых PribOnly и Naim не видны их подписи.
$grp = ['Переключатель8', 'Переключатель10', 'Переключатель11', 'Надпись9', 'Надпись11', 'Надпись12', 'Надпись22', 'Надпись6', 'Hlp01'];
foreach ($layout as &$c) {
    if (in_array($c['n'], $grp, true)) $c['hidden'] = 1;
    if ($c['n'] === 'ClsFrm' || $c['n'] === 'AcceptElem') { unset($c['hidden']); $c['H'] = 38; $c['T'] = 273; }
    if ($c['n'] === 'Elems') unset($c['hidden']);
}
unset($c);
$w = 1096; $h = 313;   // 16446 твипов; заголовок 631 + область 3458 + примечание 600
$opts = '';
foreach ($prinList as [$k, $t]) {
    $opts .= '<option value="' . Html::encode($k) . '"' . ((int)$k === $prin ? ' selected' : '') . '>' . Html::encode($t) . '</option>';
}
$slots = [
    'fndel'      => '<input type="text" id="fndel" autocomplete="off" style="width:100%;height:100%;box-sizing:border-box;font-size:13px">',
    'lstPrin'    => '<select id="lstPrin" style="width:100%;height:100%;font-size:11px">' . $opts . '</select>',
    'Elems'      => '<div id="elemsBox" style="width:100%;height:100%;overflow:auto;background:#fff;display:none">'
                  . '<table class="a-grid" id="elems" data-key="schet.fndElem"><colgroup></colgroup><thead><tr></tr></thead><tbody></tbody></table></div>',
    'AcceptElem' => '<button type="button" class="a-btn" id="AcceptElem" title="Выбрать элемент">✓</button>',
    'ClsFrm'     => '<button type="button" class="a-btn" id="ClsFrm" title="Закрыть форму">✕</button>',
];
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerCssFile('@web/css/dlg-modern.css');          // корпоративные цвета, когда окно открыто поверх карточки счёта
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/access-dialog.js');
// dlg-modern ставится на <html> только если родитель — современный экран или карточка счёта
$this->registerJs(<<<'JS'
try {
    if (window.parent !== window && window.parent.document.querySelector('.vh-modern, .vh-card')) {
        document.documentElement.classList.add('dlg-modern');
    }
} catch (e) { /* другой сайт или нет доступа — обычный вид Access */ }
JS, View::POS_HEAD);
?>
<div class="access-form" id="frmFndElem" style="width:<?= $w ?>px;height:<?= $h ?>px">
<?= $this->render('@app/views/access/_form', [
    'layout' => $layout, 'rec' => [], 'slots' => $slots, 'note' => 'ещё не перенесено',
    'icons' => ['LoadFromXl' => ['XLS', 'Загрузить аналоги из Excel']],
]) ?>
</div>
<?php
$listUrl = Json::encode(Url::to(['fnd-elem-list', 'id' => $id]));
// [поле, заголовок, ширина px, флажок]
$cols = Json::encode([
    ['IsAnalog', '', 20, 0], ['SkladID', 'Код', 48, 0], ['Elem', 'Элемент', 100, 0], ['Ser', 'Серия', 132, 0],
    ['Naim', 'Наименование', 227, 0], ['Nom', 'Номинал', 103, 0], ['Krp', 'Корпус', 88, 0], ['Izg', 'Изготовитель', 108, 0],
    ['FullNmeIzg', 'Код производителя', 236, 0], ['FreeKol', 'Св. кол-во', 75, 0], ['WhereBy', 'ГдеКупил', 117, 0],
    ['IsDav', 'Дав', 25, 1], ['VP5', 'ВП5', 30, 1],
]);
$this->registerJs(<<<JS
(function () {
    const el = id => document.getElementById(id);
    const tbl = el('elems'), box = el('elemsBox');
    let rows = [], sel = -1;
    const cols = $cols;
    (function head() {
        const cg = tbl.querySelector('colgroup'), hr = tbl.tHead.rows[0];
        cols.forEach(([f, t, w]) => {
            const col = document.createElement('col'); col.style.width = w + 'px'; cg.appendChild(col);
            const th = document.createElement('th'); th.textContent = t; th.title = t; th.appendChild(Object.assign(document.createElement('span'), { className: 'rsz' })); hr.appendChild(th);
        });
        window.colResize && colResize(tbl, 'schet.fndElem');
    })();
    const on = x => x === true || x === 1 || x === '1' || x === 'True' || x === 'Да' || x === '-1';
    function draw() {
        tbl.tBodies[0].innerHTML = '';
        if (!rows.length) { box.style.display = 'none'; sel = -1; return; }   // Elems.Visible = RecordCount > 0
        rows.forEach((r, i) => {
            const tr = tbl.tBodies[0].insertRow(); tr.dataset.i = i;
            cols.forEach(([f, , , chk]) => {
                const td = tr.insertCell();
                if (chk) { td.style.textAlign = 'center'; td.innerHTML = '<input type="checkbox" disabled' + (on(r[f]) ? ' checked' : '') + '>'; }
                else { td.textContent = r[f] ?? ''; td.title = r[f] ?? ''; }
            });
        });
        box.style.display = '';
        pick(0);
    }
    function pick(i) { sel = i; [...tbl.tBodies[0].rows].forEach((tr, k) => tr.classList.toggle('sel', k === i)); tbl.tBodies[0].rows[i]?.scrollIntoView({ block: 'nearest' }); }
    function find() {
        const q = new URLSearchParams({ q: el('fndel').value, prin: el('lstPrin').value });
        fetch($listUrl + '&' + q).then(r => r.json()).then(d => { rows = Array.isArray(d) ? d : []; draw(); })
            .catch(() => msgBox('Не удалось выполнить поиск', { icon: 'error' }));
    }
    function accept() {                                                    // AcceptElem_Click
        if (sel < 0 || !rows[sel]) return;                                 // нет строк — ничего не делать
        closeAccessDialog(false, rows[sel].SkladID);
    }
    el('fndel').addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); find(); }
        else if (e.key === 'ArrowDown' && sel < rows.length - 1) { e.preventDefault(); pick(sel + 1); }
        else if (e.key === 'ArrowUp' && sel > 0) { e.preventDefault(); pick(sel - 1); }
    });
    el('fndel').addEventListener('change', find);
    el('lstPrin').addEventListener('change', find);                      // lstPrin_AfterUpdate
    tbl.tBodies[0].addEventListener('click', e => { const tr = e.target.closest('tr'); if (tr) pick(+tr.dataset.i); });
    // двойной щелчок в Access открывает карточку элемента (Hlp02 «Dblck-карточка») — карточка ещё не перенесена
    el('AcceptElem').addEventListener('click', accept);
    el('ClsFrm').addEventListener('click', () => closeAccessDialog(false));
    el('fndel').focus();
})();
JS, View::POS_END);
