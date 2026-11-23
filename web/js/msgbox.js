/**
 * Свои окна сообщений вместо alert / confirm / prompt браузера — как MsgBox и InputBox в Access:
 * заголовок «Учёт», значок, кнопки по-русски, без адреса сервера в заголовке.
 *
 *   await msgBox('Счёт закрыт')                                  — сообщение, кнопка ОК
 *   if (await askYesNo('Удалить элемент из накладной?')) …       — Да / Нет (Enter — Да, Esc — Нет)
 *   askYesNo(текст, { def: 'no' })                               — по умолчанию «Нет» (vbDefaultButton2)
 *   await askYesNoCancel(текст)                                  — vbYesNoCancel: 'yes' | 'no' | 'cancel' (Esc — Отмена)
 *   const s = await inputBox('Комментарий причины', 'было')      — строка или null (Отмена)
 *   inputBox(текст, '', { select: [['d','Д'],['m','М']], selDef: 'm' }) — с выбором справа: [строка, выбор] или null
 *   const t = await textBox('Примечание', 'было', { max: 255 })   — многострочный текст (как frmShowPrim): строка или null
 *   const d = await dateBox('Дата производства')                 — 'ГГГГ-ММ-ДД', '' (пусто) или null (Отмена), как adhDoCalendar
 *
 * Все три возвращают Promise: код, который ждёт ответа, пишется через await / then.
 * Внутри диалога (iframe, access-dialog.js) окно показывается в родительском окне — поверх всего, а не в маленьком iframe.
 * Стили встроены, отдельный css не нужен.
 */
(function () {
    // в iframe того же сайта — показываем у родителя
    try {
        if (window.parent !== window && window.parent.msgBox) {
            window.msgBox = (t, o) => window.parent.msgBox(t, o);
            window.askYesNo = (t, o) => window.parent.askYesNo(t, o);
            window.askYesNoCancel = (t, o) => window.parent.askYesNoCancel(t, o);
            window.inputBox = (t, d, o) => window.parent.inputBox(t, d, o);
            window.dateBox = (t, o) => window.parent.dateBox(t, o);
            window.textBox = (t, d, o) => window.parent.textBox(t, d, o);
            return;
        }
    } catch (e) { /* другой сайт — рисуем сами */ }

    const css = `
.mb-ov{position:fixed;inset:0;background:rgba(0,0,0,.18);z-index:100000;display:flex;align-items:center;justify-content:center}
.mb{min-width:300px;max-width:560px;background:#ece9d8;border:1px solid #0831d9;box-shadow:2px 3px 12px rgba(0,0,0,.35);font:12px Tahoma,Arial,sans-serif;color:#000}
.mb-t{background:linear-gradient(#0a5ae8,#0840c6);color:#fff;font-weight:bold;padding:4px 8px;user-select:none}
.mb-b{display:flex;gap:12px;padding:14px 16px 10px}
.mb-i{flex:0 0 32px;height:32px;border-radius:50%;color:#fff;font:bold 20px/32px Arial;text-align:center}
.mb-i.info{background:#2c6bd6}.mb-i.question{background:#2c6bd6}.mb-i.warn{background:#e8b400;color:#000;border-radius:3px}.mb-i.error{background:#d22}
.mb-x{white-space:pre-wrap;word-break:break-word;align-self:center;max-height:60vh;overflow:auto}
.mb-in-w{display:flex;gap:4px;margin-top:8px}.mb-in-w .mb-in{margin-top:0}.mb-sel{font:12px Tahoma}
.mb-ta{width:440px;height:180px;resize:both}
.mb-in{width:100%;box-sizing:border-box;margin-top:8px;font:12px Tahoma;padding:2px 3px}
.mb-f{display:flex;justify-content:center;gap:8px;padding:4px 12px 12px}
.mb-f button{min-width:75px;height:23px;font:12px Tahoma;background:#f4f3ee;border:1px solid #003c74;border-radius:3px;cursor:pointer}
.mb-f button:focus{outline:1px dotted #000;outline-offset:-4px;box-shadow:0 0 0 1px #6b93e6}`;
    function addCss() {
        if (document.getElementById('mb-css')) return;
        const s = document.createElement('style'); s.id = 'mb-css'; s.textContent = css; document.head.appendChild(s);
    }
    const ICON = { info: 'i', question: '?', warn: '!', error: '✕' };
    // очередь: окна идут по одному, как модальные MsgBox
    let chain = Promise.resolve();
    function box({ text, icon = 'info', buttons = [['ok', 'ОК']], def = 0, cancel = null, input = null, type = 'text', select = null, selDef = null, title = 'Учёт', max = 0 }) {
        const run = () => new Promise(resolve => {
            addCss();
            const prevFocus = document.activeElement;
            const ov = document.createElement('div');
            // Современный вид экрана (.vh-modern) — окно сообщения в корпоративных цветах (index-modern.php)
            ov.className = 'mb-ov' + (document.querySelector('.vh-modern') ? ' vh-modern-dlg' : '');
            const w = document.createElement('div'); w.className = 'mb'; w.setAttribute('role', 'dialog'); w.setAttribute('aria-modal', 'true');
            const t = document.createElement('div'); t.className = 'mb-t'; t.textContent = title;
            const b = document.createElement('div'); b.className = 'mb-b';
            const i = document.createElement('div'); i.className = 'mb-i ' + icon; i.textContent = ICON[icon] || 'i';
            const x = document.createElement('div'); x.className = 'mb-x'; x.textContent = text == null ? '' : String(text);
            let inp = null, sel = null;
            if (input !== null) {
                const wrap = document.createElement('div'); wrap.style.flex = '1';
                wrap.appendChild(x);
                if (type === 'textarea') { inp = document.createElement('textarea'); inp.className = 'mb-in mb-ta'; if (max) inp.maxLength = max; }
                else { inp = document.createElement('input'); inp.className = 'mb-in'; inp.type = type; }
                inp.value = input;
                if (select) {
                    const row = document.createElement('div'); row.className = 'mb-in-w';
                    sel = document.createElement('select'); sel.className = 'mb-sel';
                    select.forEach(([v, l]) => { const o = document.createElement('option'); o.value = v; o.textContent = l; sel.appendChild(o); });
                    if (selDef != null) sel.value = selDef;
                    row.append(inp, sel); wrap.appendChild(row);
                } else wrap.appendChild(inp);
                b.append(i, wrap);
            } else b.append(i, x);
            const f = document.createElement('div'); f.className = 'mb-f';
            const btns = buttons.map(([val, label]) => {
                const e = document.createElement('button'); e.type = 'button'; e.textContent = label;
                e.addEventListener('click', () => done(val)); f.appendChild(e); return e;
            });
            w.append(t, b, f); ov.appendChild(w); document.body.appendChild(ov);
            function done(val) {
                document.removeEventListener('keydown', onKey, true);
                ov.remove();
                try { prevFocus && prevFocus.focus && prevFocus.focus(); } catch (e) {}
                resolve(inp ? (val === 'ok' ? (sel ? [inp.value, sel.value] : inp.value) : null) : val);
            }
            function onKey(e) {
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); done(cancel); }
                else if (e.key === 'Enter' && document.activeElement === inp && type !== 'textarea') { e.preventDefault(); e.stopPropagation(); done('ok'); }
                else if (e.key === 'Tab') {                           // фокус не уходит из окна
                    const all = (inp ? [inp] : []).concat(sel ? [sel] : [], btns), k = all.indexOf(document.activeElement);
                    e.preventDefault(); all[(k + (e.shiftKey ? all.length - 1 : 1)) % all.length].focus();
                }
                else if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    const k = btns.indexOf(document.activeElement);
                    if (k >= 0) { e.preventDefault(); btns[(k + (e.key === 'ArrowLeft' ? btns.length - 1 : 1)) % btns.length].focus(); }
                }
                e.stopPropagation();
            }
            document.addEventListener('keydown', onKey, true);
            (inp || btns[def] || btns[0]).focus();
            if (inp && type === 'text') inp.select();
        });
        const p = chain.then(run);
        chain = p.catch(() => {});
        return p;
    }
    window.msgBox = (text, o = {}) => box({ text, icon: o.icon || 'info', title: o.title, cancel: 'ok' }).then(() => undefined);
    window.askYesNo = (text, o = {}) => box({ text, icon: o.icon || 'question', title: o.title,
        buttons: [['yes', 'Да'], ['no', 'Нет']], def: o.def === 'no' ? 1 : 0, cancel: 'no' }).then(v => v === 'yes');
    window.askYesNoCancel = (text, o = {}) => box({ text, icon: o.icon || 'question', title: o.title,
        buttons: [['yes', 'Да'], ['no', 'Нет'], ['cancel', 'Отмена']], def: 0, cancel: 'cancel' });
    window.inputBox = (text, dflt = '', o = {}) => box({ text, icon: o.icon || 'question', title: o.title, input: dflt ?? '',
        buttons: [['ok', 'ОК'], ['cancel', 'Отмена']], cancel: 'cancel', select: o.select || null, selDef: o.selDef ?? null });
    window.textBox = (text, dflt = '', o = {}) => box({ text, icon: o.icon || 'question', title: o.title, input: dflt ?? '', type: 'textarea', max: o.max || 0,
        buttons: [['ok', 'ОК'], ['cancel', 'Отмена']], cancel: 'cancel' });
    window.dateBox = (text, o = {}) => box({ text, icon: o.icon || 'question', title: o.title, input: o.value || '', type: 'date',
        buttons: [['ok', 'ОК'], ['cancel', 'Отмена']], cancel: 'cancel' });
})();
