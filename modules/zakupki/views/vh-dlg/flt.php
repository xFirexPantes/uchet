<?php
/**
 * frmfltSch «фильтр по счетам», Rgm 0 (из «Входящих счетов», кнопка ▼). Раскладка из SaveAsText: ширина 4308 твипов = 287 px,
 * область данных 1303 (87 px), примечание 632 (42 px). Двойной щелчок по фирме — выбор холдинга (frmfndHolding 1):
 * список фирм перечитывается по холдингу, в поле встаёт название холдинга (cboFirma = OldLine).
 * @var yii\web\View $this
 * @var string $firma
 * @var string $dateS
 * @var string $datePo
 * @var string $holding  выбранный холдинг (список фирм по нему)
 * @var string[] $firms  xp_GetOrg4AllScet
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'фильтр по счетам';
echo $this->render('_dlg', ['id' => 0]);
if ($firma !== '' && !in_array($firma, $firms, true)) array_unshift($firms, $firma);
$url = Json::encode(Url::to(['flt']));
$hold = Json::encode($holding);
?>
<div class="access-form" id="frmfltSch" style="width:287px;height:129px">
    <div class="a-lbl" style="left:8px;top:8px;width:95px;height:23px;font-size:16px">Фирма</div>
    <div class="a-select" style="left:121px;top:8px;width:159px;height:23px;font-size:14px">
        <select id="cboFirma" title="Двойной щелчок — выбор холдинга">
            <option value=""></option>
            <?php foreach ($firms as $f): ?><option <?= $f === $firma ? 'selected' : '' ?>><?= Html::encode($f) ?></option><?php endforeach ?>
        </select>
    </div>
    <div class="a-lbl" style="left:8px;top:38px;width:11px;height:19px;font-weight:bold">c:</div>
    <div class="a-txt" style="left:42px;top:38px;width:83px;height:19px"><input type="date" id="txtDateS" value="<?= Html::encode($dateS) ?>"></div>
    <div class="a-lbl" style="left:162px;top:42px;width:19px;height:19px;font-weight:bold">по:</div>
    <div class="a-txt" style="left:197px;top:42px;width:83px;height:19px"><input type="date" id="txtDatePo" value="<?= Html::encode($datePo) ?>"></div>
    <button type="button" class="a-btn" id="GetInterval" style="left:8px;top:63px;width:272px;height:23px;font-weight:bold;font-size:13px">Интервал</button>
    <div class="dlg-ft" style="top:87px;height:42px"></div>
    <button type="button" class="a-btn big" id="ApproveFlt" title="Применить фильтр" style="left:4px;top:91px;width:38px;height:38px">✓</button>
    <button type="button" class="a-btn big" id="DelFlt" title="Снять фильтр" style="left:45px;top:91px;width:38px;height:38px">⌫</button>
    <button type="button" class="a-btn big" id="ClsFrm" title="Закрыть форму" style="left:246px;top:91px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const \$ = id => document.getElementById(id);
    let holding = $hold;
    const vals = () => ({ firma: \$('cboFirma').value, dateS: \$('txtDateS').value, datePo: \$('txtDatePo').value });
    // SetRowS4cboFirma: список фирм за даты — окно перечитывается с новыми датами, выбранная фирма остаётся
    const refill = () => { location.href = $url + '?' + new URLSearchParams(Object.assign(vals(), holding ? { holding } : {})); };
    // cboFirma_DblClick: frmfndHolding 1 с датами фильтра → фирмы холдинга, в поле — холдинг
    \$('cboFirma').addEventListener('dblclick', async () => {
        const ymd = v => v.replace(/-/g, '');
        const s = await accessPicker('frmfndHolding', 1, ymd(\$('txtDateS').value) + ymd(\$('txtDatePo').value));
        if (!s || !s.text) return;
        holding = s.text;
        location.href = $url + '?' + new URLSearchParams(Object.assign(vals(), { firma: s.text, holding: s.text }));
    });
    \$('txtDateS').addEventListener('change', refill);
    \$('txtDatePo').addEventListener('change', refill);
    \$('GetInterval').addEventListener('click', async () => {             // GetInterval_Click
        const r = await getInterval();
        if (!r) return;
        \$('txtDateS').value = r[0]; \$('txtDatePo').value = r[1]; refill();
    });
    \$('ApproveFlt').addEventListener('click', () => closeAccessDialog(true, vals()));
    \$('DelFlt').addEventListener('click', () => closeAccessDialog(true, { firma: '', dateS: '', datePo: '' }));
    \$('ClsFrm').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
