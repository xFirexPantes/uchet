<?php
/**
 * Правка шапки счёта и действия (шаги Б1, Б2). Как в Access: поле сохраняется сразу после изменения (AfterUpdate),
 * флажки и кнопки зовут свои процедуры. MsgBox → alert, вопрос Да/Нет → confirm.
 *
 * @var yii\web\View $this
 * @var int $id
 */
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$saveUrl  = Json::encode(Url::to(['save', 'id' => $id]));
$actUrl   = Json::encode(Url::to(['act', 'id' => $id]));
$firmUrl  = Json::encode(Url::to(['firm', 'id' => $id]));
$firmsUrl = Json::encode(Url::to(['firms']));
$posElemUrl = Json::encode(Url::to(['pos-elem', 'id' => $id]));
$fndElemUrl = Json::encode(Url::to(['fnd-elem', 'id' => $id]));
$fobnovUrl  = Json::encode(Url::to(['fobnov/view', 'id' => $id]));
$posDetalUrl = Json::encode(Url::to(['pos-detal', 'id' => $id]));
$naklNewUrl = Json::encode(Url::to(['nakl/view', 'sch' => $id, 'edit' => 1, 'new' => 1]));
$this->registerJs(<<<JS
(function () {
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    // Все запросы идут по очереди: закрытие счёта ждёт, пока сохранится последнее изменённое поле
    let queue = Promise.resolve();
    function post(url, data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => {
            if (v && typeof v === 'object') Object.entries(v).forEach(([kk, vv]) => fd.append(k + '[' + kk + ']', vv));
            else fd.append(k, v ?? '');
        });
        if (csrfParam) fd.append(csrfParam, csrfToken);
        const p = queue.then(() => fetch(url, { method: 'POST', body: fd })).then(r => {
            if (!r.ok) return r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) }));
            return r.json();
        });
        queue = p.catch(() => {});
        return p;
    }
    const byField = f => document.querySelector('.ed[data-field="' + CSS.escape(f) + '"]');

    // --- Сохранение поля при изменении
    document.querySelectorAll('.ed:not([data-kind=search]):not([data-kind=localdate]):not([data-kind=path])').forEach(el => {
        el.dataset.old = el.value;
        el.addEventListener('change', async () => {
            if (el.type === 'radio' && !el.checked) return;
            const data = { field: el.dataset.field, value: el.value };
            // SumDost/DopSum_AfterUpdate: AddNDS — «Указанная сумма с НДС?» (по умолчанию «Нет»: НДС 22% добавится)
            if ((el.dataset.field === 'SumDost' || el.dataset.field === 'DopSum') && el.value.trim() !== '' && parseFloat(el.value.replace(',', '.')) !== 0) {
                data.withNds = (await askYesNo('Указанная сумма с НДС?', { def: 'no' })) ? '1' : '0';
            }
            el.classList.add('saving'); el.classList.remove('err');
            post($saveUrl, data).then(async res => {
                el.classList.remove('saving');
                if (res.set) Object.entries(res.set).forEach(([f, v]) => {
                    const t = byField(f) || document.getElementById(f);
                    if (t) { t.value = v ?? ''; t.dataset.old = t.value; }
                });
                if (res.lockDst !== undefined) { const d = byField('Dst'); if (d) d.disabled = res.lockDst; }
                if (res.ok) { el.dataset.old = el.value; }
                else if (el.type === 'radio') { location.reload(); return; }
                else if (!res.set || !(el.dataset.field in res.set)) { el.value = el.dataset.old; el.classList.add('err'); }
                if (res.msg) await msgBox(res.msg);
                if (res.reload) location.reload();
            }).catch(() => { el.classList.remove('saving'); el.classList.add('err'); el.value = el.dataset.old; msgBox('Не удалось сохранить', { icon: 'error' }); });
        });
        // Enter в текстовом поле = сохранить (как переход к следующему полю в Access)
        if (el.tagName === 'INPUT') el.addEventListener('keydown', e => { if (e.key === 'Enter') el.blur(); });
    });

    // --- Дата «Отдано в оплату»: Delete очищает (Dta2Opl_KeyDown)
    const dta2 = byField('Dta2Opl');
    dta2?.addEventListener('keydown', e => { if (e.key === 'Delete') { e.preventDefault(); dta2.value = ''; dta2.dispatchEvent(new Event('change')); } });

    // --- Путь к счёту (frmScetaDetal: pth_KeyDown, AdditPth_KeyDown): Delete — спросить и удалить
    document.querySelectorAll('.ed[data-kind=path]').forEach(el => el.addEventListener('keydown', e => {
        if (e.key !== 'Delete') return;
        e.preventDefault();
        act(el.dataset.field === 'AdditPth' ? 'AdditPthDel' : 'PthDel');
    }));

    // --- InUEtxt_DblClick: смена валюты у пустого счёта (окно «Валюта», frmfndDezWorks 61)
    document.getElementById('InUEtxt')?.addEventListener('dblclick', () => act('InUEtxt'));

    // --- Флажки и кнопки-действия. Вопросы сервера (ask) задаём confirm и повторяем запрос с ответом.
    function act(name, extra = {}, answers = {}) {
        return post($actUrl, Object.assign({ act: name, answers }, extra)).then(async res => {
            if (res.ask) {
                if (res.msg) await msgBox(res.msg);
                answers[res.ask.key] = (await askYesNo(res.ask.text)) ? '1' : '0';
                return act(name, extra, answers);
            }
            // сервер просит выбрать значение в окне выбора (как DoCmd.OpenForm ..., acDialog, режим)
            if (res.pick) {
                return accessPicker(res.pick.form, res.pick.mode).then(sel => {
                    if (!sel) { if (res.pick.empty) return msgBox(res.pick.empty).then(() => res); return res; }
                    return act(name, Object.assign({}, extra, { [res.pick.field]: sel.key }), answers);
                });
            }
            if (res.msg) await msgBox(res.msg);
            if (res.close) { closeCard(true); return res; }
            if (res.reload) location.reload();
            return res;
        });
    }
    // dd.mm.yyyy → yyyy-mm-dd (как adhDoCalendar в Access: дата обязательна, Отмена = ничего не делать)
    async function askDate() {
        const t = new Date(), d = String(t.getDate()).padStart(2, '0') + '.' + String(t.getMonth() + 1).padStart(2, '0') + '.' + t.getFullYear();
        const s = await inputBox('Дата (дд.мм.гггг)', d); if (!s) return null;
        const m = s.trim().match(/^(\\d{1,2})\\.(\\d{1,2})\\.(\\d{4})$/);
        if (!m) { await msgBox('Неверная дата', { icon: 'warn' }); return null; }
        return m[3] + '-' + m[2].padStart(2, '0') + '-' + m[1].padStart(2, '0');
    }
    document.querySelectorAll('.act').forEach(el => {
        el.addEventListener('click', async e => {
            const name = el.dataset.act;
            if (el.type === 'checkbox') e.preventDefault();          // флажок меняет сервер, потом карточка перечитывается
            if (name === 'TakeMe') {
                const on = el.checked;                                // в click checked уже новое значение (preventDefault вернёт старое)
                if (on) {
                    const date = await askDate(); if (!date) return;
                    const time = (await inputBox('Введите время (ЧЧММ), можно пусто')) ?? '';
                    act(name, { on: '1', date, time });
                } else act(name, { on: '0' });
                return;
            }
            // flgFZ275_MouseUp: ставим флажок — сначала выбрать заказ (frmfndFirm, режим 50), снимаем — сразу
            if (name === 'flgFZ275' && el.checked) {
                accessPicker('frmfndFirm', 50).then(sel => { if (sel) act(name, { num: sel.key }); });
                return;
            }
            // MassSetDTPost / MassSetDTDP: дата из свободного поля рядом (DTPost / DPDT) всем строкам счёта на детали
            if (name === 'MassSetDTPost' || name === 'MassSetDTDP') {
                const src = document.getElementById(name === 'MassSetDTPost' ? 'DTPost' : 'DPDT');
                act(name, { date: src ? src.value : '' });
                return;
            }
            if (name === 'AllNakl') { location.reload(); return; }          // UpdateList(ID, 1): перечитать накладные
            // DelRec: выбранная позиция, а если не выбрана — первая, как текущая запись Access
            if (name === 'DelRec') {
                const tb = document.querySelector('#pos tbody');
                const tr = tb?.querySelector('tr.sel') || tb?.rows[0];
                if (!tr) { msgBox('Нет позиций для удаления'); return; }
                act(name, { pos: tr.dataset.id });
                return;
            }
            // AddNakl_Click: проверки и создание на сервере (вопрос «Есть excel-файл?»), новая накладная открывается для правки
            if (name === 'AddNakl') {
                document.activeElement?.blur();
                act(name).then(res => {
                    if (!res || !res.ok || !res.nakl) return;
                    accessDialog($naklNewUrl + '&id=' + encodeURIComponent(res.nakl), 1243, $id > 0 ? 804 : 641).then(() => location.reload());
                });
                return;
            }
            if (name === 'butExit' || name === 'butSaveAs') document.activeElement?.blur();  // сначала сохранить поле, в котором стоит курсор
            act(name);
        });
    });

    // --- ✎ Кнопка15_Click: режим окна (есть накладная — вопрос), потом окно frmPosInput; после ✓ карточка перечитывается
    document.getElementById('Кнопка15')?.addEventListener('click', () => {
        const tb = document.querySelector('#pos tbody'), tr = tb?.querySelector('tr.sel') || tb?.rows[0];
        if (!tr) { msgBox('Выберите элемент для редактирования'); return; }
        act('EdtPos', { pos: tr.dataset.id }).then(res => {
            if (!res || !res.mode) return;
            accessDialog($posElemUrl + '&pos=' + encodeURIComponent(tr.dataset.id) + '&mode=' + res.mode, 700, 480)
                .then(r => { if (r.reload) location.reload(); });
        });
    });

    // --- ＋ butDob_Click: CheckNumSch и вопрос «облегчённая версия?» (сервер), окно поиска frmFndElem или fObnov → SkladID,
    // проверка фирмы-покупателя (xp_GetWhatFirmSch, только предупреждение), окно frmPosInput в режиме 5; после ✓ карточка перечитывается
    document.getElementById('butDob')?.addEventListener('click', async () => {
        document.activeElement?.blur();
        const res = await act('butDob');
        if (res && res.det) {                                  // счёт на детали: окно позиции frmScetaDetalAdd (IDPos 0), потом UpdateListDetal
            const r = await accessDialog($posDetalUrl + '&pos=0', 660, 250);
            if (r.reload) location.reload();
            return;
        }
        if (!res || !(res.light || res.full)) return;
        // fObnov (1336 × 505) — в главном окне, если карточка сама открыта внутри окна
        let dlg = accessDialog;
        try { if (window.top !== window && window.top.accessDialog) dlg = window.top.accessDialog; } catch (e) {}
        const f = res.light ? await accessDialog($fndElemUrl, 1096, 313) : await dlg($fobnovUrl, 1336, 505);
        if (!f.value) return;
        const chk = await act('AddPos', { sklad: f.value });
        if (!chk || !chk.sklad) return;
        const r = await accessDialog($posElemUrl + '&mode=5&sklad=' + encodeURIComponent(chk.sklad), 700, 480);
        location.reload();                                    // UpdateList + RecordSource: перечитать в любом случае, как Access
    });

    // --- Поиск фирмы (Fnd → FillListINN) и выбор двойным щелчком (xListINN_DblClick)
    const fnd = document.querySelector('.ed[data-kind=search]'), firms = document.getElementById('firms');
    let timer;
    fnd?.addEventListener('input', () => {
        clearTimeout(timer);
        if (fnd.value === '') return;                     // как fnd_Change: пустую строку не ищем
        timer = setTimeout(() => {
            fetch($firmsUrl + '?q=' + encodeURIComponent(fnd.value)).then(r => r.json()).then(rows => {
                const b = firms.tBodies[0]; b.innerHTML = '';
                rows.forEach(r => {
                    const tr = b.insertRow(); tr.dataset.id = r.ID;
                    [r.Naim, r.INN].forEach(v => { const td = tr.insertCell(); td.textContent = v ?? ''; td.title = v ?? ''; });
                });
            });
        }, 300);
    });
    firms?.tBodies[0].addEventListener('dblclick', e => {
        const tr = e.target.closest('tr'); if (!tr) return;
        post($firmUrl, { firmId: tr.dataset.id }).then(async res => {
            if (res.msg) await msgBox(res.msg);
            if (res.reload) location.reload();
        });
    });
    firms?.closest('.a-slot')?.setAttribute('title', 'Двойной щелчок: выбрать контрагента для счёта');
})();
JS, View::POS_END);
