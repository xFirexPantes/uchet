/**
 * Раздвигание столбцов мышью, как в Access (ListView, режим таблицы подформы).
 * Правило проекта: у ВСЕХ таблиц и подформ столбцы раздвигаются.
 *
 *   colResize(document.getElementById('vhList'), 'vhSchet.list');
 *   colResize(tbl, 'key', { full: true });   // таблица занимает всю ширину контейнера, колонки — по пропорции
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

    // Уже настроена — только пересчитать ширины под текущий размер контейнера
    if (tbl.dataset.colResize) { if (tbl._vhFit) tbl._vhFit(); return; }

    // base — ширины, задающие пропорцию колонок. full масштабирует их под ширину контейнера,
    // поэтому пропорция не «плывёт» от повторных пересчётов.
    const initial = cols.map(c => parseInt(c.style.width) || 80);
    const base = initial.slice();
    try { (JSON.parse(localStorage.getItem(key)) || []).forEach((w, i) => { if (cols[i] && w) base[i] = w; }); } catch (e) {}

    const apply = () => {
        const avail = (opts.full && tbl.parentElement ? tbl.parentElement.clientWidth : 0) || 0;
        const total = base.reduce((a, b) => a + b, 0) || 1;
        if (!avail) {                                     // контейнер ещё не разложен: ширины как заданы
            cols.forEach((c, i) => { c.style.width = base[i] + 'px'; });
            tbl.style.width = total + 'px';
            return;
        }
        const k = avail / total;
        cols.forEach((c, i) => { c.style.width = Math.max(20, Math.round(base[i] * k)) + 'px'; });
        tbl.style.width = avail + 'px';
    };
    tbl.style.tableLayout = 'fixed';
    tbl._vhFit = apply;
    apply();
    // Первый расчёт может прийтись на неполную раскладку (шрифты, полосы прокрутки, ширина контейнера
    // меняется скриптом страницы) — пересчитываем после отрисовки кадра, после полной загрузки
    // и при любом изменении размера контейнера.
    requestAnimationFrame(apply);
    window.addEventListener('load', apply);

    ths.forEach(th => {
        th.style.position = th.style.position || 'sticky';
        if (!th.querySelector('.rsz')) { const s = document.createElement('span'); s.className = 'rsz'; th.appendChild(s); }
    });
    tbl.dataset.colResize = '1';
    if (opts.full && window.ResizeObserver && tbl.parentElement) new ResizeObserver(apply).observe(tbl.parentElement);
    if (opts.full) window.addEventListener('resize', apply);   // окно изменили — таблица подстраивается

    tbl.tHead.addEventListener('mousedown', e => {
        if (!e.target.classList.contains('rsz')) return;
        e.preventDefault(); e.stopPropagation();
        const i = [...tbl.tHead.rows[0].cells].indexOf(e.target.parentElement);
        const x0 = e.clientX;
        const start = cols.map(c => parseInt(c.style.width) || 80);
        const total = start.reduce((a, b) => a + b, 0);
        const avail = (opts.full && tbl.parentElement ? tbl.parentElement.clientWidth : 0) || 0;
        tbl.dataset.resizing = '1';
        const move = ev => {
            const w = Math.max(20, Math.round(start[i] + ev.clientX - x0));
            if (!avail) {                                 // не full: тянем одну колонку, таблица шире
                cols[i].style.width = w + 'px';
                tbl.style.width = (total - start[i] + w) + 'px';
                return;
            }
            // full: таблица остаётся во всю ширину, остальные колонки делят оставшееся место
            const rest = total - start[i];
            const k = rest > 0 ? Math.max(0, avail - w) / rest : 0;
            cols.forEach((c, j) => { c.style.width = (j === i ? w : Math.max(20, Math.round(start[j] * k))) + 'px'; });
            tbl.style.width = avail + 'px';
        };
        const up = () => {
            document.removeEventListener('mousemove', move); document.removeEventListener('mouseup', up);
            setTimeout(() => { tbl.dataset.resizing = ''; }, 0);
            // новые ширины становятся пропорцией: дальнейшие пересчёты их сохранят
            cols.forEach((c, j) => { const w = parseInt(c.style.width); if (w) base[j] = w; });
            try { localStorage.setItem(key, JSON.stringify(base)); } catch (er) {}
        };
        document.addEventListener('mousemove', move); document.addEventListener('mouseup', up);
    });
    tbl.tHead.addEventListener('dblclick', e => {
        if (!e.target.classList.contains('rsz')) return;
        try { localStorage.removeItem(key); } catch (er) {}
        initial.forEach((w, i) => { base[i] = w; });
        apply();
    });
};
