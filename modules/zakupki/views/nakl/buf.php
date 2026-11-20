<?php
/**
 * ProdPlanSnd2Buf — диалог «Составляющие детали на буферный склад» (fake = 0) или «… в накладную» (fake = 1).
 * Ширина формы 15191 твипов = 1013 px; область данных 2771 + примечание 576 = 223 px. Координаты = твипы / 15.
 * lstSnd2Buf (subProdPlanSnd2Buf) на 4,4 размером 1009 × 177. Внизу: btnSend2Buf «Отправить на буферный склад» слева, ClsFrm справа.
 *
 * @var yii\web\View $this
 * @var int   $id    накладная
 * @var int   $sch   счёт
 * @var int   $fake  OpenArgs
 * @var array $rows  xp_ProdPlanSnd2BufRS
 */
use app\modules\zakupki\services\BufService;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = BufService::CAPTION[$fake] ?? BufService::CAPTION[0];
$g = require __DIR__ . '/_buf-cols.php';
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/access-dialog.js');
?>
<style>
#ProdPlanSnd2Buf .a-grid tbody tr{cursor:default}
#gbuf td.g-chk input{pointer-events:none}
#gbuf td.g-chk{cursor:pointer}
#gbuf td.g-kol,#gbuf td.g-sm{cursor:pointer}
#ProdPlanSnd2Buf .ft{position:absolute;left:0;top:185px;width:1013px;height:38px;border-top:1px solid #aca899}
</style>
<div class="access-form" id="ProdPlanSnd2Buf" style="width:1013px;height:223px">
    <div id="lstSnd2Buf" style="position:absolute;overflow:auto;background:#fff;border:1px solid #7f9db9;box-sizing:border-box;left:4px;top:4px;width:1009px;height:177px">
        <?= $this->render('@app/views/access/_grid', $g + ['rows' => $rows]) ?>
    </div>
    <div class="ft"></div>
    <button type="button" class="a-btn" id="btnSend2Buf" title="<?= $fake ? 'Перенести в накладную' : 'Отправить на буферный склад' ?>" style="left:0;top:185px;width:38px;height:38px">⇩</button>
    <button type="button" class="a-btn" id="ClsFrm" title="Закрыть форму" style="left:971px;top:185px;width:38px;height:38px">✕</button>
</div>
<?php
$actUrl = Json::encode(Url::to(['buf-act', 'id' => $id, 'sch' => $sch, 'fake' => $fake]));
$ask = Json::encode($fake ? 'Перенести выбранные составляющие детали в накладную?' : 'Перенести выбранные составляющие на буферный склад?');
$this->registerJs(<<<JS
(function () {
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    const T = id => document.getElementById(id);
    const tbl = T('gbuf');
    window.colResize && colResize(tbl, tbl.dataset.key);

    function pick(id) {                               // Recordset.Find "IDStr = '…'"
        tbl.querySelectorAll('tbody tr.sel').forEach(x => x.classList.remove('sel'));
        const tr = id == null ? null : tbl.querySelector('tbody tr[data-id="' + CSS.escape(String(id)) + '"]');
        if (tr) { tr.classList.add('sel'); tr.scrollIntoView({ block: 'nearest' }); }
    }
    function post(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
        if (csrfParam) fd.append(csrfParam, csrfToken);
        document.body.style.cursor = 'wait';
        return fetch($actUrl, { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })))
            .finally(() => { document.body.style.cursor = ''; });
    }
    async function act(data, keep) {
        const res = await post(data);
        if (res.html) {                                 // RecordSource = RecordSource: строки заново, ширины остаются
            const tmp = document.createElement('div'); tmp.innerHTML = res.html;
            const nb = tmp.querySelector('tbody'); if (nb) tbl.replaceChild(nb, tbl.tBodies[0]);
        }
        pick(keep);
        if (res.msg) await msgBox(res.msg, { icon: res.ok ? 'info' : 'warn' });
        return res;
    }

    tbl.addEventListener('click', e => {
        const tr = e.target.closest('tbody tr'); if (!tr) return;
        pick(tr.dataset.id);
        if (e.target.closest('td.g-chk')) act({ act: 'Chk', str: tr.dataset.id }, tr.dataset.id);   // Chk_MouseDown
    });
    tbl.addEventListener('dblclick', async e => {
        const td = e.target.closest('tbody td'); if (!td) return;
        const tr = td.closest('tr'), kol = td.classList.contains('g-kol');
        if (!kol && !td.classList.contains('g-sm')) return;
        // GetFromBox("Введите количество", , 1) / GetFromBox("Введите сумму", , 0): запятая = точка, не число — сообщение
        const s = await inputBox(kol ? 'Введите количество' : 'Введите сумму', '');
        if (s === null) return;
        if (!/^\\s*-?\\d+([.,]\\d*)?\\s*$/.test(s)) { await msgBox('Необходимо ввести числовое значение'); return; }
        act({ act: kol ? 'Kol' : 'Sm', str: tr.dataset.id, value: s.trim().replace(',', '.') }, tr.dataset.id);
    });
    // btnSend2Buf_Click
    T('btnSend2Buf').addEventListener('click', async () => {
        if (!(await askYesNo($ask))) return;
        act({ act: 'Send' }, tbl.querySelector('tbody tr.sel')?.dataset.id);
    });
    T('ClsFrm').addEventListener('click', () => closeAccessDialog(true));
})();
JS, View::POS_END);
