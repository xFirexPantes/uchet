/**
 * Окно «интервал дат» — форма Access frmGetInterval, общая для всех экранов.
 *
 *   const r = await getInterval()                 — [начало, конец] в виде 'ГГГГ-ММ-ДД' или null (✕ / Esc)
 *   getInterval(1, { beg: '2026-01-01', end: '2026-01-31' })  — режимы = OpenArgs формы (см. ниже)
 *
 * Режимы (Form_Load): 0 и прочие — «месяц» на сегодня; 1 — начать с переданных дат (равны — «день»);
 * 2 — «месяц», «день» недоступен; 3 — «месяц» и подпись wrn; 4 — подпись wrn, «Выбирайте даты в пределах месяца» —
 * только «месяц»/«день», иначе «год»; 5 — только «квартал», даты руками не меняются; 6 — переданные даты, не более 10 дней
 * (иначе вопрос); 7 — переданные даты, «день», группа недоступна.
 * Раскладка 1 в 1 из SaveAsText (твипы / 15 = px): форма 270 × 171 (область данных 125, примечание 46).
 * Как msgbox.js: внутри диалога (iframe) окно рисуется у родителя, поверх всего. Нужен msgbox.js (dateBox, msgBox).
 */
(function () {
    try {
        if (window.parent !== window && window.parent.getInterval) {
            window.getInterval = (m, o) => window.parent.getInterval(m, o);
            return;
        }
    } catch (e) { /* другой сайт — рисуем сами */ }

    const css = `
.gi-ov{position:fixed;inset:0;background:rgba(0,0,0,.18);z-index:99990;display:flex;align-items:center;justify-content:center}
.gi{width:270px;background:#ece9d8;border:1px solid #0831d9;box-shadow:2px 3px 12px rgba(0,0,0,.35);font:15px Tahoma,Arial,sans-serif;color:#000}
.gi-t{background:linear-gradient(#0a5ae8,#0840c6);color:#fff;font:bold 12px Tahoma;padding:4px 8px;user-select:none}
.gi-b{position:relative;height:125px}.gi-f{position:relative;height:46px}
.gi-b>*,.gi-f>*{position:absolute;box-sizing:border-box}
.gi-fr{border:1px solid #aca899;border-radius:2px}
.gi-l{background:#ece9d8;white-space:nowrap;line-height:1.1}
.gi-b input[type=text]{font:15px Tahoma;padding:0 2px;border:1px solid #7f9db9;background:transparent;cursor:default}
.gi-b input[type=radio]{margin:0}
.gi-btn{font:12px Tahoma;background:#f4f3ee;border:1px solid #003c74;border-radius:3px;cursor:pointer;padding:0}
.gi-btn:disabled,.gi-b input:disabled+label{color:#999}
.gi-w{font-size:11px;color:#000;white-space:pre-wrap}`;
    const pad = n => String(n).padStart(2, '0');
    const iso = d => d ? d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) : '';
    const ru = d => d ? pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() : '';
    const parse = s => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || ''); return m ? new Date(+m[1], m[2] - 1, +m[3]) : null; };
    const today = () => { const n = new Date(); return new Date(n.getFullYear(), n.getMonth(), n.getDate()); };
    // период (1 месяц, 2 квартал, 3 год, 4 день), в который попадает дата d, со сдвигом step периодов
    function period(g, d, step) {
        const y = d.getFullYear(), m = d.getMonth();
        if (g === 1) { const b = new Date(y, m + step, 1); return [b, new Date(b.getFullYear(), b.getMonth() + 1, 0)]; }
        if (g === 2) { const b = new Date(y, Math.floor(m / 3) * 3 + 3 * step, 1); return [b, new Date(b.getFullYear(), b.getMonth() + 3, 0)]; }
        if (g === 3) return [new Date(y + step, 0, 1), new Date(y + step, 11, 31)];
        const b = new Date(y, m, d.getDate() + step); return [b, b];
    }

    window.getInterval = function (mode = 0, o = {}) {
        return new Promise(resolve => {
            if (!document.getElementById('gi-css')) {
                const s = document.createElement('style'); s.id = 'gi-css'; s.textContent = css; document.head.appendChild(s);
            }
            const ov = document.createElement('div');
            // Современный вид экрана (.vh-modern) — окно в корпоративных цветах (css/dlg-modern.css)
            ov.className = 'gi-ov' + (document.querySelector('.vh-modern') ? ' dlg-modern' : '');
            ov.innerHTML = `<div class="gi" role="dialog" aria-modal="true"><div class="gi-t">интервал дат</div>
<div class="gi-b">
 <div class="gi-fr" style="left:4px;top:11px;width:119px;height:109px"></div>
 <div class="gi-l" style="left:11px;top:4px;width:102px;height:18px">Выбрать за...</div>
 ${[[1, 'месяц', 27], [2, 'квартал', 49], [3, 'год', 71], [4, 'день', 94]].map(([v, c, t]) =>
   `<input type="radio" name="gi-g" value="${v}" id="gi-g${v}" style="left:16px;top:${t}px">` +
   `<label for="gi-g${v}" class="gi-l" style="left:31px;top:${t - 2}px;width:78px;height:16px;background:none">${c}</label>`).join('')}
 <div class="gi-l" style="left:124px;top:4px;width:145px;height:19px">или за интервал дат</div>
 <div class="gi-l" style="left:162px;top:25px;width:23px;height:21px;background:none">с</div>
 <input type="text" readonly id="gi-beg" title="Двойной щелчок — календарь, Delete — очистить" style="left:185px;top:26px;width:83px;height:19px">
 <div class="gi-l" style="left:162px;top:46px;width:23px;height:21px;background:none">по</div>
 <input type="text" readonly id="gi-end" title="Двойной щелчок — календарь, Delete — очистить" style="left:185px;top:47px;width:83px;height:19px">
 <button type="button" class="gi-btn" id="gi-add" title="Следующий период" style="left:162px;top:72px;width:25px;height:24px">+</button>
 <button type="button" class="gi-btn" id="gi-sub" title="Предыдущий период" style="left:245px;top:72px;width:25px;height:24px">-</button>
 <div class="gi-l gi-w" id="gi-wrn" style="left:123px;top:93px;width:147px;height:30px;background:none"></div>
</div>
<div class="gi-f" style="border-top:1px solid #aca899">
 <button type="button" class="gi-btn" id="gi-ok" title="выбрать" style="left:4px;top:4px;width:38px;height:38px;font-size:16px">✓</button>
 <button type="button" class="gi-btn" id="gi-x" title="Закрыть форму" style="left:227px;top:4px;width:38px;height:38px;font-size:16px">✕</button>
</div></div>`;
            document.body.appendChild(ov);
            const $ = id => ov.querySelector('#gi-' + id);
            const radios = [...ov.querySelectorAll('[name=gi-g]')];
            let beg = null, end = null;
            const show = () => { $('beg').value = ru(beg); $('end').value = ru(end); };
            const grp = () => +(radios.find(r => r.checked)?.value || 0);
            const setGrp = g => { radios.forEach(r => r.checked = +r.value === g); };
            // Группа0_AfterUpdate: период на сегодня
            const start = (g = 1) => { setGrp(g); [beg, end] = period(g, today(), 0); show(); };
            const off = (...v) => v.forEach(n => radios[n - 1].disabled = true);

            // Form_Load
            const given = () => { beg = parse(o.beg); end = parse(o.end); show(); };
            switch (+mode) {
                case 1: given(); if (beg && end && +beg === +end) setGrp(4); break;
                case 2: start(); off(4); break;
                case 3: start(); $('wrn').textContent = o.wrn || ''; break;
                case 4: $('wrn').textContent = o.wrn || '';
                    if (o.wrn === 'Выбирайте даты в пределах месяца') { off(2, 3); start(); } else start(3);
                    break;
                case 5: off(1, 3, 4); $('beg').disabled = $('end').disabled = true; start(2); break;
                case 6: given(); $('wrn').textContent = 'Выбирайте интервал не более 10 дней'; break;
                case 7: given(); start(4); radios.forEach(r => r.disabled = true); break;  // как в Access: SetStartData(4) — сегодня
                default: start();
            }
            radios.forEach(r => r.addEventListener('change', () => start(grp())));
            // AddPer / SubPer: следующий / предыдущий период от начальной даты
            const shift = step => {
                const g = grp(); if (!g) return;
                if (!beg) { msgBox('Не указана начальная дата выборки'); return; }
                [beg, end] = period(g, beg, step); show();
            };
            $('add').addEventListener('click', () => shift(1));
            $('sub').addEventListener('click', () => shift(-1));
            // BegDta / EndDta: двойной щелчок — календарь (пусто — очистить); выбрана дата — «день» или снять группу
            ['beg', 'end'].forEach(k => {
                const el = $(k);
                el.addEventListener('dblclick', async () => {
                    if (el.disabled) return;
                    const v = await dateBox(k === 'beg' ? 'Начальная дата' : 'Конечная дата', { value: iso(k === 'beg' ? beg : end) });
                    if (v === null) return;
                    const d = parse(v);
                    if (k === 'beg') beg = d; else end = d;
                    if (d) { if (grp() === 4) { beg = end = d; } else setGrp(0); }
                    show();
                });
                el.addEventListener('keydown', e => { if (e.key === 'Delete') { e.preventDefault(); if (k === 'beg') beg = null; else end = null; show(); } });
            });
            function done(r) { document.removeEventListener('keydown', onKey, true); ov.remove(); resolve(r); }
            // GetDates_Click
            $('ok').addEventListener('click', async () => {
                if (!beg || !end) { await msgBox('Не указана начальная дата выборки'); return; }
                if (beg > end) { await msgBox('Начальная дата больше конечной даты выборки'); return; }
                if (+mode === 6 && (end - beg) / 864e5 > 10 &&
                    !(await askYesNo('Выбран интервал более 10 дней. Запрос будет осуществляться медленно. Всё равно использовать выбранный интервал?', { def: 'no' }))) return;
                done([iso(beg), iso(end)]);
            });
            $('x').addEventListener('click', () => done(null));
            function onKey(e) {
                if (document.querySelector('.mb-ov')) return;          // поверх открыт msgBox / dateBox — клавиши ему
                if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); done(null); }
            }
            document.addEventListener('keydown', onKey, true);
            $('ok').focus();
        });
    };
})();
