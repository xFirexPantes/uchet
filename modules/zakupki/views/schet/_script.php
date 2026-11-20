<?php
/**
 * Общий скрипт карточки: раздвигание столбцов во всех подформах, выбор строки, резервы по позиции, закрытие.
 * @var yii\web\View $this
 * @var int $id
 * @var string|null $resUrl  адрес резервов (только у счёта на элементы)
 */
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$resUrl = Json::encode($resUrl ?? null);
$naklActUrl  = Json::encode(Url::to(['act', 'id' => $id]));
$naklViewUrl = Json::encode(Url::to(['nakl/view', 'sch' => $id]));
$this->registerJs(<<<JS
(function () {
    // У всех таблиц карточки столбцы раздвигаются (правило проекта), ширины запоминаются
    document.querySelectorAll('table.a-grid').forEach(t => colResize(t, t.dataset.key));

    // Выбор строки щелчком, как курсор записи в подформе Access
    function selectRow(tr) {
        tr.parentElement.querySelectorAll('tr.sel').forEach(r => r.classList.remove('sel'));
        tr.classList.add('sel');
    }
    document.querySelectorAll('table.a-grid tbody').forEach(b =>
        b.addEventListener('click', e => { const tr = e.target.closest('tr'); if (tr) selectRow(tr); }));

    // SubFrmMassInputSch.Form_Current → SetRS4LstSchRes(ID, SkladID): резервы выбранной позиции
    const resUrl = $resUrl, pos = document.getElementById('pos'), res = document.getElementById('res');
    if (resUrl && pos && res) {
        pos.tBodies[0].addEventListener('click', e => {
            const tr = e.target.closest('tr'); if (!tr || !tr.dataset.row) return;
            const sklad = JSON.parse(tr.dataset.row).SkladID ?? 0;
            fetch(resUrl + '?id=$id&skladId=' + encodeURIComponent(sklad))
                .then(r => r.json())
                .then(rows => {
                    // перерисовываем только строки, столбцы и их ширины остаются
                    const b = res.tBodies[0]; b.innerHTML = '';
                    rows.forEach(r => {
                        const tr = b.insertRow();
                        RES_COLS.forEach(c => {
                            const td = tr.insertCell();
                            if (c[2]) { const cb = document.createElement('input'); cb.type = 'checkbox'; cb.disabled = true; cb.checked = !!Number(r[c[0]]); td.appendChild(cb); td.style.textAlign = 'center'; }
                            else td.textContent = fmtDate(r[c[0]]);
                            if (c[1] && Number(r.ColorFld) === c[1]) td.style.color = c[1] === 1 ? '#ed1c24' : '#ff0000';
                        });
                    });
                });
        });
        if (pos.tBodies[0].rows.length) selectRow(pos.tBodies[0].rows[0]);
    }
    // Накладная: двойной щелчок по списку (lstNakl_DblClick) или «Посмотреть» (OpnFrm_Click) → проверки на сервере → окно frmMassInput
    function openNakl(btn) {
        const tb = document.querySelector('#nakl tbody');
        let tr = tb?.querySelector('tr.sel');
        if (!tr && btn && $id < 0) tr = tb?.rows[0];              // у деталей кнопка берёт первую накладную
        const fd = new FormData();
        fd.append('act', 'OpnNakl'); fd.append('nakl', tr ? tr.dataset.id : ''); fd.append('btn', btn ? '1' : '0');
        const cp = document.querySelector('meta[name=csrf-param]')?.content;
        if (cp) fd.append(cp, document.querySelector('meta[name=csrf-token]').content);
        fetch($naklActUrl, { method: 'POST', body: fd }).then(r => r.json()).then(async res => {
            if (res.msg) await msgBox(res.msg);
            if (res.ok && res.nakl) accessDialog($naklViewUrl + '&id=' + encodeURIComponent(res.nakl), 1243, 641);
        });
    }
    document.querySelector('#nakl tbody')?.addEventListener('dblclick', e => { if (e.target.closest('tr')) openNakl(false); });
    document.getElementById('OpnFrm')?.addEventListener('click', () => openNakl(true));

    // [поле, номер ColorFld для красного, флажок]: тот же порядок, что в elem.php
    const RES_COLS = [['NmeRes'], ['NameProcess'], ['Avt', 0, 1], ['DtaPostAvt', 1], ['PlandateAvt', 2], ['DtaPost', 3], ['PlandateMnl', 4], ['InControl'], ['ClassVl']];
    function fmtDate(v) {
        if (v == null) return '';
        const m = String(v).match(/^(\\d{4})-(\\d{2})-(\\d{2})(?: (\\d{2}):(\\d{2}))?/);
        if (!m) return String(v);
        return m[3] + '.' + m[2] + '.' + m[1] + (m[4] && (m[4] + m[5]) !== '0000' ? ' ' + m[4] + ':' + m[5] : '');
    }

    // butExit: карточка открыта отдельной вкладкой из «Входящих счетов» → закрываем вкладку
    // Как ChangeDataInList в Access: после закрытия карточки список во «Входящих» обновляется
    window.closeCard = function () {
        if (window.opener && !window.opener.closed) {
            try { window.opener.location.reload(); } catch (e) {}
            window.close(); return;
        }
        history.length > 1 ? history.back() : (location.href = '/zakupki/vh-schet/index');
    };
})();
JS, View::POS_END);
