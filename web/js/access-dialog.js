/**
 * Окно-диалог поверх формы: аналог DoCmd.OpenForm ..., acDialog в Access.
 * Форма диалога открывается в iframe (своя страница, макет dialog). Закрывается сама, отправив родителю
 *   parent.postMessage({ type: 'access-dialog', reload: true|false, value }, '*')
 * accessDialog(url, width, height) → Promise<{reload, value}>. value — что выбрано в диалоге (как OldLine / txtSkladID
 * в Access), например SkladID из поиска компонента. Esc закрывает без перечитывания и без значения.
 */
(function () {
    window.accessDialog = function (url, width, height) {
        // из диалога (iframe) новое окно открывается у родителя — во весь экран, а не внутри маленького iframe
        try {
            if (window.parent !== window && window.parent.accessDialog) return window.parent.accessDialog(new URL(url, location.href).href, width, height);
        } catch (e) { /* другой сайт — открываем у себя */ }
        return new Promise(resolve => {
            const ov = document.createElement('div');
            ov.className = 'picker-ov';
            const box = document.createElement('div');
            box.className = 'picker';
            box.style.width = (width + 2) + 'px';
            // окно больше экрана — не обрезать, а прокручивать (форма ГТД шириной 1323 px)
            box.style.maxWidth = 'calc(100vw - 8px)'; box.style.maxHeight = 'calc(100vh - 8px)'; box.style.overflow = 'auto';
            const fr = document.createElement('iframe');
            fr.src = url;
            fr.style.cssText = 'display:block;border:0;width:' + width + 'px;height:' + height + 'px;background:#ece9d8';
            // заголовок окна, как у формы Access: название формы и «?» (справка по окну, window.helpUrl из views/access/_help.php)
            const cap = document.createElement('div');
            cap.className = 'picker-title';
            const capT = document.createElement('span'); capT.textContent = '';
            const capQ = document.createElement('button'); capQ.type = 'button'; capQ.className = 'picker-x'; capQ.textContent = '?';
            capQ.title = 'Справка (F1)'; capQ.style.visibility = 'hidden';
            capQ.addEventListener('click', () => { const u = fr.contentWindow && fr.contentWindow.helpUrl; if (u) window.open(u, 'help', 'width=860,height=700,scrollbars=yes'); });
            // ✕ в заголовке: выйти можно всегда, даже если кнопки формы не поместились в окно браузера
            const capX = document.createElement('button'); capX.type = 'button'; capX.className = 'picker-x'; capX.textContent = '✕';
            capX.title = 'Закрыть (Esc)'; capX.style.marginLeft = '2px';
            capX.addEventListener('click', () => done(false));
            const capB = document.createElement('span'); capB.append(capQ, capX);
            cap.append(capT, capB);
            fr.addEventListener('load', () => {
                try { capT.textContent = fr.contentDocument.title || ''; capQ.style.visibility = fr.contentWindow.helpUrl ? 'visible' : 'hidden'; } catch (e) {}
            });
            box.appendChild(cap);
            box.appendChild(fr);
            ov.appendChild(box);
            document.body.appendChild(ov);
            function done(reload, value) {
                window.removeEventListener('message', onMsg);
                document.removeEventListener('keydown', onKey);
                ov.remove();
                resolve({ reload: !!reload, value: value ?? null });
            }
            function onMsg(e) {
                if (e.source === fr.contentWindow && e.data && e.data.type === 'access-dialog') done(e.data.reload, e.data.value);
            }
            function onKey(e) {                                     // Esc закрывает только верхнее окно
                if (e.key !== 'Escape' || document.querySelector('.mb-ov')) return;
                if ([...document.querySelectorAll('.picker-ov')].pop() === ov) done(false);
            }
            window.addEventListener('message', onMsg);
            document.addEventListener('keydown', onKey);
        });
    };
    // Страница внутри диалога: Esc закрывает её (нажатие ловит iframe, а не окно, которое его открыло)
    if (window.parent !== window) {
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape' || e.defaultPrevented || document.querySelector('.mb-ov')) return;
            if (e.target.closest && e.target.closest('input, select, textarea')) return;   // Esc в поле — отмена ввода, не закрытие
            window.closeAccessDialog(false);
        });
    }
    // Для страницы внутри диалога: закрыть себя
    window.closeAccessDialog = function (reload, value) {
        if (window.parent !== window) window.parent.postMessage({ type: 'access-dialog', reload: !!reload, value: value ?? null }, '*');
        else window.close();
    };
})();
