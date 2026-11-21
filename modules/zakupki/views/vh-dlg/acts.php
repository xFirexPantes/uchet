<?php
/**
 * frmActsAndReps — «Акты» (tip 0) и «Отчеты» (tip 1) по TP счёта. Раскладка из SaveAsText: ширина 4620 твипов = 308 px,
 * высота 4725 (315); список lst 4,5 — 293 × 259. «Открыть» (OpenRep): файл по номеру из столбца 0 — vh-dlg/act-file
 * (путь как GetNameAktF6 / GetNameFileInControl, папка \\TSRV должна быть подключена на сервере: components/UncPath).
 * @var yii\web\View $this
 * @var int $tip
 * @var array $rows  xp_GetRepsAndActs (по номерам столбцов)
 */
use app\components\AccessFormat as F;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = $tip === 0 ? 'Акты' : 'Отчеты';
echo $this->render('_dlg', ['id' => 0]);
$fileUrl = Json::encode(Url::to(['act-file']));
?>
<div class="access-form" id="frmActsAndReps" style="width:308px;height:315px">
    <div class="dlg-sub" style="left:4px;top:5px;width:293px;height:259px">
        <table id="lst" class="a-grid" style="width:100%">
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr data-id="<?= Html::encode((string)($r[0] ?? '')) ?>"><?php foreach ($r as $v): ?><td title="<?= Html::encode(F::value($v)) ?>"><?= Html::encode(F::value($v)) ?></td><?php endforeach ?></tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="a-btn" id="OpenRep" title="Открыть (скачать) файл" style="left:14px;top:275px;width:77px;height:32px;font-size:12px">Открыть</button>
    <button type="button" class="a-btn big" id="ExitBut" title="Закрыть" style="left:236px;top:270px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
dlgRows(document.getElementById('lst'));
// OpenRep_Click: файл выбранной строки скачивается; нет файла — «Не найден файл»
document.getElementById('OpenRep').addEventListener('click', async () => {
    const tr = dlgSel(document.getElementById('lst')); if (!tr) return;
    const r = await fetch($fileUrl + '?' + new URLSearchParams({ nakt: tr.dataset.id, tip: $tip }));
    if (!r.ok || (r.headers.get('content-type') || '').includes('json')) {
        const j = await r.json().catch(() => ({}));
        await msgBox(j.msg || j.message || ('Ошибка ' + r.status));
        return;
    }
    const cd = r.headers.get('content-disposition') || '';
    const name = decodeURIComponent(cd.match(/filename\*=utf-8''([^;]+)/i)?.[1] || cd.match(/filename="?([^";]+)/i)?.[1] || 'file.xls');
    const a = document.createElement('a'); a.href = URL.createObjectURL(await r.blob()); a.download = name;
    document.body.appendChild(a); a.click(); a.remove();
});
document.getElementById('ExitBut').addEventListener('click', () => closeAccessDialog(false));
JS, View::POS_END);
