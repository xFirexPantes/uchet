<?php
/**
 * Загрузка ГТД (frmLoadKompel) — диалог поверх накладной (кнопка «ГТД»). Ширина формы 19850 твипов = 1323 px,
 * область данных 9092 + примечание 632 = 648 px. Координаты = твипы / 15.
 * Вверху lstSch — позиции счёта (SubFrmMassInputSch, те же столбцы, что в карточке), внизу lstNakl — позиции в файле (subfrmLoadKompel).
 * Кнопки: «Сопоставить», − (удалить сопоставление); внизу 📂 «Загрузить файл», ⇩ «Выгрузить в накладную», ✕ «Закрыть форму».
 *
 * @var yii\web\View $this
 * @var int   $id   накладная
 * @var int   $sch  счёт (из адреса накладной)
 * @var array $d    GtdService::view
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Загрузка ГТД';
$g = require __DIR__ . '/_gtd-cols.php';
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/access-dialog.js');
$box = 'position:absolute;overflow:auto;background:#fff;border:1px solid #7f9db9;box-sizing:border-box';
?>
<style>
#frmLoadKompel .lbl{position:absolute;font:11px Tahoma,Arial,sans-serif;white-space:nowrap}
#frmLoadKompel .a-grid tbody tr{cursor:default}
#gfile td.g-kol.red{background:#ff0000;color:#fff}
#frmLoadKompel .ft{position:absolute;left:0;top:606px;width:1323px;height:42px;border-top:1px solid #aca899}
</style>
<div class="access-form" id="frmLoadKompel" style="width:1323px;height:648px">
    <div class="lbl" id="lstSch_N" style="left:4px;top:2px"><?= Html::encode($d['caption']) ?></div>
    <div style="<?= $box ?>;left:4px;top:19px;width:1319px;height:274px">
        <?= $this->render('@app/views/access/_grid', $g['sch'] + ['rows' => $d['sch_rows']]) ?>
    </div>
    <div class="lbl" style="left:4px;top:319px">Позиции в файле</div>
    <?php /* Легенда цветов подформы lstNakl (subfrmLoadKompel): ConditionalFormat14 */ ?>
    <button type="button" class="a-btn" data-cl-open="gtdColors"
            title="Что означают цвета в списке позиций файла"
            style="left:150px;top:306px;width:80px;height:22px">? Цвета</button>
    <button type="button" class="a-btn" id="AssignElKompel" style="left:1138px;top:306px;width:105px;height:22px">Сопоставить</button>
    <button type="button" class="a-btn" id="DelAssign" title="Удалить сопоставление" style="left:1247px;top:306px;width:38px;height:22px">−</button>
    <div id="lstNakl" style="<?= $box ?>;left:4px;top:336px;width:1319px;height:270px">
        <?= $this->render('@app/views/access/_grid', $g['file'] + ['rows' => $d['file_rows']]) ?>
    </div>
    <div class="ft"></div>
    <button type="button" class="a-btn" id="LoadKompelXl" title="Загрузить файл" style="left:4px;top:610px;width:38px;height:38px">📂</button>
    <button type="button" class="a-btn" id="Put2Nakl" title="Выгрузить в накладную" style="left:76px;top:610px;width:38px;height:38px">⇩</button>
    <button type="button" class="a-btn" id="ClsFrm" title="Закрыть форму" style="left:1281px;top:610px;width:38px;height:38px">✕</button>
    <input type="file" id="xlFile" accept=".xls,.xlsx,.xlsm" style="display:none">
    <?= $this->render('@app/views/access/_color-legend', [
        'id'    => 'gtdColors',
        'title' => 'Цвета в позициях файла',
        'note'  => 'Цвет фона в столбце подсказывает, сопоставлена ли строка файла с позицией счёта.',
        'btn'   => '',
        'blocks' => [
            ['t' => 'Маркировка', 'rows' => [
                ['sw' => '#ffff00', 'label' => 'перегрузка строки ([OverLoad]=1)'],
                ['sw' => '#ccffcc', 'label' => 'сопоставлена с позицией счёта ([IDSchEl]>0)'],
            ]],
            ['t' => 'Применение', 'rows' => [
                ['sw' => '#ccffcc', 'label' => 'сопоставлена с позицией счёта ([IDSchEl]>0)'],
            ]],
            ['t' => 'В накл', 'rows' => [
                ['sw' => '#ff0000', 'label' => 'красный — строка не сопоставлена (после «Выгрузить в накладную»)'],
            ]],
        ],
    ]) ?>
</div>
<?php
$actUrl = Json::encode(Url::to(['gtd-act', 'id' => $id, 'sch' => $sch]));
$this->registerJs(<<<JS
(function () {
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    let red = false;                      // Put2Nakl → -1: «В накл» красным у несопоставленных (FormatConditions [IDSchEl] = 0)
    const T = id => document.getElementById(id);
    const rowOf = tr => JSON.parse(tr?.dataset.row || '{}');
    const sel = t => T(t)?.querySelector('tbody tr.sel') || null;

    function bind(t) {
        const tbl = T(t); if (!tbl) return;
        window.colResize && !tbl.dataset.rsz && (colResize(tbl, tbl.dataset.key), tbl.dataset.rsz = '1');
    }
    function pick(t, id, scroll = true) {           // StndOnLne: встать на строку
        const tbl = T(t); if (!tbl) return null;
        tbl.querySelectorAll('tbody tr.sel').forEach(x => x.classList.remove('sel'));
        const tr = id == null ? null : tbl.querySelector('tbody tr[data-id="' + CSS.escape(String(id)) + '"]');
        if (tr) { tr.classList.add('sel'); if (scroll) tr.scrollIntoView({ block: 'nearest' }); }
        return tr;
    }
    function paintRed() {
        T('gfile')?.querySelectorAll('tbody tr').forEach(tr => {
            const td = tr.querySelector('td.g-kol');
            if (td) td.classList.toggle('red', red && +(rowOf(tr).IDSchEl || 0) === 0);
        });
    }
    function swap(t, html) {                        // новая таблица с сервера — заменить строки, ширины столбцов оставить
        if (!html) return;
        const tmp = document.createElement('div'); tmp.innerHTML = html;
        const nb = tmp.querySelector('tbody'), tbl = T(t);
        if (nb && tbl) tbl.replaceChild(nb, tbl.tBodies[0]);
    }
    // SetRS4PosNaklKompel: список файла виден, только если в нём есть строки
    function showFile() { T('lstNakl').style.visibility = T('gfile').tBodies[0].rows.length ? 'visible' : 'hidden'; }

    function post(data, file) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => {
            if (v && typeof v === 'object') Object.entries(v).forEach(([kk, vv]) => fd.append(k + '[' + kk + ']', vv));
            else fd.append(k, v ?? '');
        });
        if (file) fd.append('file', file);
        if (csrfParam) fd.append(csrfParam, csrfToken);
        document.body.style.cursor = 'wait';
        return fetch($actUrl, { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })))
            .finally(() => { document.body.style.cursor = ''; });
    }
    async function act(data, file, answers = {}) {
        const keepSch = sel('gsch')?.dataset.id, keepFile = sel('gfile')?.dataset.id;
        const res = await post(Object.assign({ answers }, data), file);
        if (res.ask) {
            answers[res.ask.key] = (await askYesNo(res.ask.text)) ? '1' : '0';
            return act(data, null, answers);
        }
        if (res.red) red = true;
        swap('gsch', res.sch_html); swap('gfile', res.file_html);
        if (res.file_html !== undefined) showFile();
        paintRed();
        pick('gsch', res.selSch ?? keepSch, res.selSch != null);
        pick('gfile', res.selFile ?? keepFile, res.selFile != null);
        if (res.msg) await msgBox(res.msg, { icon: res.ok ? 'info' : 'warn' });
        if (res.close) closeAccessDialog(true);
        return res;
    }

    ['gsch', 'gfile'].forEach(bind);
    showFile();
    T('gsch').tBodies[0].rows[0]?.classList.add('sel');
    T('gsch').addEventListener('click', e => { const tr = e.target.closest('tbody tr'); if (tr) pick('gsch', tr.dataset.id, false); });
    // subfrmLoadKompel Form_Current: сопоставленная строка файла — встать на её позицию счёта (AllID = IDSchEl)
    T('gfile').addEventListener('click', e => {
        const tr = e.target.closest('tbody tr'); if (!tr) return;
        pick('gfile', tr.dataset.id, false);
        const el = +(rowOf(tr).IDSchEl || 0);
        if (el) pick('gsch', el);
    });

    // 📂 LoadKompelXl_Click: файл выбирается здесь, читает его сервер
    T('LoadKompelXl').addEventListener('click', () => { T('xlFile').value = ''; T('xlFile').click(); });
    T('xlFile').addEventListener('change', () => { const f = T('xlFile').files[0]; if (f) { red = false; act({ act: 'Load' }, f); } });

    // «Сопоставить» (AssignElKompel_Click)
    T('AssignElKompel').addEventListener('click', async () => {
        if (!T('gsch').tBodies[0].rows.length || !T('gfile').tBodies[0].rows.length) return;
        const s = sel('gsch'), f = sel('gfile');
        if (!s) { await msgBox('Не выбран элемент счёта для сопоставления'); return; }
        if (!f) { await msgBox('Не выбран элемент файла для сопоставления'); return; }
        if (+(rowOf(f).IDSchEl || 0) > 0) {
            const a = await askYesNoCancel("Данный элемент файла уже сопоставлен с позицией счёта. Завести ещё строку?\\n(Ответ 'нет' заменит сопоставление)");
            if (a === 'cancel') return;
            if (a === 'yes') {
                const r = await act({ act: 'Assign', pos: s.dataset.id, str: f.dataset.id, copy: 'yes' });
                if (r.ok) return;                     // как в VBA: при ошибке разделения — дальше обычное сопоставление
            }
        }
        const s2 = sel('gsch'), f2 = sel('gfile');
        if (!s2 || !f2) return;
        if (!(await askYesNo("Сопоставить элемент счёта '" + (rowOf(s2)['Наименование'] ?? '') + "' с элементом файла '" + (rowOf(f2).Naim ?? '') + "'?"))) return;
        act({ act: 'Assign', pos: s2.dataset.id, str: f2.dataset.id, copy: 'no' });
    });
    // − DelAssign_Click: по текущей позиции счёта (её выделяет щелчок по строке файла)
    T('DelAssign').addEventListener('click', () => {
        const f = sel('gfile'), s = sel('gsch');
        if (!T('gfile').tBodies[0].rows.length || !f) return;
        act({ act: 'Unassign', pos: s?.dataset.id ?? 0, str: f.dataset.id });
    });
    // ⇩ Put2Nakl_Click
    T('Put2Nakl').addEventListener('click', () => act({ act: 'Put' }));
    // ✕ ClsFrm_Click; накладная после закрытия перечитывает строки (SetRS4PosShc)
    T('ClsFrm').addEventListener('click', () => closeAccessDialog(true));
})();
JS, View::POS_END);
