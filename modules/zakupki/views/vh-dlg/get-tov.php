<?php
/**
 * frmGetTov «Забрать товар». Раскладка из SaveAsText: ширина 15363 твипа = 1024 px; данные 7993 (533), примечание 689 (46);
 * подформа GetTov 11,4 — 1009 × 525. Подформа SubfrmGetTov (таблица): столбцы и ширины из SaveAsText, Idsc скрыт;
 * флажки «Писать адрес» (xp_UpdateWriteAdr UchetID) и «Доставлено» (xp_UpdateShipped Idsc) меняются щелчком.
 * Фильтр fltSt: 0 «Забрать», 1 «Доставлено». Excel (ToXl) — xp_GetTakeMe4Excel2, после выгрузки окно закрывается, как в Access.
 * @var yii\web\View $this
 * @var int $flt
 * @var array $rows
 */
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Забрать товар';
echo $this->render('_dlg', ['id' => 0]);
$url = Json::encode(Url::to(['get-tov'])); $xl = Json::encode(Url::to(['tov-xl']));
?>
<div class="access-form" id="frmGetTov" style="width:1024px;height:579px">
    <div class="dlg-sub" style="left:11px;top:4px;width:1009px;height:525px">
        <?= $this->render('@app/views/access/_grid', ['tid' => 'GetTov', 'key' => 'vhDlg.getTov', 'rows' => $rows,
            'dataFields' => ['Idsc', 'UchetID'], 'cols' => [
            ['Org',          'Оранизация',   121, []],
            ['NumScet',      'Номер счёта',  101, []],
            ['DtaScet',      'Дата счёта',   76,  []],
            ['Pok',          'Покупатель',   103, []],
            ['DtaRes',       'Дата резерва', 91,  []],
            ['AdrF',         'Адрес',        322, []],
            ['WriteAdr',     'Писать адрес', 88,  ['chk' => 1, 'cls' => 'c-WriteAdr']],
            ['shipped',      'Доставлено',   75,  ['chk' => 1, 'cls' => 'c-shipped']],
            ['MainPlaceTxt', 'Склад',        100, []],
        ]]) ?>
    </div>
    <div class="dlg-ft" style="top:533px;height:46px"></div>
    <button type="button" class="a-btn big" id="ToXl" title="Выгрузить в Excel" style="left:4px;top:537px;width:38px;height:38px">XLS</button>
    <div class="a-lbl" style="left:746px;top:546px;width:95px;height:16px">Фильтр:</div>
    <div class="a-select" style="left:859px;top:546px;width:113px;height:17px">
        <select id="fltSt"><option value="0" <?= $flt === 0 ? 'selected' : '' ?>>Забрать</option><option value="1" <?= $flt === 1 ? 'selected' : '' ?>>Доставлено</option></select>
    </div>
    <button type="button" class="a-btn big" id="ClsFrm" title="Закрыть форму" style="left:983px;top:537px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const \$ = id => document.getElementById(id);
    dlgRows(\$('GetTov'));
    // флажки: процедура меняет флажок сама; не удалось — флажок возвращается, сообщение как в Access
    ['WriteAdr', 'shipped'].forEach(f => document.querySelectorAll('#GetTov td.c-' + f + ' input').forEach(cb => {
        cb.disabled = false;
        cb.addEventListener('click', async e => {
            e.preventDefault();
            const d = JSON.parse(cb.closest('tr').dataset.row || '{}');
            const r = await dlgPost('TovFlag', { flag: f, key: f === 'WriteAdr' ? (d.UchetID ?? '') : (d.Idsc ?? '') });
            if (!r.ok) { if (r.msg) await msgBox(r.msg); return; }
            location.reload();
        });
    }));
    \$('fltSt').addEventListener('change', e => { location.href = $url + '?flt=' + e.target.value; });   // fltSt_AfterUpdate
    \$('ToXl').addEventListener('click', async () => {                                                   // ToXl_Click
        const b = \$('ToXl'); b.disabled = true; document.body.style.cursor = 'wait';
        try {
            const r = await fetch($xl);
            if (!r.ok || (r.headers.get('content-type') || '').includes('json')) {
                const j = await r.json().catch(() => ({}));
                await msgBox(j.msg || j.message || ('Ошибка ' + r.status), { icon: 'warn' });
                return;
            }
            const name = decodeURIComponent((r.headers.get('content-disposition') || '').match(/filename\*=utf-8''([^;]+)/i)?.[1] || 'Забрать_товар.xlsx');
            const a = document.createElement('a'); a.href = URL.createObjectURL(await r.blob()); a.download = name;
            document.body.appendChild(a); a.click(); a.remove();
            setTimeout(() => closeAccessDialog(false), 300);
        } finally { b.disabled = false; document.body.style.cursor = ''; }
    });
    \$('ClsFrm').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
