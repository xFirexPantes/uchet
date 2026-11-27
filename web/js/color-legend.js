/* Легенда цветов подформы: открытие по [data-cl-open], закрытие по [data-cl-x],
   клику по фону и Esc. Разметка — views/access/_color-legend.php. */
(function () {
    'use strict';

    var lastFocus = null;

    function open(id) {
        var box = document.getElementById(id);
        if (!box) return;
        lastFocus = document.activeElement;
        box.classList.add('on');
        var x = box.querySelector('[data-cl-x]');
        if (x) x.focus();
    }

    function close(box) {
        if (!box) return;
        box.classList.remove('on');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-cl-open]');
        if (opener) {
            e.preventDefault();
            open(opener.getAttribute('data-cl-open'));
            return;
        }
        var closer = e.target.closest('[data-cl-x]');
        if (closer) {
            e.preventDefault();
            close(closer.closest('.cl-modal'));
            return;
        }
        var modal = e.target.classList && e.target.classList.contains('cl-modal') ? e.target : null;
        if (modal) close(modal);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var on = document.querySelector('.cl-modal.on');
        if (on) close(on);
    });
})();
