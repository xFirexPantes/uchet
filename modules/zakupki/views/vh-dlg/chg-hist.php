<?php
/**
 * frmChgPredShcetaHistory — история изменений счетов. Раскладка из SaveAsText (твипы / 15 = px):
 * Rgm 1 (из «Входящих счетов»): ширина 21500 = 1433 px, шапка 1410 (94), данные 3964 (264), примечание 1020 (68);
 * Rgm 0 (из карточки): шапки нет, ширина 15600 = 1040 px, столбцы NumSch, DSch, Zac скрыты.
 * Подформа subfrmChgPredShcetaHistory (таблица): столбцы по TabIndex, подписи и ColumnWidth из SaveAsText (без ширины — 1" = 96 px), ID скрыт.
 * btShowCard («К», карточка элемента FObnovlen1) — пока неактивна. Фильтр меняется — окно перечитывается с новыми параметрами.
 * @var yii\web\View $this
 * @var array $a      параметры (rgm, all, allId, shcId; для rgm 1 фильтры)
 * @var array $rows
 * @var mixed $SmChgR
 * @var mixed $SmChgD
 */
use app\components\AccessFormat as F;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$r1 = $a['rgm'] === 1;
$this->title = $r1 ? 'Изменение счётов' : ($a['all'] ? 'Изменение счёта' : 'Изменение позиции счёта');
echo $this->render('_dlg', ['id' => 0]);
$W = $r1 ? 1433 : 1040; $H0 = $r1 ? 94 : 0;
$hide = $r1 ? ['ID'] : ['ID', 'NumSch', 'DSch', 'Zac'];
$ru = fn($d) => $d ? date('d.m.y', strtotime($d)) : '';
$sum = fn($v) => $v === null ? '' : number_format((float)$v, 2, ',', ' ');
$url = Json::encode(Url::to(['chg-hist']));
$A = Json::encode($a);
$fill = '#edf6f9';   // BackColor 16381933
// Rgm 0: итоги стоят за правым краем формы Access (InsideWidth 15600) — здесь сдвинуты в видимую часть
$sx = $r1 ? 1092 : 690;
?>
<style>
#frmChgPredShcetaHistory .h { position:absolute; font-size:13px; }
#frmChgPredShcetaHistory .h.b { font-weight:bold; text-align:center; }
#frmChgPredShcetaHistory .ro input { background:<?= $fill ?>; cursor:pointer; }
#frmChgPredShcetaHistory .dt input { background:transparent; padding:0 1px; font-size:12px; }
</style>
<div class="access-form" id="frmChgPredShcetaHistory" style="width:<?= $W ?>px;height:<?= $H0 + 264 + 68 ?>px">
<?php if ($r1): ?>
    <div class="a-lbl h b" style="left:113px;top:4px;width:114px;height:19px">Изменение</div>
    <div class="a-lbl h" style="left:113px;top:29px;width:19px;height:17px">с</div>
    <div class="a-txt dt" style="left:132px;top:29px;width:53px;height:17px;font-size:13px"><input type="text" readonly id="BDta" value="<?= $ru($a['bdta']) ?>"></div>
    <div class="a-lbl h" style="left:113px;top:48px;width:19px;height:16px">по</div>
    <div class="a-txt dt" style="left:132px;top:48px;width:53px;height:17px;font-size:13px"><input type="text" readonly id="EDta" value="<?= $ru($a['edta']) ?>"></div>
    <button type="button" class="a-btn" id="GetInt" style="left:187px;top:26px;width:41px;height:38px;font-weight:bold;font-size:12px">Даты</button>

    <div class="a-lbl h b" style="left:301px;top:4px;width:145px;height:17px">Событие</div>
    <div class="a-txt ro" style="left:303px;top:29px;width:140px;height:17px;font-size:13px"><input type="text" readonly id="fltWHtxt" value="<?= Html::encode($a['whTxt']) ?>" title="Двойной щелчок — выбрать, Delete — очистить"></div>
    <div class="a-lbl h b" style="left:634px;top:4px;width:143px;height:17px">Причина</div>
    <div class="a-txt ro" style="left:635px;top:29px;width:140px;height:17px;font-size:13px"><input type="text" readonly id="fltReasTxt" value="<?= Html::encode($a['reasTxt']) ?>" title="Двойной щелчок — выбрать, Delete — очистить"></div>
    <div class="a-lbl h b" style="left:779px;top:4px;width:92px;height:16px">№ счёта</div>
    <div class="a-txt" style="left:779px;top:29px;width:91px;height:17px;font-size:13px"><input type="text" id="fltSch" maxlength="50" value="<?= Html::encode($a['sch']) ?>" title="Enter — применить, Delete — очистить"></div>

    <div class="a-lbl h b" style="left:875px;top:4px;width:115px;height:19px">Дата счёта</div>
    <div class="a-lbl h" style="left:876px;top:29px;width:19px;height:17px">с</div>
    <div class="a-txt dt" style="left:895px;top:29px;width:53px;height:17px;font-size:13px"><input type="text" readonly id="BDSch" value="<?= $ru($a['bds']) ?>"></div>
    <div class="a-lbl h" style="left:876px;top:48px;width:19px;height:16px">по</div>
    <div class="a-txt dt" style="left:895px;top:48px;width:53px;height:17px;font-size:13px"><input type="text" readonly id="EDSch" value="<?= $ru($a['eds']) ?>"></div>
    <button type="button" class="a-btn" id="GetIntDSch" style="left:950px;top:26px;width:41px;height:38px;font-weight:bold;font-size:12px">Даты</button>

    <div class="a-lbl h b" style="left:997px;top:4px;width:179px;height:17px">Продавец</div>
    <div class="a-txt ro" style="left:998px;top:29px;width:177px;height:17px;font-size:13px"><input type="text" readonly id="fltZacTxt" value="<?= Html::encode($a['zacTxt']) ?>" title="Двойной щелчок — выбрать, Delete — очистить"></div>
<?php endif ?>
    <div class="dlg-sub" style="left:4px;top:<?= $H0 + 2 ?>px;width:<?= $r1 ? 1420 : 1033 ?>px;height:255px">
        <?= $this->render('@app/views/access/_grid', ['tid' => 'lstPredShcetaHistory', 'key' => 'vhDlg.chgHist' . $a['rgm'], 'rows' => $rows,
            'idField' => 'ID', 'cols' => array_values(array_filter([
                ['WhoTxt', 'Кто', 96, []],
                ['DT', 'Когда', 110, ['fmt' => 'dd.mm.yy hh:nn:ss']],
                ['SkladID', 'SkladID', 71, ['al' => 'r']],
                ['WhatHappen', 'Событие', 142, []],
                ['WasVl', 'Было', 96, ['al' => 'r']],
                ['CurVl', 'Стало', 96, ['al' => 'r']],
                ['Reason', 'Причина', 149, []],
                ['NumSch', '№ счета', 94, []],
                ['DSch', 'Дата счёта', 126, []],
                ['Zac', 'Продавец', 181, []],
                ['ReasonComm', 'Комментарий', 96, []],
                ['Comm', 'Примечание', 229, []],
                ['Cat', 'Каталог', 96, []],
                ['SmChgR', 'Сумма изм.,руб', 96, ['al' => 'r']],
                ['SmChgD', 'Сумма изм.,$', 96, ['al' => 'r']],
            ], fn($c) => !in_array($c[0], $hide, true)))]) ?>
    </div>
    <div class="dlg-ft" style="top:<?= $H0 + 264 ?>px;height:68px"></div>
<?php $t = $H0 + 264; ?>
<?php if (!$r1): ?>
    <input type="checkbox" class="a-opt" id="AllPosSch" <?= $a['all'] ? 'checked' : '' ?> style="left:11px;top:<?= $t + 8 ?>px;width:13px;height:13px">
    <label class="a-lbl" for="AllPosSch" style="left:27px;top:<?= $t + 6 ?>px;width:151px;height:16px;font-size:13px">Все изменения по счёту</label>
<?php endif ?>
    <button type="button" class="a-btn big" id="bntChgReason" title="Изменить причину" style="left:189px;top:<?= $t + 4 ?>px;width:38px;height:38px">✎</button>
    <button type="button" class="a-btn" id="btShowCard" disabled title="Карточка элемента (FObnovlen1): ещё не перенесена" style="left:302px;top:<?= $t + 4 ?>px;width:38px;height:38px;font-size:13px;font-weight:bold">К</button>
    <div class="a-lbl" style="left:<?= $sx ?>px;top:<?= $t + 3 ?>px;width:140px;height:16px;text-align:right">Строк</div>
    <div class="a-txt" style="left:<?= $sx + 144 ?>px;top:<?= $t + 3 ?>px;width:147px;height:17px;text-align:right"><input type="text" readonly id="KolRec" value="<?= count($rows) ?>"></div>
    <div class="a-lbl" style="left:<?= $sx ?>px;top:<?= $t + 22 ?>px;width:140px;height:16px;text-align:right">Сумма изменений счетов,р</div>
    <div class="a-txt" style="left:<?= $sx + 144 ?>px;top:<?= $t + 22 ?>px;width:147px;height:17px;text-align:right"><input type="text" readonly id="SmChgR" value="<?= $sum($SmChgR) ?>"></div>
    <div class="a-lbl" style="left:<?= $sx ?>px;top:<?= $t + 40 ?>px;width:140px;height:16px">Сумма изменений счетов,$</div>
    <div class="a-txt" style="left:<?= $sx + 144 ?>px;top:<?= $t + 40 ?>px;width:147px;height:17px;text-align:right"><input type="text" readonly id="SmChgD" value="<?= $sum($SmChgD) ?>"></div>
    <button type="button" class="a-btn big" id="ClsFrm" title="Закрыть форму" style="left:<?= $r1 ? 1391 : 1000 ?>px;top:<?= $t + 4 ?>px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const \$ = id => document.getElementById(id);
    const a = $A, t = \$('lstPredShcetaHistory');
    dlgRows(t);
    // SetRS4PredShcetaHistory: окно перечитывается с новыми параметрами
    const go = ch => { const p = Object.assign({}, a, ch, { f: 1 }); location.href = $url + '?' + new URLSearchParams(p); };
    \$('AllPosSch')?.addEventListener('change', e => go({ all: e.target.checked ? 1 : 0 }));      // AllPosSch_AfterUpdate
    \$('GetInt')?.addEventListener('click', async () => { const r = await getInterval(); if (r) go({ bdta: r[0], edta: r[1] }); });
    \$('GetIntDSch')?.addEventListener('click', async () => { const r = await getInterval(); if (r) go({ bds: r[0], eds: r[1] }); });
    // фильтры из окон выбора frmfndZac: 21 событие, 20 причина (список «N,»), 15 продавец (UchetID)
    const pick = (el, mode, k, kt, list) => {
        \$(el)?.addEventListener('dblclick', async () => {
            const s = await accessPicker('frmfndZac', mode);
            if (!s || !s.key) return;
            go({ [k]: list ? s.key + ',' : s.key, [kt]: s.text ?? '' });
        });
        \$(el)?.addEventListener('keydown', e => { if (e.key === 'Delete') { e.preventDefault(); go({ [k]: list ? '' : 0, [kt]: '' }); } });
    };
    pick('fltWHtxt', 21, 'wh', 'whTxt', true);
    pick('fltReasTxt', 20, 'reas', 'reasTxt', true);
    pick('fltZacTxt', 15, 'zac', 'zacTxt', false);
    const sch = \$('fltSch');
    if (sch) {
        sch.addEventListener('change', () => go({ sch: sch.value.trim() }));                       // fltSch_AfterUpdate
        sch.addEventListener('keydown', e => {
            if (e.key === 'Delete' && sch.selectionStart === 0 && sch.selectionEnd === sch.value.length) { e.preventDefault(); go({ sch: '' }); }
            if (e.key === 'Enter') { e.preventDefault(); sch.blur(); }
        });
    }
    // bntChgReason_Click: право, причина (frmfndZac 19), комментарий; строка перечитывается
    \$('bntChgReason').addEventListener('click', async () => {
        const tr = dlgSel(t); if (!tr) return;
        const chk = await dlgPost('ChgReason', { hid: 0 });              // только проверка права
        if (!chk.ok && chk.msg) { await msgBox(chk.msg); return; }
        const s = await accessPicker('frmfndZac', 19);
        if (!s || !s.key) return;
        const comm = await inputBox('Комментарий причины', '');         // как InputBox: «Отмена» = пустой комментарий
        const r = await dlgPost('ChgReason', { hid: tr.dataset.id, reason: s.key, comm: comm ?? '' });
        if (!r.ok) { if (r.msg) await msgBox(r.msg); return; }
        go({});
    });
    \$('ClsFrm').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
