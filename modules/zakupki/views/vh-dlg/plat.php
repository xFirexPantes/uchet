<?php
/**
 * frmPlatFrom1C «Платежи по счёту». Раскладка из SaveAsText: ширина 6472 твипа = 431 px; шапка 396 (26), данные 2154 (144),
 * примечание 680 (45). Подформа SubFrmPlatFrom1C (таблица): столбцы по TabIndex — Nm, Dta, Sm, Inf; ID и Stat скрыты.
 * Для счёта на детали (det) — xp_SubFrmPlatFrom1C ID,1 и без кнопок отправки.
 * @var yii\web\View $this
 * @var int $id
 * @var bool $det
 * @var string $nsch
 * @var array $rows
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Платежи по счёту';
echo $this->render('_dlg', ['id' => $id]);
$mailUrl = Json::encode(Url::to(['mail-txt']));
?>
<div class="access-form" id="frmPlatFrom1C" style="width:431px;height:215px">
    <div class="a-lbl" style="left:4px;top:4px;width:115px;height:19px;font-size:13px">Платежи по счёту:</div>
    <div class="a-txt" style="left:121px;top:4px;width:110px;height:19px;font-size:13px"><input type="text" readonly value="<?= Html::encode($nsch) ?>"></div>
    <div class="dlg-sub" style="left:4px;top:30px;width:427px;height:135px">
        <?= $this->render('@app/views/access/_grid', ['tid' => 'SubFrmPlatFrom1C_1', 'key' => 'vhDlg.plat', 'rows' => $rows, 'idField' => 'ID', 'cols' => [
            ['Nm',  '№ и дата плат.', 97,  []],
            ['Dta', 'Дата оплаты',    78,  ['fmt' => 'dd.mm.yyyy']],
            ['Sm',  'Сумма',          129, ['al' => 'r']],
            ['Inf', 'Инфо',           92,  []],
        ]]) ?>
    </div>
    <div class="dlg-ft" style="top:170px;height:45px"></div>
<?php if (!$det): ?>
    <button type="button" class="a-btn big" id="SendInfo" title="отправить e-mail" style="left:4px;top:174px;width:38px;height:38px">✉</button>
    <button type="button" class="a-btn big" id="DontSend" title="не надо отправлять e-mail" style="left:45px;top:174px;width:38px;height:38px">⊘</button>
<?php endif ?>
    <button type="button" class="a-btn big" id="Кнопка4" title="Закрыть форму" style="left:393px;top:174px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const \$ = id => document.getElementById(id), t = \$('SubFrmPlatFrom1C_1');
    dlgRows(t);
    // SendInfo_Click: состояние 0 или 2 (переспросить), адрес xp_GetEMailZac, окно письма Tip 1
    \$('SendInfo')?.addEventListener('click', async () => {
        const tr = dlgSel(t); if (!tr || !tr.dataset.id) return;
        const p = await dlgPost('PlatPrep', { plat: tr.dataset.id });
        if (!p.ok) { if (p.msg) await msgBox(p.msg, { icon: p.icon || 'info' }); return; }
        if (p.stat === 2 && !(await askYesNo('Информация о данной платёжке уже была отправлена, но она была перепроведена. Отправить ещё раз?', { def: 'no' }))) return;
        const r = await accessDialog($mailUrl + '?' + new URLSearchParams({ id: $id, tip: 1, plat: tr.dataset.id, email: p.email }), 911, 503);
        if (r.reload) location.reload();
    });
    // DontSend_Click: состояние 0, 2, 3 → 3 «не отправлять»
    \$('DontSend')?.addEventListener('click', async () => {
        const tr = dlgSel(t); if (!tr || !tr.dataset.id) return;
        const r = await dlgPost('PlatDont', { plat: tr.dataset.id });
        if (!r.ok) { if (r.msg) await msgBox(r.msg); return; }
        location.reload();
    });
    \$('Кнопка4').addEventListener('click', () => closeAccessDialog(true));
})();
JS, View::POS_END);
