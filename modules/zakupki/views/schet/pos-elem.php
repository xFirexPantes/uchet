<?php
/**
 * Позиция счёта на элементы (frmPosInput): правка (режимы 3, 7) и новая позиция (режим 5) — диалог поверх карточки.
 * Раскладка: layout/frmPosInput.php. Поля свободные: состояние окна держит скрипт, суммы и проверки считает сервер
 * (SchetPosElemService), как процедуры в VBA формы.
 *
 * @var yii\web\View $this
 * @var array $d  из SchetPosElemService::open
 */
use app\modules\zakupki\services\SchetPosElemService as P;
use app\modules\zakupki\services\ScreenRules;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$v = $d['v'];
// Начальное состояние по режиму (services/ScreenRules::posElem). Скрытые элементы рисуются и прячутся скриптом:
// их потом показывают действия в окне (⇄, ручной ввод, упаковка).
$st = ScreenRules::posElem($v);
$initHide = $st['hide'];
$st['hide'] = [];
$this->title = 'Позиция счёта';
$layout = require __DIR__ . '/layout/frmPosInput.php';
$w = 0; $h = 0;
foreach ($layout as $c) { $w = max($w, $c['L'] + $c['W']); $h = max($h, $c['T'] + $c['H']); }
$money = fn($x) => number_format((float)$x, 2, ',', '');
$calc = $d['stock'] + [
    'txtKolich' => P::n($v['kol']), 'txtPriceOpt' => P::n($v['priceOpt']), 'txtPriceRub' => $money($v['priceRub']),
    'SumNDS' => $money($v['sumNds']), 'Vsego' => $money($v['vsego']), 'SumWNDS' => $money($v['sumWNds']), 'PrcNDS' => P::n($v['prcNds']),
    'txtKurs' => number_format($v['kurs'], 4, ',', ''), 'UpKoef' => $v['upKoef'], 'kol2' => $v['kol2'],
    'txtInfo' => $v['info'], 'NmeCat' => $v['nmeCat'], 'txtReserv' => $d['reserv'],
    'DtaPost' => $v['dtaPost'], 'FirstSrokPost' => $v['firstPost'],
    'IncNDS' => $v['incNds'], 'ManualFlg' => $v['manualFlg'], 'ManualKol' => $v['manualKol'], 'ManualNme' => $v['manualNme'],
    'WOQuest' => $st['flags']['woQuest'], 'txtNDS' => P::n($v['nds']), 'PostOKEI' => (string)$v['okei'], 'PostOKEI2' => (string)$v['okei2'],
];
$map = fn($l) => array_combine(array_column($l, 0), array_column($l, 1)) ?: [];
$ndsMap = [];
foreach ($d['ndsList'] as [$val]) $ndsMap[P::n((float)str_replace(',', '.', $val))] = $val;
$combos = ['txtNDS' => $ndsMap, 'PostOKEI' => $map($d['okeiList']), 'PostOKEI2' => $map($d['okei2List'])];
$slots = [
    'butSave'  => '<button type="button" class="a-btn" id="butSave" title="Сохранить">✓</button>',
    'butExit'  => '<button type="button" class="a-btn" id="butExit" title="Закрыть без сохранения">✕</button>',
    'Кнопка86' => '<button type="button" class="a-btn" id="Кнопка86" title="Деньги в рублях / опт в $">⇄</button>',
];
foreach (['IncNDS', 'ManualFlg', 'WOQuest'] as $n) {
    $slots[$n] = '<input type="checkbox" class="a-opt" id="' . $n . '"' . ($calc[$n] ? ' checked' : '') . ' style="position:static;width:100%;height:100%;margin:0">';
}
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/dlg-modern.css');          // корпоративные цвета, когда окно открыто поверх карточки счёта
$this->registerJsFile('@web/js/access-dialog.js');
$this->registerJsFile('@web/js/picker.js');
// dlg-modern ставится на <html> только если родитель — современный экран или карточка счёта;
// скрипт в head: класс готов до отрисовки, окно не мигает бежевым
$this->registerJs(<<<'JS'
try {
    if (window.parent !== window && window.parent.document.querySelector('.vh-modern, .vh-card')) {
        document.documentElement.classList.add('dlg-modern');
    }
} catch (e) { /* другой сайт или нет доступа — обычный вид Access */ }
JS, View::POS_HEAD);
?>
<div class="access-form" id="frmPosInput" style="width:<?= $w + 4 ?>px;height:<?= $h + 4 ?>px">
<?= $this->render('@app/views/access/_form', [
    'layout' => $layout, 'rec' => [], 'calc' => $calc, 'combos' => $combos, 'slots' => $slots, 'state' => $st,
    'note' => 'ещё не перенесено',
]) ?>
</div>
<?php
$cfg = Json::encode([
    'v' => $v, 'hide' => $initHide, 'flags' => $st['flags'], 'locked' => $st['locked'], 'calcUrl' => Url::to(['pos-elem-calc', 'id' => $v['id']]), 'saveUrl' => Url::to(['pos-elem-save', 'id' => $v['id']]),
]);
$this->registerJs(<<<JS
(function () {
    const C = $cfg, v = C.v;
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    let queue = Promise.resolve();
    function post(url, data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, x]) => {
            if (x && typeof x === 'object') Object.entries(x).forEach(([kk, vv]) => fd.append(k + '[' + kk + ']', typeof vv === 'boolean' ? (vv ? 1 : 0) : (vv ?? '')));
            else fd.append(k, x ?? '');
        });
        if (csrfParam) fd.append(csrfParam, csrfToken);
        const p = queue.then(() => fetch(url, { method: 'POST', body: fd }))
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })));
        queue = p.catch(() => {});
        return p;
    }
    const el = id => document.getElementById(id);
    const box = id => el(id)?.closest('.a-txt, .a-slot') || el(id);
    const show = (id, on) => { const b = box(id); if (b) b.style.display = on ? '' : 'none'; };
    const num = s => { const x = parseFloat(String(s ?? '').replace(/\\s/g, '').replace(',', '.')); return isNaN(x) ? 0 : x; };
    const f2 = x => (Math.round(x * 100) / 100).toFixed(2).replace('.', ',');
    const f4 = x => String(Math.round(x * 10000) / 10000).replace('.', ',');
    function paint() {
        el('txtKolich').value = f4(v.kol); el('txtPriceRub').value = f2(v.priceRub); el('txtPriceOpt').value = f4(v.priceOpt);
        el('SumNDS').value = f2(v.sumNds); el('Vsego').value = f2(v.vsego); el('SumWNDS').value = f2(v.sumWNds);
        el('PrcNDS').value = f4(v.prcNds); el('txtKurs').value = (Math.round(v.kurs * 10000) / 10000).toFixed(4).replace('.', ',');
    }
    function warn(t) {   // TestPrice / TestKol: «Без вопросов» — в поле Warn, иначе сообщение
        if (!t) return;
        if (el('WOQuest').checked) el('Warn').value = t; else return msgBox(t, { icon: 'warn' });
    }
    function calc(op, extra = {}) { return post(C.calcUrl, Object.assign({ op, v }, extra)); }
    // SetSums4Pos (если суммы не ручные) + SetOptPrice
    async function sums() {
        if (!v.mnlSm) { const r = await calc('sums'); Object.assign(v, r.set || {}); }
        optPrice(); paint();
    }
    function optPrice() {   // SetOptPrice: при ценах в рублях — опт в $ = цена с НДС / курс
        if (v.inUE === 0) { if (rubMode && v.kurs) v.priceOpt = Math.round(v.prcNds / v.kurs * 10000) / 10000;
                            else if (!rubMode) v.priceRub = Math.round(Math.round(v.priceOpt * v.kurs * 100) / 100 * (v.nds + 100) / 100 * 100) / 100; }
        else v.priceOpt = v.priceRub;
    }
    async function testPrice() { const r = await calc('testPrice'); el('Warn').value = ''; warn(r.text); }

    // Кнопка86: «Деньги в рублях» ↔ опт в $
    let rubMode = false;
    function toggle() {
        rubMode = !rubMode;
        show('txtPriceRub', rubMode); show('txtPriceOpt', !rubMode); show('SumNDS', rubMode && C.flags.sumNdsInRub);
        el('txtKurs').disabled = !rubMode || C.locked.includes('txtKurs'); el('txtNDS').disabled = !rubMode || C.locked.includes('txtNDS');
    }
    el('Кнопка86').addEventListener('click', toggle);

    // начальная видимость, как после Form_Load (правила — на сервере, ScreenRules::posElem)
    C.hide.forEach(id => show(id, false));
    toggle();
    paint();

    const on = (id, fn) => el(id)?.addEventListener('change', fn);
    on('txtKolich', () => { v.kol = num(el('txtKolich').value); v.vsego = 0; v.wasChg = 1; sums(); });
    on('txtPriceRub', () => { v.priceRub = num(el('txtPriceRub').value); v.wasChg = 1; if (!v.mnlSm) v.vsego = 0; sums(); });
    on('txtPriceOpt', () => { v.priceOpt = num(el('txtPriceOpt').value); v.wasChg = 1; optPrice(); sums().then(testPrice); });
    on('SumNDS', () => { v.sumNds = num(el('SumNDS').value); if (!v.mnlSm) v.vsego = 0; paint(); });
    on('Vsego', () => {   // цена из «Всего»
        v.vsego = num(el('Vsego').value);
        const k = v.incNds ? 1 : 1 + v.nds / 100;
        if (v.kol) v.priceRub = v.vsego / (v.kol * k);
        v.wasChg = 1; v.vsego = 0; sums();
    });
    on('txtKurs', () => { v.kurs = num(el('txtKurs').value); optPrice(); paint(); testPrice(); });
    el('IncNDS').addEventListener('change', () => { v.incNds = el('IncNDS').checked; v.vsego = 0; v.wasChg = 1; sums().then(testPrice); });
    on('txtNDS', async () => {
        const nv = num(el('txtNDS').value), r = await calc('checkNds', { nds: nv });
        if (r.res === -1 && !(await askYesNo('Вы меняете ставку НДС, т.к. поставщик поменял режим налогообложения?'))) { el('txtNDS').value = f4(v.oldNds); return; }
        if (r.res === -2 || r.res === -3) { msgBox(r.res === -2 ? 'В других строках этого счета НДС > 0' : 'В других строках этого счета НДС = 0'); el('txtNDS').value = f4(v.oldNds); return; }
        v.nds = nv; v.oldNds = nv; await sums(); testPrice();
    });
    on('UpKoef', () => v.upKoef = el('UpKoef').value);
    on('txtInfo', () => v.info = el('txtInfo').value === '' ? ' ' : el('txtInfo').value);
    on('ManualKol', () => v.manualKol = el('ManualKol').value);
    on('ManualNme', () => v.manualNme = el('ManualNme').value);
    el('ManualFlg').addEventListener('change', () => { v.manualFlg = el('ManualFlg').checked; show('ManualKol', v.manualFlg); show('ManualNme', v.manualFlg); });
    on('DtaPost', async () => {   // DtaPost_GotFocus: после выбора даты — TestKol и TestPrice
        v.dtaPost = el('DtaPost').value;
        const k = await calc('testKol'); el('Warn').value = ''; warn(k.text); testPrice();
    });
    on('FirstSrokPost', async () => {
        const d = el('FirstSrokPost').value;
        if (d && v.dtaPost && d > v.dtaPost) {
            if (await askYesNo('Дата первой поставки больше, чем дата поставки. Выровнять по дате первой поставки?\\n\\n«Нет» — первая поставка = дата поставки')) { v.dtaPost = d; el('DtaPost').value = d; v.firstPost = d; }
            else { v.firstPost = v.dtaPost; el('FirstSrokPost').value = v.dtaPost; }
        } else v.firstPost = d;
    });
    el('NmeCat').addEventListener('keyup', e => { if (e.key === 'Delete') { v.nmeCat = ''; el('NmeCat').value = ''; } });
    el('NmeCat').title = 'Двойной щелчок — выбрать каталог, Delete — очистить';
    // NmeCat_DblClick: frmfndPR, режим 49; в NmeCat — OldLine (второй столбец списка или введённый текст)
    el('NmeCat').addEventListener('dblclick', () => {
        accessPicker('frmfndPR', 49).then(sel => { if (sel && sel.key !== '') { v.nmeCat = sel.key; el('NmeCat').value = sel.key; } });
    });
    // единицы поставщика: UpdOKEI / UpdOKEI2
    on('PostOKEI', async () => {
        const o = parseInt(el('PostOKEI').value, 10), r = await calc('okei', { okei: o });
        if (r.res === 1 && !v.manualFlg) { msgBox('Нет связи между ОКЕИ Торэкс и ОКЕИ поставщика', { icon: 'warn' }); el('PostOKEI').value = String(v.okei); return; }
        v.okei = o;
        if (r.res === 2) {
            const s = el('PostOKEI2'); s.innerHTML = '<option value=""></option>' + (r.okei2List || []).map(x => '<option value="' + x[0] + '">' + x[1] + '</option>').join('');
            show('PostOKEI2', true); show('kol2', true);
        } else if (r.res === 0) { show('PostOKEI2', false); show('kol2', false); v.kol2 = 1; el('kol2').value = '1'; }
    });
    on('kol2', () => { const k = parseInt(el('kol2').value, 10); if (!k) { msgBox('Укажите кол-во в упаковке', { icon: 'warn' }); el('kol2').value = v.kol2; return; } v.kol2 = k; });
    on('PostOKEI2', async () => {
        if (!v.kol2) { msgBox('Укажите кол-во в упаковке', { icon: 'warn' }); return; }
        const o = parseInt(el('PostOKEI2').value, 10), r = await calc('okei', { okei: v.okei, okei2: o, kol2: v.kol2 });
        if (r.res === 1 && !v.manualFlg) { msgBox('Нет связи между ОКЕИ Торэкс и ОКЕИ поставщика', { icon: 'warn' }); el('PostOKEI2').value = String(v.okei2); return; }
        v.okei2 = o;
    });
    document.querySelectorAll('input.ed').forEach(i => i.addEventListener('keydown', e => { if (e.key === 'Enter') i.blur(); }));

    // ✓ butSave_Click
    // Вопросы сервера (ask) — confirm и повтор с ответом; focus — поставить курсор в поле и остановиться
    async function save(extra = {}, answers = {}) {
        document.activeElement?.blur();
        await queue; optPrice();
        const r = await post(C.saveUrl, Object.assign({ v, answers }, extra));
        if (r.ask) { answers[r.ask.key] = (await askYesNo(r.ask.text)) ? '1' : '0'; return save(extra, answers); }
        if (r.focus) { el(r.focus)?.focus(); return; }
        if (r.pick) {   // причина изменения (frmfndZac 19) + комментарий
            const sel = await accessPicker(r.pick.form, r.pick.mode);
            if (!sel) { await msgBox(r.pick.empty, { icon: 'warn' }); return; }
            const comm = (await inputBox('Комментарий причины')) ?? '';
            return save({ reason: sel.key, comm }, answers);
        }
        if (r.msg) await msgBox(r.msg);
        if (r.close) closeAccessDialog(true);
    }
    el('butSave').addEventListener('click', () => save());
    el('butExit').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
