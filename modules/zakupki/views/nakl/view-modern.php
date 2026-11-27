<?php
/**
 * Накладная (frmMassInput) — современный вид, диалог поверх карточки счёта (web/js/access-dialog.js).
 *
 * Обычная разметка: шапка (счёт / накладная / служебное), кнопки, таблица позиций, внизу «Где купил» и суммы;
 * у новой накладной к счёту на элементы — ещё позиции счёта (lstSch). Координаты Access не используются,
 * раскладка тянется по высоте окна. Таблицы — общий views/access/_grid.php (ширины столбцов запоминаются).
 *
 * @var yii\web\View $this
 * @var int   $id  накладная
 * @var array $d   NaklService::view
 */
use app\components\AccessFormat as F;
use app\modules\zakupki\services\ScreenRules;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$rec  = $d['rec'];
// Что можно менять, подписи «акт/накладная», изменяемые столбцы: правила экрана (services/ScreenRules::nakl)
$st   = ScreenRules::nakl($d);
$edit = $st['flags']['edit'];
$lst  = $st['flags']['lstSch'];
$this->title = $st['flags']['akt'] ? 'Акт' : 'Накладная';

// SubFrmqrytblAll: [поле, заголовок, ширина, опции]
$posCols = [
    ['Kol', 'Кол-во', 60, ['al' => 'r']], ['KolByNakl', 'В накл.', 60, ['al' => 'r']],
    ['PriceOpt', 'Цена опт.', 65, ['al' => 'r', 'fmt' => '0.0000', 'dp' => 4]], ['ТочноеНазвание', 'Маркировка', 250, []],
    ['OrigCountry', 'Страна', 129, []], ['GTD', 'ГТД', 178, []], ['Наименование', 'Наименование', 271, []],
    ['Номинал', 'Номинал', 76, []], ['Корпус', 'Корпус', 98, []], ['Изготовитель', 'Изготовитель', 115, []],
    ['PriceWNDS', 'Цена, руб.', 75, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 2]], ['SumWNDS', 'Сумма, руб.', 75, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 2]],
    ['SumNDS', 'Сумма НДС', 76, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 2]], ['Vsego', 'Всего', 76, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 2]],
    ['ГдеКупил', 'Продавец', 101, []], ['Ostatok', 'Остаток', 54, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 0]],
    ['ProductDate', 'Дата произв.', 76, ['fmt' => 'dd.mm.yyyy']], ['WarrantySrok', 'Срок годн.', 57, ['al' => 'r', 'fmt' => 'Fixed', 'dp' => 0]],
    ['WarrantyDate', 'Годен до', 79, ['fmt' => 'dd.mm.yyyy']], ['Who', 'Кто', 66, []], ['DTLog', 'Когда', 69, ['fmt' => 'General Date']],
];
// IsChangedNakl(False): в режиме правки заголовки изменяемых столбцов выделены
foreach ($posCols as &$pc) if (in_array($pc[0], $st['editCols'], true)) $pc[3]['cls'] = 'ed-col';
unset($pc);
$whereCols = [
    ['ЦенаОптовая', 'Цена, опт', 87, ['al' => 'r', 'dp' => 4]], ['Количество', 'Кол-во', 87, ['al' => 'r']],
    ['Организация', 'Продавец', 124, []], ['Дата', 'Дата', 88, ['fmt' => 'Short Date']],
];

// Подписи шапки: у деталей с IsAkt = 1 — «акт» вместо «накладной»
$lblNakl = rtrim($st['labels']['Надпись33'] ?? '№ накладной:', ':');
$lblDta  = rtrim($st['labels']['Надпись34'] ?? 'Накладная от:', ':');

$iso = static fn($v): string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : '';
$dst = ['0' => 'Сокол', '1' => 'ОЗЗ', '255' => 'Не указан'];
$dstVal = $rec['Dst'] ?? '';
$dstKey = is_numeric($dstVal) ? (string)(int)$dstVal : (string)$dstVal;

/** Поле шапки: $o[ed] — можно менять (только в режиме правки), $o[field] — поле записи для сохранения. */
$inp = static function (string $id, string $val, array $o = []) use ($edit): string {
    $a = ' class="vh-dlg-inp' . (!empty($o['ed']) && $edit ? ' ed' : '') . '" id="' . $id . '" value="' . Html::encode($val) . '"';
    if (!empty($o['ed']) && $edit) $a .= ' data-field="' . Html::encode($o['field'] ?? $id) . '" data-kind="text"';
    else $a .= ' readonly';
    if (!empty($o['type'])) $a .= ' type="' . $o['type'] . '"';
    return '<input' . $a . '>';
};
$sumRows = '';
foreach ($d['sumSch'] as $r) {
    $sumRows .= '<tr><td>' . Html::encode(F::value($r[0] ?? null)) . '</td><td style="text-align:right">' . Html::encode(F::value($r[1] ?? null)) . '</td></tr>';
}

$tools = [
    'butDob'    => ['＋', 'Добавить позицию в накладную'],
    'DelRec'    => ['−', 'Удалить позицию из накладной'],
    'Кнопка58'  => ['✎', 'Корректировка позиции в накладной'],
    'butSaveAs' => ['💾', 'Сохранить накладную'],
    'LoadGTD'   => ['ГТД', 'Загрузка ГТД'],
    'ToSklad'   => ['На склад', 'Перенести элементы на склад'],
];
$why = $edit ? 'недоступно в этом режиме' : 'сначала включите режим правки (✎)';

$this->registerCssFile('@web/css/access-form.css');    // общий вид таблиц (.a-grid)
$this->registerCssFile('@web/css/colresize.css');
$this->registerCssFile('@web/css/dlg-modern.css');     // корпоративные цвета окна
$this->registerCss('body { margin:0; background:#fff; }');
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
<div class="vh-nk" id="frmMassInput">
    <div class="vh-nk-head">
        <div class="vh-nk-block">
            <div class="vh-nk-t">Счёт</div>
            <div class="vh-nk-rows">
                <span class="vh-nk-lbl">Продавец</span><?= $inp('Организация', (string)($rec['Org'] ?? '')) ?>
                <span class="vh-nk-lbl">Покупатель</span><?= $inp('TP', (string)($rec['TP'] ?? '')) ?>
                <span class="vh-nk-lbl">№ счёта</span><?= $inp('НомерСчета', (string)($rec['NumSch'] ?? '')) ?>
                <span class="vh-nk-lbl">Счёт от</span><?= $inp('ДатаСчета', F::value($rec['DtaSch'] ?? null, 'Short Date')) ?>
            </div>
        </div>

        <div class="vh-nk-block">
            <div class="vh-nk-t"><?= $st['flags']['akt'] ? 'Акт' : 'Накладная' ?></div>
            <div class="vh-nk-rows">
                <span class="vh-nk-lbl"><?= Html::encode($lblNakl) ?></span>
                <?= $inp('НомерНакладной', (string)($rec['НомерНакладной'] ?? ''), ['ed' => true, 'field' => 'НомерНакладной']) ?>
                <span class="vh-nk-lbl"><?= Html::encode($lblDta) ?></span>
                <?php if ($edit): ?>
                    <input class="vh-dlg-inp ed" type="date" id="ДатаНакладной" data-field="ДатаНакладной" data-kind="date"
                        value="<?= Html::encode($iso($rec['ДатаНакладной'] ?? null)) ?>">
                <?php else: ?>
                    <?= $inp('ДатаНакладной', F::value($rec['ДатаНакладной'] ?? null, 'Short Date')) ?>
                <?php endif ?>
                <span class="vh-nk-lbl">Склад</span>
                <?php if ($edit): ?>
                    <select class="vh-dlg-inp ed" id="Dst" data-field="Dst" data-kind="select">
                        <?php if (!isset($dst[$dstKey])): ?><option value=""></option><?php endif ?>
                        <?php foreach ($dst as $k => $t): ?>
                            <option value="<?= $k ?>"<?= (string)$k === $dstKey ? ' selected' : '' ?>><?= Html::encode($t) ?></option>
                        <?php endforeach ?>
                    </select>
                <?php else: ?>
                    <?= $inp('Dst', $dst[$dstKey] ?? $dstKey) ?>
                <?php endif ?>
            </div>
        </div>

        <div class="vh-nk-stamp">
            <span><span class="vh-nk-lbl">Дата</span> <?= Html::encode(F::value($rec['Дата'] ?? null, 'Short Date')) ?></span>
            <span><span class="vh-nk-lbl">Время</span> <?= Html::encode(F::value($rec['Время'] ?? null, 'Short Time')) ?></span>
            <span><span class="vh-nk-lbl">User</span> <?= Html::encode((string)($rec['UserCur'] ?? '')) ?></span>
            <span><span class="vh-nk-lbl">ShcID</span> <?= Html::encode((string)($rec['ShcID'] ?? '')) ?></span>
        </div>
    </div>

    <div class="vh-nk-tools">
        <?php foreach ($tools as $name => [$label, $tip]):
            if (isset($st['edit'][$name])): ?>
                <button type="button" class="vh-dlg-btn act" id="<?= $name ?>" data-act="<?= $name ?>" title="<?= Html::encode($tip) ?>"><?= Html::encode($label) ?></button>
            <?php else: ?>
                <button type="button" class="vh-dlg-btn" id="<?= $name ?>" disabled title="<?= Html::encode("$tip: $why") ?>"><?= Html::encode($label) ?></button>
            <?php endif;
        endforeach ?>
        <?php if ($edit): ?><span class="vh-nk-badge">Режим правки</span><?php endif ?>
        <?php if ($d['leg01']): ?><span class="vh-nk-badge vh-nk-badge-warn">Комплект плат</span><?php endif ?>
        <span class="vh-nk-hint">Двойной щелчок по «Дата произв.», «Годен до», «Остаток» — правка</span>
        <button type="button" class="vh-dlg-btn vh-nk-exit" id="butExit" title="Выход">✕</button>
    </div>

    <div class="vh-nk-grid">
        <?= $this->render('@app/views/access/_grid', ['tid' => 'npos', 'key' => 'nakl.pos', 'cols' => $posCols,
            'rows' => $d['pos'], 'idField' => 'AllID', 'dataFields' => ['SkladID', 'InSklad', 'IDSchEl', 'ProductDate', 'WarrantyDate']]) ?>
    </div>

    <div class="vh-nk-bottom">
        <div class="vh-nk-where">
            <div class="vh-nk-cap">Где купил</div>
            <div class="vh-nk-grid vh-nk-grid-sm">
                <?= $this->render('@app/views/access/_grid', ['tid' => 'where', 'key' => 'nakl.where', 'cols' => $whereCols, 'rows' => $d['whereBy']]) ?>
            </div>
        </div>
        <div class="vh-nk-sums">
            <div class="vh-nk-cap">Суммы</div>
            <div class="vh-nk-grid vh-nk-grid-sm">
                <table class="a-grid" id="sumSch">
                    <colgroup><col style="width:132px"><col style="width:57px"></colgroup>
                    <tbody><?= $sumRows ?></tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($lst): // Rgm 2: позиции счёта (SubFrmMassInputSch) — те же столбцы, что в карточке ?>
        <div class="vh-nk-lsch">
            <div class="vh-nk-cap" style="display:flex;align-items:center;gap:.4rem">
                <span>Позиции счёта</span>
                <?php /* Легенда цветов — те же столбцы, что в карточке счёта (_pos-cols.php) */ ?>
                <?= $this->render('@app/views/access/_color-legend', [
                    'id'    => 'naklPosColors',
                    'title' => 'Цвета в позициях счёта',
                    'note'  => 'Цвет фона в столбце подсказывает состояние позиции счёта.',
                    'btn'   => 'vh-dlg-btn',
                    'blocks' => [
                        ['t' => 'Наименование', 'rows' => [
                            ['sw' => '#a7da4e', 'label' => 'по позиции уже есть накладная ([ExistNakl]=1)'],
                        ]],
                        ['t' => 'ID Пост.', 'rows' => [
                            ['sw' => '#fce6d4', 'label' => 'пустой ID подсвечен цветом счёта (PaintIDPost)'],
                        ]],
                    ],
                ]) ?>
            </div>
            <div class="vh-nk-grid">
                <?php ['cols' => $schCols, 'key' => $schKey] = (function () use ($d) { $rec = $d['cardRec']; $all = false; return require __DIR__ . '/../schet/_pos-cols.php'; })(); ?>
                <?= $this->render('@app/views/access/_grid', ['tid' => 'lsch', 'key' => 'nakl.' . $schKey, 'cols' => $schCols, 'rows' => $d['lstSch']]) ?>
            </div>
        </div>
    <?php endif ?>

    <?php // Служебные поля формы Access: их читают скрипты окна ?>
    <input type="hidden" id="txtShcID" value="<?= Html::encode((string)($rec['ShcID'] ?? $id)) ?>">
    <input type="hidden" id="Rgm" value="<?= $lst ? '2' : '1' ?>">
    <input type="hidden" id="IsAkt" value="<?= (int)($rec['IsAkt'] ?? 0) ?>">
    <input type="hidden" id="InUE" value="<?= (int)($rec['InUE'] ?? 0) ?>">
</div>
<?php
$whereUrl = Json::encode(Url::to(['where-by']));
$actUrl   = Json::encode(Url::to(['act', 'id' => $id, 'sch' => $d['sch']]));
$editUrl  = Json::encode(Url::to(['view', 'id' => $id, 'sch' => $d['sch'], 'edit' => 1]));
$viewUrl  = Json::encode(Url::to(['view', 'id' => $id, 'sch' => $d['sch']]));
$isEdit   = $edit ? 'true' : 'false';
$addUrl   = Json::encode(Url::to(['add-el', 'id' => $id, 'sch' => $d['sch']]));
$gtdUrl   = Json::encode(Url::to(['gtd', 'id' => $id, 'sch' => $d['sch']]));
$bufUrl   = Json::encode(Url::to(['buf', 'id' => $id, 'sch' => $d['sch'], 'fake' => 0]));
// столбцы строк для скрипта: [поле, формат, знаков], и какие из них меняются (ScreenRules::nakl editCols)
$colMeta  = Json::encode(array_map(fn($c) => [$c[0], $c[3]['fmt'] ?? null, $c[3]['dp'] ?? null], $posCols));
$editCols = Json::encode($st['editCols']);
$this->registerJs(<<<JS
(function () {
    // SubFrmqrytblAll: Form_Current → SetRS4WhereBy (где и почём покупали элемент текущей строки)
    const pos = document.getElementById('npos'), where = document.getElementById('where');
    window.colResize && [pos, where, document.getElementById('lsch')].forEach(t => t && colResize(t, t.dataset.key));
    pos?.tBodies[0].rows[0]?.classList.add('sel');
    pos?.tBodies[0].addEventListener('click', e => {
        const tr = e.target.closest('tr'); if (!tr || tr.classList.contains('sel')) return;
        pos.querySelectorAll('tr.sel').forEach(x => x.classList.remove('sel')); tr.classList.add('sel');
        const sklad = JSON.parse(tr.dataset.row || '{}').SkladID; if (!sklad) return;
        fetch($whereUrl + '?sklad=' + encodeURIComponent(sklad)).then(r => r.json()).then(rows => {
            const b = where.tBodies[0]; b.innerHTML = '';
            rows.forEach(r => {
                const t = b.insertRow();
                [['ЦенаОптовая', 1], ['Количество', 1], ['Организация', 0], ['Дата', 0]].forEach(([f, right]) => {
                    const td = t.insertCell(); let v = r[f] ?? '';
                    if (f === 'Дата' && /^\\d{4}-\\d{2}-\\d{2}/.test(v)) v = v.slice(8, 10) + '.' + v.slice(5, 7) + '.' + v.slice(0, 4);
                    if (right) { v = String(v).replace('.', ','); td.style.textAlign = 'right'; }
                    td.textContent = v; td.title = v;
                });
            });
        });
    });

    // --- Действия. Вопросы сервера (ask) — confirm и повтор с ответом; info — сообщение и повтор с готовыми ответами.
    const edit = $isEdit;
    let chg = edit, leaving = false;                     // IsChg: новая накладная или правка — изменения были
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    function form(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => {
            if (v && typeof v === 'object') Object.entries(v).forEach(([kk, vv]) => fd.append(k + '[' + kk + ']', vv));
            else fd.append(k, v ?? '');
        });
        if (csrfParam) fd.append(csrfParam, csrfToken);
        return fd;
    }
    let queue = Promise.resolve();
    function post(data) {
        const p = queue.then(() => fetch($actUrl, { method: 'POST', body: form(data) })).then(r => r.ok ? r.json()
            : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })));
        queue = p.catch(() => {});
        return p;
    }
    function go(url) { leaving = true; url ? (location.href = url) : location.reload(); }
    function act(name, extra = {}, answers = {}) {
        return post(Object.assign({ act: name, answers }, extra)).then(async res => {
            if (res.chg) chg = true;
            if (res.ask) {
                if (res.msg) await msgBox(res.msg);
                answers[res.ask.key] = (await askYesNo(res.ask.text)) ? '1' : '0';
                return act(name, extra, answers);
            }
            if (res.info) { await msgBox(res.info); return act(name, extra, Object.assign(answers, res.answers || {})); }
            if (res.msg) await msgBox(res.msg);
            // ToSklad у деталей: «Установить состояние в 'Изготовлено' у …?» по каждой позиции → Try2SwitchSost
            for (const q of res.sost || []) {
                if (!(await askYesNo(q.text))) continue;
                const s = await post({ act: 'Sost', proc: q.id });
                if (s.msg) await msgBox(s.msg);
            }
            // На склад у деталей: IsReadySostDetal > 0 — окно ProdPlanSnd2Buf (acDialog, OpenArgs 0), потом строки заново
            if (res.buf !== undefined) {
                let dlg = accessDialog;
                try { if (window.top !== window && window.top.accessDialog) dlg = window.top.accessDialog; } catch (e) {}
                await dlg($bufUrl, 1013, 223);
            }
            // ＋ butDob: список готов (FillEl2Nakl) — окно frmAddEl2Nakl; после добавления — режим правки (IsChg, Add2ChgNakl)
            if (res.add) {
                const r = await accessDialog($addUrl, 600, 233);
                if (r.reload) { chg = true; go($editUrl); }
                return res;
            }
            // ГТД (LoadGTD_Click): окно frmLoadKompel; оно шире накладной — открывается в главном окне. После — строки заново (SetRS4PosShc)
            if (res.gtd !== undefined) {
                let dlg = accessDialog;
                try { if (window.top !== window && window.top.accessDialog) dlg = window.top.accessDialog; } catch (e) {}
                await dlg($gtdUrl, 1323, 648);
                go(edit ? $editUrl : undefined);
                return res;
            }
            if (res.close) { leaving = true; closeAccessDialog(true); return res; }
            if (res.edit) { go($editUrl); return res; }             // Кнопка58: режим правки
            if (res.view) { go($viewUrl); return res; }             // butSaveAs: выход из режима правки
            if (res.reload) go();
            return res;
        });
    }
    // Шапка: НомерНакладной, ДатаНакладной, Dst сохраняются сразу после изменения (AfterUpdate)
    document.querySelectorAll('.ed[data-field]').forEach(el => el.addEventListener('change', () => {
        post({ act: 'Save', field: el.dataset.field, value: el.value }).then(async res => {
            if (!res.ok) { await msgBox(res.msg || 'Не удалось сохранить', { icon: 'warn' }); go(); } else chg = true;
        });
    }));
    document.querySelectorAll('.act').forEach(el => el.addEventListener('click', () => {
        const name = el.dataset.act;
        document.activeElement?.blur();                         // сначала сохранить поле, в котором стоит курсор
        if (name === 'DelRec') {
            const tr = pos?.querySelector('tbody tr.sel');
            if (!tr) { msgBox('Выберите строку накладной'); return; }
            act(name, { pos: tr.dataset.id });
            return;
        }
        act(name);
    }));
    document.getElementById('butExit').addEventListener('click', () => {
        document.activeElement?.blur();
        act('butExit', { edit: edit ? '1' : '0', chg: chg ? '1' : '0' });
    });
    // --- Правка строк (SubFrmqrytblAll, режим правки): поля прямо в таблице, сохранение после изменения (AfterUpdate)
    const COLS = $colMeta, EDIT = $editCols;
    function fmt(v, f, dp) {                                  // как AccessFormat::value
        if (v === null || v === undefined || v === '') return '';
        const m = String(v).match(/^(\\d{4})-(\\d{2})-(\\d{2})(?: (\\d{2}):(\\d{2}):(\\d{2}))?/);
        if (m) { const d = m[3] + '.' + m[2] + '.' + m[1]; if (f && f.startsWith('dd')) return d; return m[4] && (m[4] + m[5]) !== '0000' ? d + ' ' + m[4] + ':' + m[5] + ':' + m[6] : d; }
        if (isNaN(v)) return String(v);
        const n = +v;
        if (f === 'Fixed') return n.toFixed(dp ?? 2).replace('.', ',');
        if (f && dp != null) return n.toFixed(dp).replace('.', ',');
        return String(v).replace('.', ',');
    }
    function paintRow(tr, r) {                                // строка заново с сервера
        if (!r) return;
        COLS.forEach(([f, ft, dp], i) => {
            const td = tr.cells[i], t = fmt(r[f], ft, dp), inp = td.querySelector('input');
            if (inp) { inp.value = t; inp.dataset.old = t; } else td.textContent = t;
            td.title = t;
        });
    }
    function paintSum(rows) {
        const b = document.querySelector('#sumSch tbody'); if (!b || !rows) return;
        b.innerHTML = '';
        rows.forEach(r => { const tr = b.insertRow(); tr.insertCell().textContent = fmt(r[0]); const c = tr.insertCell(); c.textContent = fmt(r[1]); c.style.textAlign = 'right'; });
    }
    let countries = null;
    const lists = {};
    function datalist(id, items) {
        let dl = document.getElementById(id);
        if (!dl) { dl = document.createElement('datalist'); dl.id = id; document.body.appendChild(dl); }
        dl.innerHTML = ''; items.forEach(x => { const o = document.createElement('option'); o.value = x; dl.appendChild(o); });
        return id;
    }
    if (edit && pos) {
        const idx = EDIT.map(f => COLS.findIndex(c => c[0] === f)).filter(i => i >= 0);
        [...pos.tBodies[0].rows].forEach(tr => {
            idx.forEach(i => {
                const td = tr.cells[i], f = COLS[i][0];
                const inp = document.createElement('input');
                inp.type = 'text'; inp.className = 'cell-ed'; inp.value = td.textContent; inp.dataset.old = inp.value; inp.dataset.f = f;
                inp.style.textAlign = td.style.textAlign || 'left';
                td.textContent = ''; td.style.padding = '0'; td.appendChild(inp);
            });
        });
        pos.addEventListener('focusin', async e => {
            const inp = e.target.closest('input.cell-ed'); if (!inp) return;
            const tr = inp.closest('tr'); tr.click();                                // текущая строка (Form_Current)
            if (inp.dataset.f === 'OrigCountry' && !inp.getAttribute('list')) {
                if (!countries) { const r = await post({ act: 'Countries' }); countries = r.list || []; datalist('dl-country', countries); }
                inp.setAttribute('list', 'dl-country');
            }
            if (inp.dataset.f === 'ТочноеНазвание') {                               // RowSource = xp_SubfrmqrytblAll SkladID
                const sk = JSON.parse(tr.dataset.row || '{}').SkladID;
                if (sk && !lists[sk]) { const r = await post({ act: 'Marks', sklad: sk }); lists[sk] = datalist('dl-mark-' + sk, r.list || []); }
                if (sk) inp.setAttribute('list', lists[sk]);
            }
        });
        pos.addEventListener('keydown', e => {
            const inp = e.target.closest('input.cell-ed'); if (!inp) return;
            if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
            if (e.key === 'Escape') { inp.value = inp.dataset.old; inp.blur(); e.stopPropagation(); }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {                     // соседняя строка, тот же столбец
                const tr = inp.closest('tr'), nx = e.key === 'ArrowDown' ? tr.nextElementSibling : tr.previousElementSibling;
                const ni = nx?.cells[inp.closest('td').cellIndex]?.querySelector('input');
                if (ni) { e.preventDefault(); ni.focus(); ni.select(); }
            }
        });
        pos.addEventListener('change', async e => {
            const inp = e.target.closest('input.cell-ed'); if (!inp || inp.value === inp.dataset.old) return;
            const tr = inp.closest('tr');
            inp.classList.add('saving');
            const res = await post({ act: 'RowSave', pos: tr.dataset.id, field: inp.dataset.f, value: inp.value });
            inp.classList.remove('saving');
            if (res.chg) chg = true;
            if (res.del) tr.remove(); else if (res.row) paintRow(tr, res.row); else if (!res.ok) inp.value = inp.dataset.old;
            if (res.sum) paintSum(res.sum);
            if (res.ok) inp.dataset.old = inp.value;
            if (res.msg) await msgBox(res.msg, { icon: res.ok ? 'info' : 'warn' });
        });
    }

    // Двойной щелчок: «Дата произв.», «Годен до» (WarrantyNaklUpdDateInNakl 1 / 3 → fnWarrantyNaklUpdDate), «Остаток» (Ostatok_DblClick)
    pos?.addEventListener('dblclick', async e => {
        const td = e.target.closest('tbody td'); if (!td) return;
        const f = COLS[td.cellIndex]?.[0];
        if (!['ProductDate', 'WarrantyDate', 'Ostatok'].includes(f)) return;
        const tr = td.closest('tr'), r = JSON.parse(tr.dataset.row || '{}');
        const done = res => { if (res.row) { paintRow(tr, res.row); tr.dataset.row = JSON.stringify(res.row); } };
        if (f === 'Ostatok') {                                          // GetFromBox(«Введите остаток», , 1)
            const s = await inputBox('Введите остаток', '');
            if (s === null) return;
            if (!/^\\s*-?\\d+([.,]\\d*)?\\s*$/.test(s)) { await msgBox('Необходимо ввести числовое значение'); return; }
            const res = await post({ act: 'Ostatok', pos: tr.dataset.id, value: s.trim() });
            if (!res.ok) await msgBox(res.msg || 'Не удалось отредактировать запись', { icon: 'warn' });
            done(res);
            return;
        }
        const hasW = !!r.WarrantyDate, hasP = !!r.ProductDate;
        let rgm = f === 'ProductDate' ? 1 : 3, prod = '';
        if (rgm < 3 && hasW && !(await askYesNo("Дата 'Годен до' уже установлена? Изменить её?", { def: 'no' }))) return;
        if (rgm === 1) {
            if (!hasP) {
                prod = await dateBox('Дата производства');
                if (prod === null) return;
                if (prod === '') {
                    if (!(await askYesNo("Дата производства не указана. Указать дату 'Годен до'?"))) return;
                    rgm = 3;
                } else rgm = 2;
            } else rgm = 3;
        }
        let dta;
        if (rgm === 2) {                                                  // frmInputBox (OpenArgs 2): срок + Д/М/Г
            let ans;
            for (;;) {
                ans = await inputBox('Введите срок годности', '', { select: [['d', 'Д'], ['m', 'М'], ['y', 'Г']], selDef: 'm' });
                if (ans === null) return;
                if (/^\\s*\\d+\\s*$/.test(ans[0])) break;
                await msgBox('Необходимо указать числовое значение');
            }
            dta = prod.replaceAll('-', '') + ans[1] + ans[0].trim();
        } else {
            const d = await dateBox('Годен до');
            if (!d) return;
            dta = d.replaceAll('-', '') + '-1';
        }
        const res = await post({ act: 'Warranty', pos: tr.dataset.id, dta });
        if (!res.ok && res.msg) await msgBox(res.msg, { icon: 'warn' });
        done(res);
    });

    // Окно закрыли не кнопкой выхода (Esc, закрытие вкладки) — снять отметку «накладную правит пользователь»
    window.addEventListener('pagehide', () => {
        if (edit && !leaving) navigator.sendBeacon($actUrl, form({ act: 'Release' }));
    });
})();
JS, View::POS_END);
