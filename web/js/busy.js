/**
 * Индикатор занятости — «система думает».
 *
 * Показывается между действием пользователя и готовым экраном: при отправке формы,
 * переходе по ссылке и во время AJAX-запросов (fetch). Чтобы не мигать на быстрых
 * операциях, окно появляется только если ожидание длится дольше DELAY мс.
 * Фраза выбирается случайно из PHRASES и меняется, если ждать приходится долго.
 *
 *   Busy.show('текст')   — показать принудительно
 *   Busy.hide()          — скрыть
 *   Busy.wrap(promise)   — обернуть промис (показать/скрыть автоматически)
 *   fetch(url, { busy: false }) — запрос без индикатора (фоновый опрос)
 *
 * Файл подключается в <head>, поэтому DOM строится лениво, при первом показе.
 */
(function () {
    'use strict';

    const PHRASES = [
        'Обедаю…',
        'Звоню подруге…',
        'Гуглю…',
        'Завариваю чай…',
        'Ищу нужную папку…',
        'Консультируюсь с бухгалтерией…',
        'Считаю на счётах…',
        'Разговариваю с базой…',
        'Уговариваю сервер…',
        'Пересчитываю всё заново…',
        'Пью кофе…',
        'Смотрю в окно…',
        'Жду ответа от 1С…',
        'Делаю вид, что работаю…',
        'Медитирую над запросом…',
        'Разгребаю завалы…',
        'Печатаю очень быстро…',
        'Ищу потерянную запятую…',
        'Спрашиваю у Гугла…',
        'Договариваюсь с базой данных…',
        'Грею сервер…',
        'Проверяю каждую цифру…',
        'Читаю документацию…',
        'Вспоминаю пароль…',
        'Учусь считать…',
        'Сортирую отчёты…',
        'Разбираю Excel…',
        'Заряжаюсь энтузиазмом…',
        'Иду за печеньем…',
        'Почти успел…',
    ];

    const DELAY = 350;      // мс: быстрее — окно не показываем, чтобы не мигало
    const ROTATE = 5000;    // мс: смена фразы, если ожидание затянулось
    const HOLD_MAX = 45000; // мс: страховка для перехода по ссылке (страница не ушла)

    let el = null, txt = null;
    let shown = false;
    let timer = null, rotate = null, hold = null;
    let active = 0;          // число одновременных запросов
    let manual = false;      // окно показано вручную — release его не закрывает
    let last = -1;           // индекс прошлой фразы — чтобы не повторялась подряд

    function pick() {
        if (PHRASES.length < 2) return PHRASES[0] || '';
        let i;
        do {
            i = Math.floor(Math.random() * PHRASES.length);
        } while (i === last);
        last = i;
        return PHRASES[i];
    }

    function build() {
        if (el || !document.body) return;
        el = document.createElement('div');
        el.id = 'vhBusy';
        el.className = 'vh-busy';
        el.setAttribute('role', 'status');
        el.setAttribute('aria-live', 'polite');
        el.innerHTML =
            '<div class="vh-busy-box">' +
                '<div class="vh-busy-spin" aria-hidden="true"><i></i><i></i><i></i></div>' +
                '<div class="vh-busy-txt"></div>' +
                '<div class="vh-busy-sub">идёт обработка, подождите…</div>' +
            '</div>';
        document.body.appendChild(el);
        txt = el.querySelector('.vh-busy-txt');
    }

    function show(text) {
        if (shown) return;
        build();
        if (!el) return;
        shown = true;
        if (txt) txt.textContent = text || pick();
        el.classList.add('is-on');
        rotate = setInterval(() => { if (txt) txt.textContent = pick(); }, ROTATE);
    }

    function hide() {
        clearTimeout(timer);
        clearTimeout(hold);
        timer = hold = null;
        if (rotate) { clearInterval(rotate); rotate = null; }
        shown = false;
        manual = false;
        if (el) el.classList.remove('is-on');
    }

    function acquire() {
        active++;
        if (active === 1) {
            clearTimeout(timer);
            timer = setTimeout(() => show(), DELAY);
        }
    }

    function release() {
        active = Math.max(0, active - 1);
        if (!active && !manual) hide();
    }

    // Переход на другую страницу: показываем сразу, снимаем по возврату (pageshow/bfcache)
    function holdOn() {
        show();
        clearTimeout(hold);
        hold = setTimeout(hide, HOLD_MAX);
    }

    window.Busy = {
        show(text) {
            manual = true;
            show(text);
        },
        hide: hide,
        acquire: acquire,
        release: release,
        wrap(p) {
            acquire();
            return Promise.resolve(p).then(
                r => { release(); return r; },
                e => { release(); throw e; },
            );
        },
    };

    // --- AJAX: любой fetch дольше DELAY показывает окно ---
    const nativeFetch = window.fetch;
    if (typeof nativeFetch === 'function') {
        window.fetch = function (input, init) {
            if (init && init.busy === false) return nativeFetch.apply(this, arguments);
            acquire();
            let p;
            try {
                p = nativeFetch.apply(this, arguments);
            } catch (e) {
                release();
                throw e;
            }
            return p.then(
                r => { release(); return r; },
                e => { release(); throw e; },
            );
        };
    }

    // --- Переходы по ссылкам и отправка форм (bubble: уважаем preventDefault) ---
    document.addEventListener('click', ev => {
        if (ev.defaultPrevented || ev.button !== 0) return;
        if (ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.altKey) return;
        const a = ev.target.closest && ev.target.closest('a[href]');
        if (!a) return;
        const href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#') return;
        if (/^(javascript|mailto|tel):/i.test(href)) return;
        if (a.target && a.target !== '_self') return;
        if (a.hasAttribute('download') || a.dataset.busy === '0') return;
        holdOn();
    });

    document.addEventListener('submit', ev => {
        if (ev.defaultPrevented) return;
        const f = ev.target;
        if (!f || f.target === '_blank') return;
        if (f.dataset && f.dataset.busy === '0') return;
        holdOn();
    });

    // form.submit() не порождает событие submit — на нём построены фильтры
    // (onchange="this.form.submit()"), поэтому перехватываем сам метод.
    const nativeSubmit = HTMLFormElement.prototype.submit;
    if (typeof nativeSubmit === 'function') {
        HTMLFormElement.prototype.submit = function () {
            if (!(this.dataset && this.dataset.busy === '0')) holdOn();
            return nativeSubmit.apply(this, arguments);
        };
    }

    // Возврат из bfcache — страница осталась живой, окно не нужно
    window.addEventListener('pageshow', () => { active = 0; hide(); });
})();
