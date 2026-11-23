/**
 * Окно выбора, как frmfndFirm в Access (DoCmd.OpenForm ..., acDialog, OpenArgs).
 * Режимы описаны на сервере: components/Picker.php.
 *
 *   accessPicker('frmfndFirm', 50).then(sel => { if (sel) ... sel.key, sel.text });
 *   accessPicker('frmfndHolding', 1, '2026010120261120') — третий параметр уходит в процедуру последним (режим с extra)
 *
 * Поиск по мере набора (fnd_Change), двойной щелчок или ✓ — выбрать (xList_DblClick / Accpt_Click),
 * ✕ или Esc — закрыть без выбора (KodNullEl остаётся пустым). Столбцы раздвигаются (colResize).
 * Режим с free: если ничего не нашлось, Enter/✓ спрашивает и возвращает введённый текст ({free: true}).
 */
// Свои стили окна выбора: экран может не подключать access-form.css (так было во «Входящих счетах» — окно «Долги» разваливалось)
(function () {
    if (document.getElementById('picker-css')) return;
    const st = document.createElement('style'); st.id = 'picker-css';
    st.textContent =
        '.picker-ov{position:fixed;inset:0;background:rgba(0,0,0,.25);z-index:1000;display:flex;align-items:center;justify-content:center}' +
        '.picker{background:#ece9d8;border:1px solid #0a246a;box-shadow:2px 2px 8px rgba(0,0,0,.4);font:11px Tahoma,sans-serif;color:#000}' +
        '.picker-title{background:#0a246a;color:#fff;padding:3px 4px;display:flex;justify-content:space-between;align-items:center;font-weight:bold}' +
        '.picker-x{background:#ece9d8;border:1px outset #ccc;font-size:10px;width:18px;height:16px;padding:0;cursor:pointer}' +
        '.picker-top{display:flex;align-items:center;gap:6px;padding:4px;font-size:16px}' +
        '.picker-top input{flex:1;font-size:16px;border:1px inset #999}' +
        '.picker-list{height:287px;overflow:auto;background:#fff;border:1px inset #999;margin:0 4px}' +
        '.picker-foot{display:flex;gap:4px;padding:4px}' +
        '.picker .a-btn{background:#ece9d8;border:1px outset #ccc;padding:0;display:flex;align-items:center;justify-content:center;color:#000;cursor:pointer;width:38px;height:38px;font-size:16px;position:static}' +
        '.picker .a-btn[data-b=cls]{margin-left:auto}' +
        '.picker .a-grid{border-collapse:collapse;font:12px Arial,sans-serif;table-layout:fixed}' +
        '.picker .a-grid th{position:sticky;top:0;background:#ece9d8;font-weight:normal;padding:1px 3px;border:1px solid #c0c0c0}' +
        '.picker .a-grid td{padding:0 3px;border:1px solid #c0c0c0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;height:17px;cursor:default}' +
        '.picker .a-grid tr.sel td{background:#316ac5;color:#fff}';
    (document.head || document.documentElement).appendChild(st);
})();
window.accessPicker = function (form, mode, extra) {
    const q = new URLSearchParams({ form, mode });
    return fetch('/picker/mode?' + q).then(r => r.json()).then(m => new Promise(resolve => {
        const ov = document.createElement('div');
        // Современный вид экрана (.vh-modern) — окно получает корпоративные стили (index-modern.php)
        ov.className = 'picker-ov' + (document.querySelector('.vh-modern') ? ' vh-modern-dlg' : '');
        ov.innerHTML =
            '<div class="picker" style="width:' + (m.width || 300) + 'px">' +
              '<div class="picker-title"><span></span><button type="button" class="picker-x" title="Закрыть">✕</button></div>' +
              '<div class="picker-top"><label></label><input type="text" autocomplete="off"></div>' +
              '<div class="picker-list"><table class="a-grid"><colgroup></colgroup><thead><tr></tr></thead><tbody></tbody></table></div>' +
              '<div class="picker-foot">' +
                '<button type="button" class="a-btn" data-b="ok" title="Выбрать">✓</button>' +
                '<button type="button" class="a-btn" data-b="clr" title="Очистить выборку">⌫</button>' +
                '<button type="button" class="a-btn" data-b="cls" title="Закрыть форму">✕</button>' +
              '</div>' +
            '</div>';
        document.body.appendChild(ov);
        const $ = s => ov.querySelector(s);
        $('.picker-title span').textContent = m.title;
        $('.picker-top label').textContent = m.label;
        const tbl = $('table'), body = tbl.tBodies[0], fnd = $('.picker-top input');
        m.cols.forEach(c => {
            const col = document.createElement('col'); col.style.width = c[2] + 'px'; $('colgroup').appendChild(col);
            const th = document.createElement('th'); th.textContent = c[1]; tbl.tHead.rows[0].appendChild(th);
        });
        if (m.noHead) tbl.tHead.style.display = 'none';
        if (m.noFind) $('.picker-top').style.display = 'none';             // Me!fnd.Visible = False
        window.colResize && colResize(tbl, 'picker.' + form + '.' + mode);

        let rows = [], sel = -1, timer;
        function draw() {
            body.innerHTML = '';
            rows.forEach((r, i) => {
                const tr = body.insertRow(); tr.dataset.i = i;
                m.cols.forEach(c => { const td = tr.insertCell(); td.textContent = r[c[0]] ?? ''; td.title = r[c[0]] ?? ''; });
            });
            pick(rows.length ? 0 : -1);
        }
        function pick(i) {
            sel = i;
            [...body.rows].forEach((tr, k) => tr.classList.toggle('sel', k === i));
            body.rows[i]?.scrollIntoView({ block: 'nearest' });
        }
        function load() {
            fetch('/picker/list?' + new URLSearchParams(Object.assign({ form, mode, q: fnd.value }, extra != null ? { x: extra } : {}))).then(r => r.json()).then(d => { rows = d; draw(); });
        }
        function done(r) {
            document.removeEventListener('keydown', keys, true);
            ov.remove();
            resolve(r ? { key: r.key, text: r[m.cols[0][0]], row: r } : null);
        }
        // режим с free: ничего не нашлось — можно взять введённое как есть (fnd_AfterUpdate)
        function freeText() {
            const t = fnd.value.trim();
            if (!m.free || rows.length || !t) return false;
            askYesNo(m.free).then(yes => {
                if (!yes) return;
                document.removeEventListener('keydown', keys, true);
                ov.remove();
                resolve({ key: t, text: t, row: null, free: true });
            });
            return true;
        }
        function keys(e) {
            if (document.querySelector('.mb-ov')) return;          // открыто окно сообщения (msgbox.js) — клавиши его
            if (e.key === 'Escape') { e.preventDefault(); done(null); }
            else if (e.key === 'Enter') { e.preventDefault(); if (sel >= 0) done(rows[sel]); else freeText(); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); if (sel < rows.length - 1) pick(sel + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); if (sel > 0) pick(sel - 1); }
        }
        document.addEventListener('keydown', keys, true);
        fnd.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); });
        body.addEventListener('click', e => { const tr = e.target.closest('tr'); if (tr) pick(+tr.dataset.i); });
        body.addEventListener('dblclick', e => { const tr = e.target.closest('tr'); if (tr) done(rows[+tr.dataset.i]); });
        $('.picker-x').onclick = () => done(null);
        $('[data-b=cls]').onclick = () => done(null);
        $('[data-b=ok]').onclick = () => { if (sel >= 0) done(rows[sel]); else freeText(); };
        $('[data-b=clr]').onclick = () => { fnd.value = ''; rows = []; draw(); fnd.focus(); };   // Del_Click: очистить выборку
        if (!m.noFind) fnd.focus();
        load();                                                // Form_Open: сразу полный список
    }));
};
