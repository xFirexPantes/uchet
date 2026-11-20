<?php
/**
 * fObnov — полный поиск компонента («Склад»), диалог. DoCmd.MoveSize 20100 × 7950 твипов → окно 1336 × 505 px, координаты = твипы / 15.
 * Слева дерево xTree (Склад → NSh → Элемент → Серия → Наименование → Номинал → Корпус → Изготовитель), справа список xList.
 * Внизу: поиск (FindElem, от 3 символов через секунду; со «*» — по Enter), поиск по маркировке, «Максимум строк» / «Вывести все»,
 * ID выбранного элемента, ✓ ButEl, ✕ Кнопка8, группы TypeSel, IsShow, grpCompany, флажки фильтра, «Принадлежность».
 * У счёта на детали ($det) часть элементов скрыта, TypeSel = 5 (как в Form_Open при открытой frmScetaDetalAdd).
 *
 * @var yii\web\View $this
 * @var int   $id        счёт (> 0 на элементы, < 0 на детали)
 * @var bool  $det
 * @var int   $prin      lstPrin = TP.Column(0)
 * @var array $prinList  [[код, название]]
 * @var int   $maxItems  xp_ListViewMaxItemsGet
 */
use app\modules\zakupki\services\FObnovService;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Склад';
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/access-dialog.js');
$cols = FObnovService::COLS;
$pos = fn($l, $t, $w, $h) => "left:{$l}px;top:{$t}px;width:{$w}px;height:{$h}px";
$lbl = fn($l, $t, $w, $txt, $id = '') => '<div class="a-lbl"' . ($id ? " id=\"$id\"" : '') . " style=\"left:{$l}px;top:{$t}px;width:{$w}px\">" . Html::encode($txt) . '</div>';
// TypeSel: значение, подпись, left/top кнопки; 7 «Реализуемые заказчиком» скрыт при открытой frmMassInputSch, 4 «Склады поставщиков» не перенесён
$typeSel = [[1, 'НЕ давальческое сырьё', 384], [2, 'Давальческое сырьё', 404], [6, 'Закупаемые у заказчика', 424], [3, 'Не важно', 464], [4, 'Склады поставщиков', 484]];
if ($det) $typeSel = [];
?>
<style>
#fObnov input[type=radio], #fObnov input[type=checkbox]{margin:0;position:absolute}
#xTree{background:#fff;border:1px inset #999;overflow:auto;font:12px Arial,sans-serif;padding:2px 0}
#xTree ul{list-style:none;margin:0;padding-left:14px}
#xTree > ul{padding-left:2px}
#xTree .nd{white-space:nowrap;cursor:default;line-height:17px}
#xTree .tg{display:inline-block;width:12px;text-align:center;color:#555;cursor:pointer;font-size:9px}
#xTree .tx{padding:0 2px}
#xTree .nd.sel > .tx{background:#316ac5;color:#fff}
#boxList{background:#fff;border:1px inset #999;overflow:auto}
#xList th{cursor:pointer}
#xList th .ar{font-size:8px;color:#555}
#xList td.blue{color:#0000ff}
#fObnov .a-txt input{font:11px Tahoma,sans-serif}
#cnt input{background:#808080;color:#fff;text-align:right}
</style>
<div class="access-form" id="fObnov" style="width:1336px;height:505px">
    <div id="xTree" style="<?= $pos(8, 4, 257, 351) ?>"><ul></ul></div>
    <div id="boxList" style="<?= $pos(268, 4, 1059, 352) ?>">
        <table id="xList" class="a-grid" data-key="fobnov.list">
            <colgroup><?php foreach ($cols as $c): ?><col style="width:<?= (int)$c[2] ?>px"><?php endforeach ?></colgroup>
            <thead><tr><?php foreach ($cols as $i => $c): ?><th data-i="<?= $i ?>" title="<?= Html::encode($c[1]) ?>"><?= Html::encode($c[1]) ?> <span class="ar"></span><span class="rsz"></span></th><?php endforeach ?></tr></thead>
            <tbody></tbody>
        </table>
    </div>

    <?= $lbl(366, 359, 35, 'Поиск') ?>
    <div class="a-txt edit" style="<?= $pos(405, 359, 113, 17) ?>"><input type="text" id="FindElem" autocomplete="off"></div>
    <?= $lbl(605, 359, 113, 'Поиск по маркировке') ?>
    <div class="a-txt edit" style="<?= $pos(718, 359, 150, 17) ?>"><input type="text" id="fndMark" autocomplete="off"></div>
    <div id="MaxItemsLabel" class="a-lbl" style="left:933px;top:356px;width:86px">Максимум строк</div>
    <div class="a-txt edit" id="boxMax" style="<?= $pos(1020, 356, 48, 17) ?>"><input type="text" id="MaxItems" value="<?= (int)$maxItems ?>"></div>
    <?= $lbl(1082, 356, 68, 'Вывести все') ?>
    <input type="checkbox" id="FlgShowAll" style="left:1150px;top:358px">
    <div class="a-txt" id="cnt" style="<?= $pos(1183, 355, 52, 17) ?>"><input type="text" readonly></div>

    <?= $lbl(4, 384, 19, 'ID') ?>
    <div class="a-txt" style="<?= $pos(26, 386, 69, 17) ?>"><input type="text" id="SkladID" readonly></div>
    <button type="button" class="a-btn" id="ShowPhoto" disabled title="Фото элемента: ещё не перенесено" style="<?= $pos(155, 384, 39, 38) ?>">Фото</button>
    <button type="button" class="a-btn" id="ButEl" disabled title="Выбрать элемент" style="<?= $pos(250, 384, 38, 38) ?>;font-size:16px">✓</button>
    <button type="button" class="a-btn" id="Kn8" title="Закрыть" style="<?= $pos(297, 383, 38, 38) ?>;font-size:16px">✕</button>
    <?php if ($det): ?>
    <button type="button" class="a-btn" id="MakeSostDet" disabled title="Составляющие детали: ещё не перенесено" style="<?= $pos(340, 384, 38, 38) ?>">Сост</button>
    <?php else: ?>
    <button type="button" class="a-btn" id="btnCopyFullNmeIzg" title="Скопировать полное наименование" style="<?= $pos(340, 384, 38, 38) ?>;visibility:hidden;font-size:16px">📋</button>
    <?php endif ?>
    <button type="button" class="a-btn" id="btnBuf" title="Скопировать наименование" style="<?= $pos(761, 444, 29, 29) ?>">⧉</button>

    <?php if ($typeSel): ?>
    <div class="a-frame" style="<?= $pos(790, 380, 181, 120) ?>"></div>
    <?php foreach ($typeSel as [$v, $t, $top]): ?>
        <input type="radio" name="TypeSel" value="<?= $v ?>" <?= $v === 1 ? 'checked' : '' ?> <?= $v === 4 ? 'disabled' : '' ?> style="left:794px;top:<?= $top ?>px" title="<?= $v === 4 ? 'Склады поставщиков: ещё не перенесено' : '' ?>">
        <?= $lbl(809, $top - 2, 159, $t) ?>
    <?php endforeach ?>
    <div class="a-frame" style="<?= $pos(1060, 384, 160, 72) ?>"></div>
    <input type="radio" name="IsShow" value="0" checked style="left:1061px;top:391px"><?= $lbl(1076, 389, 139, 'Показываемые эл-ты') ?>
    <input type="radio" name="IsShow" value="1" style="left:1061px;top:421px"><?= $lbl(1076, 420, 144, "Удалённые/\nнепоказываемые эл-ты") ?>
    <?php endif ?>

    <div class="a-frame" style="<?= $pos(1220, 384, 108, 109) ?>"></div>
    <?php foreach ([['IsVP5', 'ВП5', 390], ['NoAnalog', 'Без аналогов', 410], ['Nelikvid', 'Неликвид', 431], ['IsPG', 'ПГ', 452], ['IsEFind', 'E-find', 472]] as [$n, $t, $top]):
        if ($det && $n !== 'IsEFind') continue; ?>
        <input type="checkbox" class="flt" id="<?= $n ?>" style="left:1225px;top:<?= $top ?>px"><?= $lbl(1240, $top - 2, 84, $t) ?>
    <?php endforeach ?>

    <div class="a-frame" style="<?= $pos(15, 429, 99, 73) ?>"></div>
    <?php foreach ([[0, 'Пантес', 437], [1, 'Торэкс', 460], [2, 'Не важно', 483]] as [$v, $t, $top]): ?>
        <input type="radio" name="grpCompany" value="<?= $v ?>" <?= $v === 2 ? 'checked' : '' ?> style="left:19px;top:<?= $top ?>px"><?= $lbl(34, $top - 2, 62, $t) ?>
    <?php endforeach ?>

    <?php if (!$det): ?>
    <?= $lbl(971, 384, 87, 'Принадлежность') ?>
    <div class="a-txt a-select edit" style="<?= $pos(971, 403, 87, 17) ?>"><select id="lstPrin">
        <?php foreach ($prinList as [$k, $t]): ?><option value="<?= Html::encode($k) ?>" <?= (int)$k === $prin ? 'selected' : '' ?>><?= Html::encode($t) ?></option><?php endforeach ?>
    </select></div>
    <?php endif ?>
</div>
<?php
$actUrl = Json::encode(Url::to(['act', 'id' => $id]));
$colsJs = Json::encode(array_map(fn($c) => [$c[0], $c[3] ?? ''], $cols));
$detJs  = $det ? 'true' : 'false';
$prinJs = (int)$prin;
$this->registerJs(<<<JS
(function () {
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    const COLS = $colsJs, DET = $detJs;
    const T = id => document.getElementById(id);
    const tree = T('xTree'), list = T('xList'), body = list.tBodies[0];
    let rows = [], sortI = -1, sortDesc = false, selPath = null, lastQuery = '', timer = null;
    window.colResize && colResize(list, list.dataset.key);

    // состояние окна для сервера (TypeSel, IsShow, grpCompany, lstPrin, флажки)
    function state() {
        const s = {};
        const r = n => document.querySelector('input[name=' + n + ']:checked')?.value;
        s.TypeSel = DET ? 5 : (r('TypeSel') ?? 1);
        s.IsShow = r('IsShow') ?? 0;
        s.grpCompany = r('grpCompany') ?? 2;
        s.lstPrin = T('lstPrin') ? T('lstPrin').value : $prinJs;
        document.querySelectorAll('input.flt').forEach(c => s[c.id] = c.checked ? 1 : 0);
        return s;
    }
    function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => {
            if (Array.isArray(v)) v.forEach(x => fd.append(k + '[]', x));
            else if (v && typeof v === 'object') Object.entries(v).forEach(([kk, vv]) => fd.append(k + '[' + kk + ']', vv));
            else fd.append(k, v ?? '');
        });
        if (csrfParam) fd.append(csrfParam, csrfToken);
        document.body.style.cursor = 'wait';                         // DoCmd.Hourglass True
        return fetch($actUrl, { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })))
            .finally(() => { document.body.style.cursor = ''; });
    }
    const q = data => post(Object.assign({ s: state() }, data));

    // ---------- дерево ----------
    function mkNode(text, path, kids) {
        const li = document.createElement('li');
        li.dataset.path = JSON.stringify(path);
        const nd = document.createElement('div'); nd.className = 'nd';
        const tg = document.createElement('span'); tg.className = 'tg'; tg.textContent = kids ? '+' : '';
        const tx = document.createElement('span'); tx.className = 'tx'; tx.textContent = text;
        nd.append(tg, tx); li.append(nd);
        if (kids) li.append(document.createElement('ul'));
        li.dataset.kids = kids ? '1' : '';
        return li;
    }
    const pathOf = li => JSON.parse(li.dataset.path);
    function expand(li, on) {
        const ul = li.querySelector(':scope > ul'); if (!ul) return;
        ul.style.display = on ? '' : 'none';
        li.querySelector(':scope > .nd > .tg').textContent = on ? '−' : '+';
        li.dataset.open = on ? '1' : '';
    }
    async function loadKids(li) {                                    // узлы заново (как xp_Sklad4TreeGetNodesKol не совпало — перестроить)
        const ul = li.querySelector(':scope > ul'); if (!ul) return;
        const path = pathOf(li);
        const res = await q({ act: 'Nodes', path });
        ul.innerHTML = '';
        (res.nodes || []).forEach(([t, k]) => ul.append(mkNode(t, path.concat([t]), k)));
        li.dataset.loaded = '1';
    }
    function select(li) {
        tree.querySelectorAll('.nd.sel').forEach(x => x.classList.remove('sel'));
        if (li) { li.querySelector(':scope > .nd').classList.add('sel'); li.querySelector(':scope > .nd').scrollIntoView({ block: 'nearest' }); }
        selPath = li ? pathOf(li) : null;
    }
    // FillTree2: корень «Склад» и первый уровень
    async function fillTree() {
        const top = tree.querySelector('ul'); top.innerHTML = '';
        const root = mkNode('Склад', [], true); top.append(root);
        await loadKids(root); expand(root, true);
        select(null);
        return root;
    }
    // xTree_NodeClick: «Вывести все» снимается, узлы перечитываются, список по узлу
    async function nodeClick(li) {
        select(li);
        T('FlgShowAll').checked = false; await showMax();
        if (li.dataset.kids && pathOf(li).length) await loadKids(li);
        expand(li, true);
        setSklad(0);
        await fillList();
    }
    tree.addEventListener('click', e => {
        const li = e.target.closest('li'); if (!li) return;
        if (e.target.classList.contains('tg') && li.dataset.open) { expand(li, false); return; }
        nodeClick(li);
    });
    // xTree4KodnullEl: раскрыть дерево до элемента, список не перечитывать (RfhTree = False)
    async function reveal(sklad) {
        const res = await q({ act: 'Path', sklad });
        const path = res.path || []; if (!path.length) return;
        let li = tree.querySelector('ul > li');                     // «Склад»
        for (let i = 0; i < path.length; i++) {
            if (!li.dataset.loaded) await loadKids(li);
            expand(li, true);
            const next = [...li.querySelectorAll(':scope > ul > li')].find(x => pathOf(x)[i] === path[i]);
            if (!next) break;
            li = next;
        }
        select(li);
        if (li.dataset.kids) expand(li, true);
    }

    // ---------- список ----------
    function draw() {
        let r = rows.slice();
        if (sortI >= 0) {                                            // xList_ColumnClick: сортировка текстом, повторный щелчок — обратно
            const f = COLS[sortI][0];
            r.sort((a, b) => String(a[f] ?? '').localeCompare(String(b[f] ?? ''), 'ru', { numeric: true }) * (sortDesc ? -1 : 1));
        }
        const keep = body.querySelector('tr.sel')?.dataset.id;
        body.innerHTML = '';
        const frag = document.createDocumentFragment();
        r.forEach(x => {
            const tr = document.createElement('tr'); tr.dataset.id = x.id; tr.dataset.row = JSON.stringify(x);
            COLS.forEach(([f, al]) => {
                const td = document.createElement('td'); const v = x[f] ?? '';
                td.textContent = v; td.title = v;
                if (al === 'r') td.style.textAlign = 'right';
                if (['Kat', 'IsVP5', 'IsNoAnalog'].includes(f) && v === 'да') td.className = 'blue';
                tr.append(td);
            });
            frag.append(tr);
        });
        body.append(frag);
        if (keep) body.querySelector('tr[data-id="' + keep + '"]')?.classList.add('sel');
        list.querySelectorAll('th .ar').forEach((s, i) => s.textContent = i === sortI ? (sortDesc ? '▲' : '▼') : '');
        T('cnt').querySelector('input').value = rows.length;
    }
    function setRows(r) { rows = r || []; body.innerHTML = ''; draw(); }
    async function fillList() {                                     // FillList: по выбранному узлу (корень и ничего — пусто)
        if (!selPath || !selPath.length) { setRows([]); return; }
        const res = await q({ act: 'List', path: selPath, all: T('FlgShowAll').checked ? 1 : 0 });
        if (!res.ok && res.msg) await msgBox(res.msg, { icon: 'warn' });
        setRows(res.rows);
    }
    async function fillListNew(st, mark) {                          // FillListNew: поиск
        const res = await q({ act: 'Find', q: st, mark });
        if (!res.ok && res.msg) { await msgBox(res.msg, { icon: 'warn' }); return; }
        setRows(res.rows);
        if (res.single) {                                            // одна строка: выбрать её; для упаковки — сразу «выбрать»
            await fillTree();
            if (DET) await reveal(res.single);                       // FillTree2 раскрывает дерево, если не frmMassInputSch
            setSklad(res.single);
            body.rows[0]?.classList.add('sel');
            if (res.upak) await choose();
        }
        T('FlgShowAll').checked = true; await showMax();
    }
    function setSklad(id) {
        T('SkladID').value = id ? id : '';
        T('ButEl').disabled = !id;
        if (!id) T('btnCopyFullNmeIzg')?.style.setProperty('visibility', 'hidden');
    }
    const selRow = () => body.querySelector('tr.sel');
    body.addEventListener('click', e => {                           // xList_Click
        const tr = e.target.closest('tr'); if (!tr) return;
        body.querySelectorAll('tr.sel').forEach(x => x.classList.remove('sel'));
        tr.classList.add('sel');
        setSklad(+tr.dataset.id);
        T('btnCopyFullNmeIzg')?.style.setProperty('visibility', 'visible');
        reveal(+tr.dataset.id);
    });
    body.addEventListener('dblclick', e => { const tr = e.target.closest('tr'); if (tr) { setSklad(+tr.dataset.id); choose(); } });
    list.tHead.addEventListener('click', e => {
        if (e.target.classList.contains('rsz')) return;
        const th = e.target.closest('th'); if (!th) return;
        const i = +th.dataset.i;
        if (sortI === i) sortDesc = !sortDesc; else { sortI = i; sortDesc = false; }
        draw();
    });

    // ---------- «Максимум строк» / «Вывести все» (ShowMaxItems) ----------
    async function showMax() {
        const all = T('FlgShowAll').checked;
        T('MaxItemsLabel').style.visibility = T('boxMax').style.visibility = all ? 'hidden' : 'visible';
        if (!all) { const r = await q({ act: 'Max', value: 0 }); T('MaxItems').value = r.value ?? 500; }
    }
    T('FlgShowAll').addEventListener('change', async () => { await showMax(); await fillList(); });
    T('MaxItems').addEventListener('change', async () => {
        const r = await q({ act: 'Max', value: parseInt(T('MaxItems').value, 10) || 0 });
        T('MaxItems').value = r.value ?? T('MaxItems').value;
        await fillList();
    });

    // ---------- поиск: от 3 символов через секунду (FindElem_Change / Form_Timer), со «*» — по Enter (AfterUpdate) ----------
    const fe = T('FindElem'), fm = T('fndMark');
    fe.addEventListener('input', () => {
        clearTimeout(timer);
        T('cnt').querySelector('input').value = '';
        const st = fe.value;
        if (st.length < 3 || st.includes('*')) return;
        timer = setTimeout(() => { if (fe.value === st && st !== lastQuery) { lastQuery = st; fillListNew(st, fm.value); } }, 1000);
    });
    fe.addEventListener('keydown', e => {
        if (e.key !== 'Enter') return;
        const st = fe.value;
        if (st.includes('*') && st !== lastQuery) { clearTimeout(timer); lastQuery = st; fillListNew(st, fm.value); }
    });
    fe.addEventListener('blur', () => { lastQuery = ''; });
    // fndMark_AfterUpdate
    fm.addEventListener('change', async () => {
        if (fe.value === '') {
            if (fm.value === '') { const root = tree.querySelector('ul > li'); if (root) await nodeClick(root); }
            else await fillListNew('', fm.value);
        } else await fillListNew(fe.value, fm.value);
    });

    // ---------- фильтры: TypeSel_AfterUpdate (дерево и список заново) ----------
    async function refilter() {
        await fillTree();
        await fillList();
        setSklad(0);
    }
    document.querySelectorAll('input[name=TypeSel], input[name=IsShow], input[name=grpCompany], input.flt').forEach(el => el.addEventListener('change', async () => {
        if (el.id === 'IsPG' && el.checked) ['IsVP5', 'NoAnalog', 'Nelikvid', 'IsEFind'].forEach(n => { if (T(n)) T(n).checked = false; });
        await refilter();
    }));
    T('lstPrin')?.addEventListener('change', refilter);             // lstPrin_AfterUpdate → grpCompany_AfterUpdate

    // ---------- кнопки ----------
    async function choose() {                                       // ButEl_Click
        const sklad = parseInt(T('SkladID').value, 10) || 0;
        const res = await post({ act: 'Choose', sklad });
        if (res.msg) await msgBox(res.msg, { icon: res.ok ? 'info' : 'warn' });
        if (res.ok) closeAccessDialog(false, res.value);
    }
    T('ButEl').addEventListener('click', choose);
    T('Kn8').addEventListener('click', () => closeAccessDialog(false));
    const copy = async f => {                                       // PutOffClipboard
        const tr = selRow(); if (!tr) return;
        const v = JSON.parse(tr.dataset.row)[f] ?? '';
        try { await navigator.clipboard.writeText(v); } catch (e) { await msgBox('Не удалось скопировать в буфер обмена'); }
    };
    T('btnBuf').addEventListener('click', () => copy('Naim'));
    T('btnCopyFullNmeIzg')?.addEventListener('click', () => copy('FullNmeIzg'));

    showMax();
    fillTree().then(() => fe.focus());
})();
JS, View::POS_END);
