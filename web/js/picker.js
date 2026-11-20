/**
 * Окно выбора, как frmfndFirm в Access (DoCmd.OpenForm ..., acDialog, OpenArgs).
 * Режимы описаны на сервере: components/Picker.php.
 *
 *   accessPicker('frmfndFirm', 50).then(sel => { if (sel) ... sel.key, sel.text });
 *
 * Поиск по мере набора (fnd_Change), двойной щелчок или ✓ — выбрать (xList_DblClick / Accpt_Click),
 * ✕ или Esc — закрыть без выбора (KodNullEl остаётся пустым). Столбцы раздвигаются (colResize).
 * Режим с free: если ничего не нашлось, Enter/✓ спрашивает и возвращает введённый текст ({free: true}).
 */
window.accessPicker = function (form, mode) {
    const q = new URLSearchParams({ form, mode });
    return fetch('/picker/mode?' + q).then(r => r.json()).then(m => new Promise(resolve => {
        const ov = document.createElement('div');
        ov.className = 'picker-ov';
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
            fetch('/picker/list?' + new URLSearchParams({ form, mode, q: fnd.value })).then(r => r.json()).then(d => { rows = d; draw(); });
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
        fnd.focus();
        load();                                                // Form_Open: сразу полный список
    }));
};
