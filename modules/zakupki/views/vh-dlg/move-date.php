<?php
/**
 * frmFndIsp4Nakl, режим 2 «Перенос даты счета». Раскладка из SaveAsText: ширина 9960 твипов (664 px), данные 4592 (306),
 * примечание 636 (42). Подформа Isp 113,383 — 9735 × 4155 (8,26 — 649 × 277); поле поиска скрыто.
 * Внизу: ChoiceRab 30,60 (✓), флажок All2One 737,79 с подписью «Выбрать все», CloseFrm 9285,15.
 * Подформа subFrmFndIsp без выгрузки: столбцы IsSel, Num, ID, FullName «Наименование» (Kol скрыт), ширины условные.
 * Выбор строк — флажки IsSel здесь, на экране; «Выбрать все» ещё и пишет xp_UpdAllPos4Date, как в Access.
 * @var yii\web\View $this
 * @var int $id
 * @var array $rows
 */
use yii\web\View;

$this->title = 'Перенос даты счета';
echo $this->render('_dlg', ['id' => 0]);
$all = $rows && !array_filter($rows, fn($r) => empty($r['IsSel']));
?>
<div class="access-form" id="frmFndIsp4Nakl" style="width:664px;height:348px">
    <div class="dlg-sub" style="left:8px;top:26px;width:649px;height:277px">
        <?= $this->render('@app/views/access/_grid', ['tid' => 'Isp', 'key' => 'vhDlg.moveDate', 'rows' => $rows, 'idField' => 'IspolnitID',
            'dataFields' => ['FullName'], 'cols' => [
                ['IsSel',    '',             30,  ['chk' => 1, 'cls' => 'c-IsSel']],
                ['Num',      'Num',          60,  []],
                ['ID',       'ID',           70,  ['al' => 'r']],
                ['FullName', 'Наименование', 460, []],
            ]]) ?>
    </div>
    <div class="dlg-ft" style="top:306px;height:42px"></div>
    <button type="button" class="a-btn big" id="ChoiceRab" title="Выбрать" style="left:2px;top:310px;width:38px;height:38px">✓</button>
    <input type="checkbox" class="a-opt" id="All2One" <?= $all ? 'checked' : '' ?> style="left:49px;top:311px;width:13px;height:13px">
    <label class="a-lbl" for="All2One" style="left:64px;top:310px;width:187px;height:16px">Выбрать все</label>
    <button type="button" class="a-btn big" id="CloseFrm" title="Закрыть форму" style="left:619px;top:307px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const t = document.getElementById('Isp');
    dlgRows(t);
    t.querySelectorAll('td.c-IsSel input').forEach(x => x.disabled = false);
    // All2One_MouseDown: UpdAll(ScID, 1|0) и все флажки разом
    document.getElementById('All2One').addEventListener('change', async e => {
        const on = e.target.checked;
        t.querySelectorAll('td.c-IsSel input').forEach(x => x.checked = on);
        const r = await dlgPost('PosAll', { sch: $id, on: on ? 1 : 0 });
        if (!r.ok && r.msg) await msgBox(r.msg);
    });
    // ChoiceRab_Click (Case 2): дата (GetInfoDta → календарь), затем каждая выбранная позиция; карточка перечитывается
    document.getElementById('ChoiceRab').addEventListener('click', async () => {
        const pos = [...t.tBodies[0]?.rows || []].filter(tr => tr.querySelector('td.c-IsSel input')?.checked)
            .map(tr => ({ id: tr.dataset.id, name: (JSON.parse(tr.dataset.row || '{}').FullName) || '' }));
        if (!pos.length) { await msgBox('Не выбрано ни одной позиции'); return; }
        const d = new Date(), today = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        const date = await dateBox('Новая дата поставки', { value: today });
        if (!date) return;
        const r = await dlgPost('MoveDate', { date, pos: JSON.stringify(pos) });
        if (r.msg) await msgBox(r.msg, { icon: r.ok ? 'info' : 'warn' });
        if (r.ok) closeAccessDialog(true);
    });
    document.getElementById('CloseFrm').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
