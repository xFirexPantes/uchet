/**
 * Раздвигание столбцов мышью, как в Access (ListView, режим таблицы подформы).
 * Правило проекта: у ВСЕХ таблиц и подформ столбцы раздвигаются.
 *
 *   colResize(document.getElementById('vhList'), 'vhSchet.list');
 *   colResize(tbl, 'key', { full: true });   // таблица занимает всю ширину контейнера (не уже суммы колонок)
 *
 * - тянуть за правый край заголовка;
 * - ширины запоминаются в браузере под ключом key (у каждого пользователя свои);
 * - двойной щелчок по краю заголовка возвращает исходные ширины;
 * - пока тянем, щелчок по заголовку (сортировка) не срабатывает: table.dataset.resizing = '1'.
 * Можно вызывать повторно для той же таблицы (после перерисовки строк): ручки не задвоятся.
 */
window.colResize = function (tbl, key, opts) {
    opts = opts || {};
    if (!tbl || !tbl.tHead) return;
    const ths = [...tbl.tHead.rows[0].cells];
    let cg = tbl.querySelector('colgroup');
    if (!cg) {                                            // нет colgroup: берём текущие ширины заголовков
        cg = document.createElement('colgroup');
        ths.forEach(th => { const c = document.createElement('col'); c.style.width = (th.offsetWidth || 80) + 'px'; cg.appendChild(c); });
        tbl.insertBefore(cg, tbl.firstChild);
    }
    const cols = [...cg.children];
    const initial = cols.map(c => c.style.width);
    // full: тянем таблицу на всю ширину контейнера. Если ширины колонок почти совпадают с контейнером
    // (расхождение до 3%), колонки слегка сжимаются — таблица встаёт ровно по ширине без скролла.
    // Если колонки заметно шире — оставляем их ширину и появляется горизонтальный скролл.
    const fit = () => {
        const sum = cols.reduce((s, c) => s + parseInt(c.style.width || 0), 0);
        const avail = (opts.full && tbl.parentElement ? tbl.parentElement.clientWidth : 0) || 0;
        tbl.style.width = (avail && avail >= sum * 0.97 ? avail : sum) + 'px';
    };
    tbl.style.tableLayout = 'fixed';
    try { (JSON.parse(localStorage.getItem(key)) || []).forEach((w, i) => { if (cols[i] && w) cols[i].style.width = w + 'px'; }); } catch (e) {}
    fit();
    if (tbl.dataset.colResize) return;                    // обработчики уже повешены
    tbl.dataset.colResize = '1';
    if (opts.full) window.addEventListener('resize', fit);  // окно изменили — таблица подстраивается
    ths.forEach(th => {
        th.style.position = th.style.position || 'sticky';
        if (!th.querySelector('.rsz')) { const s = document.createElement('span'); s.className = 'rsz'; th.appendChild(s); }
    });
    tbl.tHead.addEventListener('mousedown', e => {
        if (!e.target.classList.contains('rsz')) return;
        e.preventDefault(); e.stopPropagation();
        const i = [...tbl.tHead.rows[0].cells].indexOf(e.target.parentElement);
        const col = cols[i], x0 = e.clientX, w0 = parseInt(col.style.width);
        tbl.dataset.resizing = '1';
        const move = ev => { col.style.width = Math.max(20, w0 + ev.clientX - x0) + 'px'; fit(); };
        const up = () => {
            document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up);
            setTimeout(() => { tbl.dataset.resizing = ''; }, 0);
            try { localStorage.setItem(key, JSON.stringify(cols.map(c => parseInt(c.style.width)))); } catch (er) {}
        };
        document.addEventListener('mousemove', move); document.addEventListener('mouseup', up);
    });
    tbl.tHead.addEventListener('dblclick', e => {
        if (!e.target.classList.contains('rsz')) return;
        try { localStorage.removeItem(key); } catch (er) {}
        cols.forEach((c, i) => { c.style.width = initial[i]; }); fit();
    });
};
