<?php
/**
 * «Добавить позиции в накладную» (frmAddEl2Nakl) — диалог поверх накладной (＋ butDob).
 * Ленточная форма Access: заголовки «Кол-во» и «Серия, Наименование, Номинал», в строке Kolvo (меняется) и nazv.
 * Ширины: Kolvo 1140 твипов = 76 px, nazv 7185 = 479 px. Окно DoCmd.MoveSize 11000 × 3500.
 * Kolvo_AfterUpdate: больше, чем в счёте, нельзя. ✓ Кнопка20 — добавить строки с Kolvo > 0, ✕ Кнопка21 — выйти.
 *
 * @var yii\web\View $this
 * @var int   $id    накладная
 * @var int   $sch   счёт
 * @var array $rows  xp_frmAddEl2NaklRS: Kolvo, AllID, SkladID, nazv, Код
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Добавить позиции в накладную';
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/access-dialog.js');
?>
<div class="access-form" id="frmAddEl2Nakl" style="width:600px;height:233px">
    <div style="left:4px;top:4px;width:592px;height:176px;overflow:auto;background:#fff">
        <table class="a-grid" id="ael" data-key="nakl.addEl">
            <colgroup><col style="width:76px"><col style="width:479px"></colgroup>
            <thead><tr><th title="Кол-во">Кол-во<span class="rsz"></span></th><th title="Серия, Наименование, Номинал">Серия, Наименование, Номинал<span class="rsz"></span></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr data-id="<?= (int)$r['AllID'] ?>" data-max="<?= (int)$r['Kolvo'] ?>">
                    <td style="padding:0"><input type="text" class="kol" value="<?= (int)$r['Kolvo'] ?>" style="width:100%;box-sizing:border-box;border:0;text-align:right;font:inherit"></td>
                    <td title="<?= Html::encode($r['nazv'] ?? '') ?>"><?= Html::encode($r['nazv'] ?? '') ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="a-btn" id="Кнопка20" title="Добавить позицию в накладную" style="left:4px;top:188px;width:38px;height:38px">✓</button>
    <button type="button" class="a-btn" id="Кнопка21" title="Выход" style="left:45px;top:188px;width:38px;height:38px">✕</button>
</div>
<?php
$actUrl = Json::encode(Url::to(['act', 'id' => $id, 'sch' => $sch]));
$this->registerJs(<<<JS
(function () {
    const tbl = document.getElementById('ael');
    window.colResize && colResize(tbl, 'nakl.addEl');
    const kols = [...tbl.querySelectorAll('input.kol')];
    // Kolvo_AfterUpdate: больше, чем в счёте, нельзя — вернуть прежнее значение
    kols.forEach((i, k) => {
        i.dataset.old = i.value;
        i.addEventListener('change', async () => {
            const v = parseInt(i.value, 10), max = +i.closest('tr').dataset.max;
            if (isNaN(v) || v < 0) { i.value = i.dataset.old; return; }
            if (v > max) { await msgBox('Количество по накладной больше, чем в счёте', { icon: 'error' }); i.value = i.dataset.old; i.focus(); return; }
            i.value = v; i.dataset.old = i.value;
        });
        // Kolvo_KeyDown: стрелки вверх / вниз — соседняя строка
        i.addEventListener('keydown', e => {
            if (e.key === 'ArrowUp' && kols[k - 1]) { e.preventDefault(); kols[k - 1].focus(); kols[k - 1].select(); }
            if (e.key === 'ArrowDown' && kols[k + 1]) { e.preventDefault(); kols[k + 1].focus(); kols[k + 1].select(); }
            if (e.key === 'Enter') i.blur();
        });
    });
    function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => {
            if (v && typeof v === 'object') Object.entries(v).forEach(([kk, vv]) => fd.append(k + '[' + kk + ']', vv));
            else fd.append(k, v);
        });
        const cp = document.querySelector('meta[name=csrf-param]')?.content;
        if (cp) fd.append(cp, document.querySelector('meta[name=csrf-token]').content);
        return fetch($actUrl, { method: 'POST', body: fd }).then(r => r.json());
    }
    let done = false;
    document.getElementById('Кнопка20').addEventListener('click', async () => {
        document.activeElement?.blur();
        const items = {};
        kols.forEach(i => { const v = parseInt(i.value, 10); if (v > 0) items[i.closest('tr').dataset.id] = v; });
        const res = await post({ act: 'AddSave', items });
        if (res.msg) await msgBox(res.msg, { icon: res.ok ? 'info' : 'error' });
        if (res.close) { done = true; closeAccessDialog(true); }
    });
    document.getElementById('Кнопка21').addEventListener('click', () => { done = true; post({ act: 'AddCancel' }); closeAccessDialog(false); });
    // закрыли иначе (Esc) — очистить временную таблицу
    window.addEventListener('pagehide', () => {
        if (done) return;
        const fd = new FormData(); fd.append('act', 'AddCancel');
        const cp = document.querySelector('meta[name=csrf-param]')?.content;
        if (cp) fd.append(cp, document.querySelector('meta[name=csrf-token]').content);
        navigator.sendBeacon($actUrl, fd);
    });
    kols[0]?.focus(); kols[0]?.select();
})();
JS, View::POS_END);
