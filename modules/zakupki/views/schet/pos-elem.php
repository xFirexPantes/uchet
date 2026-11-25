<?php
/**
 * Позиция счёта на элементы (frmPosInput): правка (режимы 3, 7) и новая позиция (режим 5) — диалог поверх карточки.
 *
 * Обычная разметка (.vh-dlg): слева — поставка, в центре — цены и суммы, справа — склад. Координаты Access
 * не используются. Поля свободные: состояние окна держит скрипт, суммы и проверки считает сервер
 * (SchetPosElemService), как процедуры в VBA формы.
 *
 * @var yii\web\View $this
 * @var array $d  из SchetPosElemService::open
 */
use app\components\AccessFormat as F;
use app\modules\zakupki\services\SchetPosElemService as P;
use app\modules\zakupki\services\ScreenRules;
use yii\helpers\Html;
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
$locked = array_flip($st['locked']);                                // Enabled = False: поле видно, но не меняется
$hideNow = array_unique(array_merge($initHide, ['txtPriceOpt']));   // чтобы поля не мигали до запуска скрипта
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
/** Поле ввода: $o[r] — число справа, $o[ro] — только показ, $o[type] — тип, $o[title] — подсказка. */
$txt = static function (string $id, string $val, array $o = []) use ($locked): string {
    $a = ' class="vh-dlg-inp' . (!empty($o['r']) ? ' r' : '') . (empty($o['ro']) ? ' ed' : '') . '"'
        . ' type="' . ($o['type'] ?? 'text') . '" id="' . $id . '" value="' . Html::encode($val) . '"';
    if (!empty($o['ro'])) $a .= ' readonly';
    elseif (isset($locked[$id])) $a .= ' disabled';
    if (!empty($o['title'])) $a .= ' title="' . Html::encode($o['title']) . '"';
    return '<input' . $a . '>';
};
/** Список: значение могло не загрузиться из процедуры — тогда оставляем его одной строкой. */
$sel = static function (string $id, string $val, array $opts) use ($locked): string {
    if (!isset($opts[$val]) && $val !== '') $opts = [$val => $val] + $opts;
    $h = '<select class="vh-dlg-inp ed" id="' . $id . '"' . (isset($locked[$id]) ? ' disabled' : '') . '>';
    foreach ($opts as $k => $t) {
        $h .= '<option value="' . Html::encode((string)$k) . '"' . ((string)$k === $val ? ' selected' : '') . '>' . Html::encode((string)$t) . '</option>';
    }
    return $h . '</select>';
};
/** Флажок с подписью: $o[hide] — прячется вместе с подписью, $o[lid] — id подписи, $o[off] — только показ. */
$chk = static function (string $id, string $label, bool $on, array $o = []): string {
    return '<label class="vh-pe-chk' . (!empty($o['hide']) ? ' a-txt' : '') . '"' . ($o['style'] ?? '') . '>'
        . '<input type="checkbox" id="' . $id . '"' . ($on ? ' checked' : '') . (!empty($o['off']) ? ' disabled' : '') . '>'
        . '<span' . (!empty($o['lid']) ? ' id="' . $o['lid'] . '"' : '') . '>' . $label . '</span></label>';
};
/** Строка «подпись — поле»: $o[hide] — прячется целиком, $o[lid] — id подписи, $o[style] — скрыть до запуска скрипта. */
$line = static function (string $label, string $field, array $o = []): string {
    return '<div class="vh-pe-row' . (!empty($o['hide']) ? ' a-txt' : '') . '"' . ($o['style'] ?? '') . '>'
        . '<label class="vh-pe-lbl"' . (!empty($o['lid']) ? ' id="' . $o['lid'] . '"' : '') . '>' . $label . '</label>'
        . $field . '</div>';
};
$style = static fn(string $id) => in_array($id, $hideNow, true) ? ' style="display:none"' : '';
$stock = static fn(string $f, ?string $fmt = null, ?int $dp = null): string => F::value($calc[$f] ?? null, $fmt, $dp);
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
<div class="vh-dlg" id="frmPosInput">
    <?php // Служебная строка: откуда позиция (склад, счёт, когда и кем создана) ?>
    <div class="vh-pe-top">
        <div class="vh-pe-meta"><span class="vh-pe-lbl">Счёт</span><?= $txt('txtShcID', (string)($calc['txtShcID'] ?? ''), ['ro' => true]) ?></div>
        <div class="vh-pe-meta"><span class="vh-pe-lbl">SkladID</span><?= $txt('SkladIDAdd', (string)($calc['SkladIDAdd'] ?? ''), ['ro' => true]) ?></div>
        <div class="vh-pe-meta"><span class="vh-pe-lbl">Создана</span><?= $txt('txtDT', $stock('txtDT', 'General Date'), ['ro' => true]) ?></div>
        <div class="vh-pe-meta"><span class="vh-pe-lbl">Пользователь</span><?= $txt('txtUserCur', (string)($calc['txtUserCur'] ?? ''), ['ro' => true]) ?></div>
    </div>

    <div class="vh-dlg-body">
        <div class="vh-pe-cols">
            <?php // ---------- Поставка ---------- ?>
            <div class="vh-pe-col">
                <div class="vh-pe-t">Поставка</div>
               
                    <?= $line('Кол.поставщика', $txt('txtKolich', $calc['txtKolich'], ['r' => true])) ?>
                    <?= $line('кратность', $txt('UpKoef', (string)$calc['UpKoef'], ['r' => true])) ?>

                <?= $chk('IncNDS', 'Включает НДС', !empty($calc['IncNDS'])) ?>
                <?= $line('Ед. изм. пост.', $sel('PostOKEI', $calc['PostOKEI'], $map($d['okeiList']))) ?>
                <?= $line('Количество', $txt('kol2', (string)$calc['kol2'], ['r' => true]), ['hide' => true, 'style' => $style('kol2')]) ?>
                <?= $line('Ед. изм. пост.', $sel('PostOKEI2', $calc['PostOKEI2'], $map($d['okei2List'])), ['hide' => true, 'style' => $style('PostOKEI2')]) ?>
                <?= $line('Кол. Torex', $txt('ManualKol', (string)$calc['ManualKol'], ['r' => true]), ['hide' => true, 'style' => $style('ManualKol')]) ?>
                <?= $chk('ManualFlg', 'Ручной ввод количества Torex', !empty($calc['ManualFlg'])) ?>
                <?= $line('Точное название', $txt('ManualNme', (string)$calc['ManualNme']), ['hide' => true, 'style' => $style('ManualNme')]) ?>
                <?= $line('Информация', $txt('txtInfo', (string)$calc['txtInfo'])) ?>
                <?= $line('Дата поставки', $txt('DtaPost', (string)$calc['DtaPost'], ['type' => 'date'])) ?>
                <?= $line('1 дата поставки', $txt('FirstSrokPost', (string)$calc['FirstSrokPost'], ['type' => 'date']), ['hide' => true, 'style' => $style('FirstSrokPost')]) ?>
                <?= $line('Каталог', $txt('NmeCat', (string)$calc['NmeCat'], ['title' => 'Двойной щелчок — выбрать каталог, Delete — очистить'])) ?>
            </div>

            <?php // ---------- Цены и суммы ---------- ?>
            <div class="vh-pe-col">
                <div class="vh-pe-t">Цена и суммы</div>
                <?= $line('Опт,$', $txt('txtPriceOpt', $calc['txtPriceOpt'], ['r' => true]), ['hide' => true, 'style' => $style('txtPriceOpt')]) ?>
                <?= $line(Html::encode($st['labels']['Надпись89'] ?? 'Цена'), $txt('txtPriceRub', $calc['txtPriceRub'], ['r' => true]), ['hide' => true, 'lid' => 'Надпись89']) ?>
                <?= $line('Сумма НДС', $txt('SumNDS', $calc['SumNDS'], ['r' => true]), ['hide' => true, 'style' => $style('SumNDS')]) ?>
                <?= $line(Html::encode($st['labels']['Надпись109'] ?? 'Всего'), $txt('Vsego', $calc['Vsego'], ['r' => true]), ['lid' => 'Надпись109']) ?>
                <?= $line('Сумма без НДС', $txt('SumWNDS', $calc['SumWNDS'], ['r' => true]), ['hide' => true, 'style' => $style('SumWNDS')]) ?>
                <?= $line('Цена с НДС', $txt('PrcNDS', $calc['PrcNDS'], ['r' => true]), ['hide' => true, 'style' => $style('PrcNDS')]) ?>
                <?= $line('Курс,$', $txt('txtKurs', $calc['txtKurs'], ['r' => true])) ?>
                <?= $line('НДС,%', $sel('txtNDS', $calc['txtNDS'], $ndsMap)) ?>
                <div class="vh-pe-row">
                    <span class="vh-pe-lbl" id="Надпись87">Деньги в рублях</span>
                    <button type="button" class="vh-dlg-btn" id="Кнопка86" title="Деньги в рублях / опт в $">⇄</button>
                </div>
                <?= $chk('WOQuest', 'Без вопросов', !empty($calc['WOQuest']), ['hide' => true, 'lid' => 'Надпись121', 'style' => $style('WOQuest')]) ?>
            </div>

            <?php // ---------- Склад: только показ ---------- ?>
            <div class="vh-pe-col">
                <div class="vh-pe-t">На складе</div>
                <?= $line('На складе', $txt('Количество', $stock('Количество'), ['ro' => true])) ?>
                <?= $line('Резерв', $txt('txtReserv', $stock('txtReserv'), ['ro' => true, 'r' => true])) ?>
                <?= $line('Заказано', $txt('Поле70', $stock('Поле70'), ['ro' => true])) ?>
                <?= $line('Примечание', $txt('Поле72', $stock('Поле72'), ['ro' => true])) ?>
                <?= $line('Мин. кол-во', $txt('Поле74', $stock('Поле74'), ['ro' => true])) ?>
                <?= $line('Цена опт.', $txt('ЦенаОптовая', $stock('ЦенаОптовая', 'Fixed', 4), ['ro' => true])) ?>
                <?= $line('Цена розн.', $txt('Поле78', $stock('Поле78', 'Fixed', 4), ['ro' => true])) ?>
                <?= $line('Стеллаж', $txt('Поле90', $stock('Поле90'), ['ro' => true])) ?>
                <?= $line('Ячейка', $txt('Поле92', $stock('Поле92'), ['ro' => true])) ?>
                <?= $chk('SchWithNDS', 'Учитывать НДС', !empty($calc['SchWithNDS']), ['off' => true]) ?>
                <?= $chk('PrcWithNDS', 'Сумма включает НДС', !empty($calc['PrcWithNDS']), ['off' => true]) ?>
            </div>
        </div>

        <?php // Предупреждения сервера (TestPrice / TestKol) ?>
        <input class="vh-dlg-inp vh-pe-warn" type="text" id="Warn" readonly
            placeholder="Предупреждения о цене и количестве" title="Предупреждения о цене и количестве">
    </div>

    <div class="vh-dlg-foot">
        <span class="vh-dlg-hint">Суммы пересчитывает сервер. ✓ записывает позицию в счёт.</span>
        <span class="vh-dlg-btns">
            <button type="button" class="vh-dlg-btn" id="butSave" title="Сохранить">✓ Сохранить</button>
            <button type="button" class="vh-dlg-btn" id="butExit" title="Закрыть без сохранения">✕</button>
        </span>
    </div>

    <?php // Служебные поля — как в форме Access: их читают скрипты окна ?>
    <input type="checkbox" id="WithNDS" hidden>
    <input type="checkbox" id="MnlSm" hidden>
    <input type="hidden" id="WasChg" value="0">
    <input type="hidden" id="OldNDS" value="<?= Html::encode(P::n((float)$v['oldNds'])) ?>">
    <input type="hidden" id="NSh" value="<?= Html::encode((string)$v['nsh']) ?>">
    <input type="hidden" id="NDSVl" value="">
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
