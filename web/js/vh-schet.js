/**
 * Экран «Входящие счета» (форма Access frmStat), вид «как в Access».
 * Раньше скрипт лежал прямо в modules/zakupki/views/vh-schet/index.php (heredoc) — вынесен сюда,
 * чтобы один и тот же код работы со счётом могли использовать оба вида экрана (старый и современный).
 *
 * Настройки приходят из шаблона: перед подключением файла экран кладёт в window.VH объект
 *   { panelUrl, actUrl, primUrl, getPrcUrl, whoBuyUrl, partUrl, pthUrl, fltUrl, chgUrl, mailUrl,
 *     postUrl, srokUrl, docsUrl, platUrl, tovUrl, actsUrl, dlgActUrl, schetUrl, csrfP, csrfT,
 *     typeSch, fltOn }
 * — адреса действий (Url::to), имя и значение CSRF-токена, тип счёта (GrpTypeSch) и признак
 * применённого фильтра. Шаблон также подключает: colresize.js, picker.js, access-dialog.js,
 * interval.js, msgbox.js — ими пользуется этот файл.
 *
 * Имена функций и переменных — из VBA формы (xListSch_ColumnClick, SetRowSOplPrc, AddOpl_Click,
 * SelResKompel и т.д.): по ним ищется исходная логика в выгрузке формы.
 */
(function () {
    'use strict';

    const VH = window.VH || {};
    const tbl = document.getElementById('vhList');
    if (!tbl) return;   // на странице нет списка счетов (другой вид экрана) — скрипт не нужен
    const body = tbl.tBodies[0];
    const byId = id => document.getElementById(id);
    // Современный вид (Bootstrap-раскладка) и вид «как в Access» используют одну разметку по id,
    // но по-разному показывают элементы и подсвечивают суммы — здесь решаем, какой стиль применить.
    const MODERN = !!document.querySelector('.vh-modern');
    // Вкладки панели современного вида. Bootstrap CSS подключён, а JS-плагины — нет
    // (они тянут jQuery), поэтому переключение вкладок делаем здесь: те же классы, что у Bootstrap.
    if (MODERN) {
        const tabs = document.querySelector('.vh-modern-tabs');
        tabs?.addEventListener('click', e => {
            const btn = e.target.closest('[data-bs-target]'); if (!btn) return;
            e.preventDefault();
            tabs.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const box = tabs.parentElement;
            box.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show', 'active'));
            box.querySelector(btn.dataset.bsTarget)?.classList.add('show', 'active');
        });
    }
    // Видимые колонки списков: из ColumnWidths в Access (ширина 0 = скрытая колонка)
    const show = { lstOplPrc: [0, 1, 3], lstOplPrcToDo: [1, 2, 3, 4, 5], lstPlat: [1, 2, 3, 5] };

    // --- Сортировка по заголовку (xListSch_ColumnClick). Даты сортируются по скрытым Lng-полям.
    function sortBy(col, desc) {
        const rows = [...body.rows];
        const num = rows.every(r => r.cells[col].dataset.sort === '' || !isNaN(r.cells[col].dataset.sort));
        rows.sort((a, b) => {
            const x = a.cells[col].dataset.sort, y = b.cells[col].dataset.sort;
            const d = num ? (Number(x) - Number(y)) : x.localeCompare(y, 'ru');
            return desc ? -d : d;
        });
        rows.forEach(r => body.appendChild(r));
        tbl.querySelectorAll('th').forEach(th => th.classList.remove('asc', 'desc'));
        tbl.tHead.rows[0].cells[col].classList.add(desc ? 'desc' : 'asc');
    }
    // --- Клик по заголовку. xListSch_ColumnClick: колонка «Оплата» (hd = 12) — не сортировка,
    // а RemoveDataSch: скрыть строки с заполненным HowOpl (оставить только неоплаченные), OnlyNeopl.
    // Повторный щелчок возвращает полный список.
    const COL_OPL = 11;                                   // индекс колонки «Оплата» в _list.php
    let onlyNeopl = false;
    function removeDataSch() {
        onlyNeopl = !onlyNeopl;
        let first = null;
        for (const r of body.rows) {
            const paid = (r.cells[COL_OPL].dataset.sort || '').trim() !== '';
            r.hidden = onlyNeopl && paid;
            if (!r.hidden && !first) first = r;
        }
        tbl.tHead.rows[0].cells[COL_OPL].classList.toggle('neopl', onlyNeopl);
        // выбранная строка могла скрыться — выбираем первую видимую, как после FillListSch
        const sel = body.querySelector('tr.sel');
        if (first && (!sel || sel.hidden)) select(first);
        else if (!first) { vis('ActBut', false); vis('RepBut', false); }
    }
    tbl.tHead.addEventListener('click', e => {
        if (e.target.classList.contains('rsz') || tbl.dataset.resizing) return;
        const th = e.target.closest('th'); if (!th) return;
        if (+th.dataset.col === COL_OPL) { removeDataSch(); return; }
        sortBy(+th.dataset.col, th.classList.contains('asc'));
    });

    // --- Раздвигание столбцов (общий web/js/colresize.js). Ключ разный: в современном виде таблица
    // в узкой колонке и ширины подбираются заново, иначе ширины из вида «как в Access» её раздувают.
    colResize(tbl, MODERN ? 'vhSchet.list.modern' : 'vhSchet.list');
    // usersrt: сортировка по пользователю (FillListSch(1): SortKey = 12, по возрастанию)
    byId('usersrt')?.addEventListener('click', () => sortBy(10, false));

    // --- Списки панели (RowSource у ListBox)
    // Счётчик на вкладке современного вида: сколько строк в списке. В Access это не показывалось,
    // поэтому в виде «как в Access» счётчиков нет.
    function count(id, n) {
        if (!MODERN) return;
        const el = document.querySelector('[data-count="' + id + '"]');
        if (el) el.textContent = n;
    }
    function fill(id, rows) {
        const t = byId(id); if (!t) return; t.innerHTML = '';
        rows.forEach(r => {
            const tr = t.insertRow(); tr.dataset.row = JSON.stringify(r);
            show[id].forEach(i => tr.insertCell().textContent = r[i] ?? '');
        });
        if (MODERN && !rows.length) emptyRow(t, show[id].length);
        count(id, rows.length);
    }
    // Пустая вкладка в современном виде: в Access список просто пустой, здесь — понятная подпись
    function emptyRow(t, span) {
        const tr = t.insertRow(), td = tr.insertCell();
        td.colSpan = span || 1; td.className = 'text-secondary fst-italic'; td.textContent = 'нет данных';
    }
    // lstOplPrc_AfterUpdate: CommOpl = скрытая колонка 4 выбранной оплаты
    byId('lstOplPrc')?.addEventListener('click', e => {
        const tr = e.target.closest('tr'); if (!tr || !tr.dataset.row) return;   // строка «нет данных»
        byId('lstOplPrc').querySelectorAll('tr.sel').forEach(r => r.classList.remove('sel')); tr.classList.add('sel');
        byId('CommOpl').value = JSON.parse(tr.dataset.row)[4] ?? '';
    });

    // --- Подформа договоров lstDogs (subfrmFObnovlen1_01, таблица). Колонки и ширины из SaveAsText
    // (ColumnWidth в твипах / 15). IspID, UchetID, IsActive скрыты, как ColumnHidden в Access.
    // Условное форматирование: у активного договора (IsActive = True) «Дата дог» на голубом фоне.
    const dogCols = [['NumDog', '№ дог', 90], ['DataDog', 'Дата дог', 59], ['SrokWar', 'Срок гарантии,мес.', 82], ['Org', 'Организация', 164], ['DescrDog', 'Описание', 110]];
    function fillDogs(rows) {
        const t = byId('lstDogs');
        if (!t.tHead) {                                   // шапка и ширины один раз, потом меняем только строки
            const cg = document.createElement('colgroup');
            dogCols.forEach(c => { const col = document.createElement('col'); col.style.width = c[2] + 'px'; cg.appendChild(col); });
            t.appendChild(cg);
            const h = t.createTHead().insertRow();
            dogCols.forEach(c => { const th = document.createElement('th'); th.textContent = c[1]; h.appendChild(th); });
            colResize(t, 'vhSchet.dogs');
        }
        if (t.tBodies[0]) t.tBodies[0].remove();
        const b = t.createTBody();
        rows.forEach(r => {
            const tr = b.insertRow();
            dogCols.forEach(c => {
                const td = tr.insertCell(); td.textContent = r[c[0]] ?? '';
                if (c[0] === 'DataDog' && (r.IsActive === true || r.IsActive == 1)) td.style.background = '#99ccff';
            });
        });
        if (MODERN && !rows.length) emptyRow(b, dogCols.length);
        count('lstDogs', rows.length);
        vis('lstDogsBox', true);   // в Access SetRS4DogScet всегда делает подформу видимой
    }

    // --- Выбор строки (xListSch_ItemClick + SetRowSOplPrc)
    const set = (id, v) => { const el = byId(id); if (el) el.value = v ?? ''; };
    // Скрытие элемента: в Access-раскладке элементы стоят на координатах и место под них сохранено
    // (visibility), в современной сетке пустое место не нужно — убираем из потока (display).
    const vis = (id, on) => {
        const el = byId(id); if (!el) return;
        if (MODERN) { el.style.display = on ? '' : 'none'; }
        else { el.style.visibility = on ? 'visible' : 'hidden'; }
    };
    function select(tr) {
        body.querySelectorAll('tr.sel').forEach(r => r.classList.remove('sel'));
        tr.classList.add('sel');
        const d = JSON.parse(tr.dataset.row);
        set('PthSch', d.pth); set('txtPrim', d.Prim); set('OstOPl', d.OstOPl); set('SmSch', d.SmSch); set('txtComm', d.Comm);
        set('CommOpl', '');
        byId('Надпись61').textContent = 'Сумма,' + (d.InUEtxt ?? '');
        // По письму Семёнова от 14.01.22: рубли обычным фоном, валюта зелёным жирным.
        // В современном виде «аксессовские» цвета не нужны — валюта помечается классом.
        const sm = byId('SmSch');
        if (sm) {
            const rub = d.InUEtxt === 'руб.';
            if (MODERN) { sm.classList.toggle('vh-sum-ue', !rub); sm.style.background = ''; sm.style.fontWeight = ''; }
            else { sm.style.background = rub ? '#8080c0' : '#ccffcc'; sm.style.fontWeight = rub ? 'normal' : 'bold'; }
        }
        document.querySelectorAll('[name=whoPick]').forEach(r => r.checked = String(r.value) === String(d.WhoPick));
        vis('ActBut', d.Acts === 'Да'); vis('RepBut', d.Reps === 'Да');
        oplButtons(tr);
        fetch(VH.panelUrl + '?id=' + tr.dataset.id + '&typeSch=' + VH.typeSch)
            .then(r => r.json())
            .then(p => {
                fill('lstOplPrc', p.opl); fill('lstOplPrcToDo', p.oplToDo); fill('lstPlat', p.plat);
                set('LastAnswDta', p.answ.dta); set('LastAnsw', p.answ.txt);
                fillDogs(p.dogs);
            });
    }
    body.addEventListener('click', e => { const tr = e.target.closest('tr'); if (tr) select(tr); });

    // --- Двойной щелчок → карточка счёта (xListSch_DblClick): ID > 0 frmMassInputSch, ID < 0 frmScetaDetal.
    // В Access карточка открывалась поверх списка (acDialog); здесь отдельной вкладкой.
    // Двойной щелчок считаем сами, а не через событие dblclick: браузер шлёт dblclick и на дребезг
    // мыши, из-за чего карточка открывалась от одиночного нажатия. Наши условия строже:
    // два щелчка по ОДНОЙ строке не дальше чем в 8 px и не позже 450 мс.
    const DBL_MS = 450, DBL_PX = 8;
    let lastTap = null;                                  // {id, x, y, t}
    body.addEventListener('click', e => {
        const tr = e.target.closest('tr'); if (!tr) return;
        const now = Date.now(), x = e.clientX, y = e.clientY, id = tr.dataset.id;
        const prev = lastTap;
        const dbl = prev && prev.id === id && now - prev.t <= DBL_MS
            && Math.abs(prev.x - x) <= DBL_PX && Math.abs(prev.y - y) <= DBL_PX;
        lastTap = dbl ? null : { id, x, y, t: now };      // третье нажатие начинает новую пару
        if (dbl) window.open(VH.schetUrl + '?id=' + id, 'schet' + id);
    });

    // --- Как в Access: сортировка по дате (LngDta) по убыванию, выбрана первая строка
    sortBy(5, true);
    if (body.rows.length) select(body.rows[0]); else { vis('ActBut', false); vis('RepBut', false); }

    // --- Сохранения (WhoPick_AfterUpdate, PthSch_KeyDown)
    function post(id, data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
        fd.append(VH.csrfP, VH.csrfT);
        return fetch(VH.actUrl + '?id=' + encodeURIComponent(id), { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })));
    }
    const cur = () => body.querySelector('tr.sel');
    function setRow(tr, k, v) { const d = JSON.parse(tr.dataset.row); d[k] = v; tr.dataset.row = JSON.stringify(d); }
    // «Кто забирает»: сохраняется сразу; не удалось — вернуть прежнее значение (WhoPick.Undo)
    document.querySelectorAll('[name=whoPick]').forEach(r => r.addEventListener('change', async () => {
        const tr = cur();
        const back = () => document.querySelectorAll('[name=whoPick]').forEach(x => x.checked = tr && String(x.value) === String(JSON.parse(tr.dataset.row).WhoPick));
        if (!tr) { back(); return; }
        const res = await post(tr.dataset.id, { act: 'WhoPick', value: r.value });
        if (res.ok) setRow(tr, 'WhoPick', r.value); else { back(); if (res.msg) await msgBox(res.msg, { icon: 'warn' }); }
    }));
    // PthSch_DblClick: путь есть — скачать файл; нет — выбрать файл, он копируется в GetAdr(20)+фирма, путь сохраняется
    byId('PthSch')?.addEventListener('dblclick', async () => {
        const tr = cur(); if (!tr) return;
        if (byId('PthSch').value) {
            const r = await fetch(VH.pthUrl + '?id=' + encodeURIComponent(tr.dataset.id));
            if (!r.ok || (r.headers.get('content-type') || '').includes('json')) {
                const j = await r.json().catch(() => ({}));
                await msgBox(j.msg || j.message || ('Ошибка ' + r.status), { icon: 'warn' }); return;
            }
            const a = document.createElement('a'); a.href = URL.createObjectURL(await r.blob());
            a.download = byId('PthSch').value.split(/[\\\/]/).pop() || 'file';
            document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 10000);
            return;
        }
        const inp = document.createElement('input'); inp.type = 'file';
        inp.onchange = async () => {
            if (!inp.files[0]) return;
            const res = await post(tr.dataset.id, { act: 'PthSet', file: inp.files[0] });
            if (res.ok) { setRow(tr, 'pth', res.pth); byId('PthSch').value = res.pth; }
            else if (res.msg) await msgBox(res.msg, { icon: res.icon || 'info' });
        };
        inp.click();
    });
    // Путь к счёту: Delete — удалить (после вопроса, по умолчанию «Нет»)
    byId('PthSch')?.addEventListener('keydown', async e => {
        if (e.key !== 'Delete') return;
        e.preventDefault();
        const tr = cur(); if (!tr || !byId('PthSch').value) return;
        if (!(await askYesNo('Удалить путь к счёту?', { def: 'no' }))) return;
        const res = await post(tr.dataset.id, { act: 'PthDel' });
        if (res.ok) { setRow(tr, 'pth', ''); byId('PthSch').value = ''; } else if (res.msg) await msgBox(res.msg, { icon: 'warn' });
    });

    // --- Оплаты: строка «К оплате» выбирается щелчком (как lstOplPrc)
    byId('lstOplPrcToDo')?.addEventListener('click', e => {
        const tr = e.target.closest('tr'); if (!tr || !tr.dataset.row) return;
        byId('lstOplPrcToDo').querySelectorAll('tr.sel').forEach(r => r.classList.remove('sel')); tr.classList.add('sel');
    });
    // ответ сервера: row_html — строка счёта заново (ChangeDataInList), panel — списки панели заново (SetRowSOplPrc)
    async function applyRes(tr, res) {
        if (res.msg) await msgBox(res.msg, { icon: res.ok ? 'info' : 'warn' });
        if (res.row_html) {
            const tmp = document.createElement('table'); tmp.innerHTML = res.row_html.replace(/^[\s\S]*?<tbody>|<\/tbody>[\s\S]*$/g, '');
            const nr = tmp.querySelector('tr');
            if (nr) { tr.replaceWith(nr); tr = nr; }
        }
        if (res.row_html || res.panel) select(tr);
        return tr;
    }
    function oplButtons(tr) {                          // xListSch_ItemClick: ForeColor «Отдано» = 10079487 → + и − оплат недоступны
        const blocked = tr && +(JSON.parse(tr.dataset.row).OplPrc ?? 0) === 10079487;
        const del = byId('DelOplPrc'); if (del) { del.disabled = blocked; del.title = blocked ? 'Удалить оплату: недоступно для этого счёта' : 'Удалить выбранную оплату'; }
        const add = byId('AddOpl'); if (add) { add.disabled = blocked; add.title = blocked ? 'Добавить оплату: недоступно для этого счёта' : 'Добавить оплату'; }
    }
    // ✓ ClsByPlat_Click
    byId('ClsByPlat')?.addEventListener('click', async () => {
        const tr = cur(); if (!tr) return;
        if (!(await askYesNo('Изменить состояние счёта?', { def: 'no' }))) return;
        applyRes(tr, await post(tr.dataset.id, { act: 'ClsByPlat' }));
    });
    // − DelOplPrc_Click (без вопроса, как в Access)
    byId('DelOplPrc')?.addEventListener('click', async () => {
        const tr = cur(), o = byId('lstOplPrc').querySelector('tr.sel');
        if (!tr) return;
        if (!o) { await msgBox('Не выбрана строка для удаления'); return; }
        applyRes(tr, await post(tr.dataset.id, { act: 'DelOplPrc', opl: JSON.parse(o.dataset.row)[2] ?? 0 }));
    });
    // − DelOplToDo_Click
    byId('DelOplToDo')?.addEventListener('click', async () => {
        const tr = cur(), o = byId('lstOplPrcToDo').querySelector('tr.sel');
        if (!tr) return;
        if (!o) { await msgBox('Не выбрана строка для удаления'); return; }
        if (!(await askYesNo("Удалить строку 'К оплате'?", { def: 'no' }))) return;
        applyRes(tr, await post(tr.dataset.id, { act: 'DelOplToDo', todo: JSON.parse(o.dataset.row)[0] ?? 0, typeSch: VH.typeSch }));
    });

    // --- ＋ AddOpl_Click: договоры → «требующие внимания» → xp_IsCorrOplPrc → frmGetPrcSch (0) → xp_AddOplPrcNew → письма
    byId('AddOpl')?.addEventListener('click', async () => {
        let tr = cur(); if (!tr) return;
        const id = tr.dataset.id;
        const p = await post(id, { act: 'OplPrep' });
        if (!p.ok) { if (p.msg) await msgBox(p.msg, { icon: 'warn' }); return; }
        if (p.dog) await msgBox(p.dog);                                    // fnSkladDogsAllowScet(ID, 2, True): только сообщение
        if (p.who) {                                                       // оплат ещё нет, а xp_WhoBuySch что-то вернула: frmFndPR 11
            const w = await accessDialog(VH.whoBuyUrl + '?id=' + encodeURIComponent(id), 880, 445);
            if (w.value !== -1) return;
        }
        let allowZero = false;
        switch (p.corr) {
            case -1:
                if (!(await askYesNo('Команды к оплате полностью покрывают счёт. Вы уверены, что хотите добавить ещё запись?', { def: 'no' }))) return;
                break;
            case -2:
                if (await askYesNo('Счёт оплачен на сумму большую, чем установлено командами к оплате. Ввести корректирующую запись?')) {
                    const k = await post(id, { act: 'OplKorr' });
                    if (!k.ok) { await msgBox(k.msg || 'Не удалось ввести корректирующую запись'); return; }
                }
                break;
            case -3: allowZero = true; break;
            case 2: await msgBox('У Вас нет прав на данное действие'); break;    // в Access дальше не останавливается
            case -4:
                if (!(await askYesNo('Сумма счёта превышает ' + p.a173 + ' р. Всё равно отдать в оплату?'))) return;
                break;
            case -5: case -6:
                if (!(await askYesNo('Сумма счёта превышает ' + p.a174 + ' у.е. Всё равно отдать в оплату?'))) return;
                if (p.corr === -6) allowZero = true;
                break;
        }
        const g = await accessDialog(VH.getPrcUrl + '?id=' + encodeURIComponent(id) + '&mode=0', 255, 75);
        if (!g.reload || !g.value) return;                                 // «отменить»: Prc = -1
        const prc = g.value.prc;
        if (prc === 0 && !allowZero) return;
        const res = await post(id, { act: 'OplAdd', prc, ovr: g.value.ovr });
        tr = await applyRes(tr, res);
        if (res.askMail) {
            const it = await askYesNo('Отправить письмо об оплате?');
            const m = await post(id, { act: 'OplMail', prc, it: it ? 1 : 0 });
            if (!m.ok && m.msg) await msgBox(m.msg, { icon: 'warn' });
            for (const s of (m.msgs || [])) await msgBox(s);
        }
    });
    // --- ＋ AddOplToDo_Click: xp_IsCorrOplPrc → заготовка строки → frmGetPrcSch (2 — счёт на элементы, 4 — на детали)
    byId('AddOplToDo')?.addEventListener('click', async () => {
        const tr = cur(); if (!tr) return;
        const id = tr.dataset.id;
        const p = await post(id, { act: 'ToDoPrep' });
        if (!p.ok) { if (p.msg) await msgBox(p.msg, { icon: 'warn' }); return; }
        if (p.corr === -4 && !(await askYesNo('Сумма счёта превышает ' + p.a173 + ' р. Всё равно завести строку?'))) return;
        if ((p.corr === -5 || p.corr === -6) && !(await askYesNo('Сумма счёта превышает ' + p.a174 + ' у.е. Всё равно завести строку?'))) return;
        const n = await post(id, { act: 'ToDoNew' });
        if (!n.ok) { await msgBox(n.msg || 'Не удалось добавить запись'); return; }
        const mode = +id < 0 ? 4 : 2;
        // вкладку закрыли при открытом окне — черновик строки всё равно удалить
        const drop = () => { const fd = new FormData(); fd.append('act', 'ToDoDel'); fd.append('todo', n.todo); fd.append(VH.csrfP, VH.csrfT); navigator.sendBeacon(VH.actUrl + '?id=' + encodeURIComponent(id), fd); };
        window.addEventListener('pagehide', drop);
        const r = await accessDialog(VH.getPrcUrl + '?id=' + encodeURIComponent(id) + '&mode=' + mode + '&todo=' + n.todo, 347, 189);
        window.removeEventListener('pagehide', drop);
        if (r.value !== 'saved') {                                         // «отменить» или ✕/Esc: заготовку удалить (--del--)
            const d = await post(id, { act: 'ToDoDel', todo: n.todo });
            if (d.msg) await msgBox(d.msg);
        }
        select(cur() || tr);                                               // lstOplPrcToDo.RowSource = lstOplPrcToDo.RowSource
    });

    // --- «Долги» (GetPartSums_Click): покупатель (frmFndFirm 9) → xp_GetPartSums → файл Excel
    byId('GetPartSums')?.addEventListener('click', async () => {
        const f = await accessPicker('frmfndFirm', 9);
        if (!f || !f.key) return;
        const b = byId('GetPartSums'); b.disabled = true; document.body.style.cursor = 'wait';   // DoCmd.Hourglass True
        try {
            const r = await fetch(VH.partUrl + '?uchet=' + encodeURIComponent(f.key));
            if ((r.headers.get('content-type') || '').includes('json') || !r.ok) {
                const j = await r.json().catch(() => ({}));
                await msgBox(j.msg || j.message || ('Ошибка ' + r.status), { icon: r.ok ? 'info' : 'warn' });
                return;
            }
            const name = decodeURIComponent((r.headers.get('content-disposition') || '').match(/filename\*=utf-8''([^;]+)/i)?.[1] || 'Долги.xlsx');
            const a = document.createElement('a'); a.href = URL.createObjectURL(await r.blob()); a.download = name;
            document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 10000);
        } finally { b.disabled = false; document.body.style.cursor = ''; }
    });

    // --- Примечание (✎ EditPrim, frmShowPrim 44) и комментарий (двойной щелчок, frmShowPrim 83)
    async function editText(mode) {
        const tr = cur();
        if (!tr) { await msgBox('Не выбран счёт'); return; }
        const d = JSON.parse(tr.dataset.row), f = mode === 44 ? 'Prim' : 'Comm';
        const r = await accessDialog(VH.primUrl + '?id=' + encodeURIComponent(tr.dataset.id) + '&mode=' + mode + '&t=' + encodeURIComponent(d[f] ?? ''), 455, 236);
        if (!r.reload || r.value == null) return;
        setRow(tr, f, r.value);
        if (mode === 44) { byId('txtPrim').value = r.value; const c = tr.cells[1]; if (c) { c.textContent = r.value; c.dataset.sort = r.value; } }
        else { byId('txtComm').value = r.value; const c = tr.cells[16]; if (c) { c.textContent = r.value; c.dataset.sort = r.value; } }
    }
    byId('EditPrim')?.addEventListener('click', () => editText(44));
    byId('txtComm')?.addEventListener('dblclick', () => editText(83));

    // ================= Окна второй очереди (контроллер vh-dlg) =================
    function dlgPost(id, data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => fd.append(k + '[]', x)) : fd.append(k, v ?? ''));
        fd.append(VH.csrfP, VH.csrfT);
        return fetch(VH.dlgActUrl + '?id=' + encodeURIComponent(id), { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })));
    }
    const qs = o => '?' + new URLSearchParams(o);
    const rowOf = tr => JSON.parse(tr.dataset.row);
    // перечитать строку счёта (ChangeDataInList) и, если надо, панель
    async function refreshRow(tr) { if (tr) return applyRes(tr, await dlgPost(tr.dataset.id, { act: 'Row' })); }

    // «Интервал» (frmGetInterval): даты в поля и сразу поиск (butF_Click)
    byId('GetIntrv')?.addEventListener('click', async () => {
        const r = await getInterval();
        if (!r) return;
        document.querySelector('[name=dateS]').value = r[0];
        document.querySelector('[name=datePo]').value = r[1];
        byId('frmStat').submit();
    });
    // ▼ «фильтр по счетам» (frmfltSch 0): фирма и даты; применён — поиск заново (IsChgflt → butF_Click)
    byId('flt')?.addEventListener('click', async () => {
        const f = byId('frmStat'), applied = VH.fltOn;
        const cur = applied ? { firma: byId('cboFirma').value, dateS: f.dateS.value, datePo: f.datePo.value } : {};
        const r = await accessDialog(VH.fltUrl + qs(cur), 287, 129);
        if (!r.reload || !r.value) return;
        byId('cboFirma').value = r.value.firma; f.dateS.value = r.value.dateS; f.datePo.value = r.value.datePo;
        f.submit();
    });
    // «Ист» (GetIDs4SelSch): номера счетов списка → tmpIDs, затем «История изменений» (frmChgPredShcetaHistory 1)
    byId('btnShowLogPrices')?.addEventListener('click', async () => {
        const ids = [...body.rows].map(r => r.dataset.id);
        if (!ids.length) return;
        const r = await dlgPost(0, { act: 'ChgList', ids });
        if (!r.ok) { if (r.msg) await msgBox(r.msg, { icon: 'warn' }); return; }
        await accessDialog(VH.chgUrl + qs({ rgm: 1 }), 1433, 426);
    });
    // ✉ MakeEmail_Click: адрес и вопрос из xp_GetEmailBySch, затем окно письма (frmGetTxt4Mail, Tip 0)
    byId('MakeEmail')?.addEventListener('click', async () => {
        const tr = cur(); if (!tr) return;
        const e = await dlgPost(tr.dataset.id, { act: 'Email' });
        if (!e.ok) { if (e.msg) await msgBox(e.msg, { icon: 'warn' }); return; }
        if (e.ask && !(await askYesNo(e.ask))) return;
        const r = await accessDialog(VH.mailUrl + qs({ id: tr.dataset.id, tip: 0, email: e.email || '' }), 911, 503);
        if (r.reload) await refreshRow(tr);
    });
    // «Сроки поставки» (KompelSrokBut_Click): поставщик и файл выбираются в окне frmChoice4InControl 101
    byId('KompelSrokBut')?.addEventListener('click', () => accessDialog(VH.srokUrl, 957, 107));
    // «Сроки КОМПЭЛ» (SpokKompelbtn_Click): файл грузится сразу (Tip 6), затем SelResKompel — для каждой строки
    // tmptblKOMPELSrokNew по очереди открывается frmFndPR 155, выбранный резерв уходит в xp_SetReserv4Kompel.
    // Как в Access: отмена выбора (KodNullEl пуст) прерывает цикл.
    byId('SpokKompelbtn')?.addEventListener('click', () => {
        const inp = document.createElement('input'); inp.type = 'file'; inp.accept = '.xls,.xlsx';
        inp.onchange = async () => {
            if (!inp.files[0]) return;
            const b = byId('SpokKompelbtn'); b.disabled = true; document.body.style.cursor = 'wait';
            try {
                const r = await post(0, { act: 'KompelLoad', file: inp.files[0] });
                if (!r.ok) { await msgBox(r.msg || 'Ошибка загрузки', { icon: r.icon || 'warn' }); return; }
                const ids = r.ids || [];
                for (let i = 0; i < ids.length; i++) {
                    const p = await accessPicker('frmfndPR', 155, ids[i]);
                    if (!p || !p.key) {                                   // отмена: остальные строки остаются без резерва
                        if (i > 0) await msgBox('Выбор резервов прерван');
                        return;
                    }
                    const s = await post(0, { act: 'KompelReserv', row: ids[i], res: p.key });
                    if (!s.ok) { await msgBox(s.msg || 'Не удалось сохранить резерв', { icon: 'warn' }); return; }
                }
                await msgBox(ids.length ? 'Загрузка завершена' : (r.msg || 'Загрузка завершена'));
            } finally { b.disabled = false; document.body.style.cursor = ''; }
        };
        inp.click();
    });
    // «Письма» (btnLtrs1C_Click): frmfndDocs 3 по выбранному счёту
    byId('btnLtrs1C')?.addEventListener('click', () => {
        const tr = cur(); if (!tr) return;
        accessDialog(VH.docsUrl + qs({ id: tr.dataset.id }), 424, 200);
    });
    // «Переписка» (frmSchPost): только если у счёта есть TP
    byId('MailFormBut')?.addEventListener('click', async () => {
        const tr = cur(); if (!tr || !rowOf(tr).TP) return;
        await accessDialog(VH.postUrl + qs({ id: tr.dataset.id }), 1078, 493);
        await refreshRow(tr);                                              // число писем могло измениться
    });
    // «Платежи» (frmPlatFrom1C): для счетов на детали — без кнопок отправки
    byId('Plat')?.addEventListener('click', async () => {
        const tr = cur(); if (!tr) return;
        await accessDialog(VH.platUrl + qs({ id: tr.dataset.id, det: VH.typeSch }), 431, 215);
        await refreshRow(tr);                                              // Form_Close: цвет «Оплата» (xp_GetColMailPlat) — строка заново
    });
    // «Тов» (frmGetTov)
    byId('butExel')?.addEventListener('click', () => accessDialog(VH.tovUrl, 1024, 579));
    // «Акт» / «Отчет» (frmActsAndReps 0 / 1)
    const acts = (tip, f) => { const tr = cur(); if (tr && rowOf(tr)[f] === 'Да') accessDialog(VH.actsUrl + qs({ id: tr.dataset.id, tip }), 308, 315); };
    byId('ActBut')?.addEventListener('click', () => acts(0, 'Acts'));
    byId('RepBut')?.addEventListener('click', () => acts(1, 'Reps'));

    // --- Form_Timer: обновить список раз в 30 минут
    setTimeout(() => location.reload(), 30 * 60 * 1000);
})();
