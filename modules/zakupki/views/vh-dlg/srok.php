<?php
/**
 * frmChoice4InControl, режим 101 — «Сроки поставки»: выбор поставщика. Раскладка из Form_Open (Case Else): окно 14000 твипов
 * (933 px; «Отмена» в Access выходит за край — здесь окно шире), высота 1600 (107); Head 0,0 — 813 × 67, шрифт 12;
 * кнопки top 1100 (73), 1500 × 450 (100 × 30) через 50 твипов. OptionValue кнопки = Tip загрузки (SrokService::TIPS).
 * Выбор поставщика сразу открывает выбор файла (ShowFileDialogGlobal), файл грузится отсюда же.
 * «Витязь» в Access имеет OptionValue 4 (как «Мамонт»), ветка Case 9 недостижима — кнопка пока неактивна.
 * @var yii\web\View $this
 */
use yii\helpers\Html;
use yii\web\View;

$this->title = 'Внимание';
echo $this->render('_dlg', ['id' => 0]);
$b = [[6, 'КОМПЭЛ'], [7, 'МТ-Систем'], [1, 'ИНТЭКОН'], [8, 'Стрелой Е'], [3, 'АМК ЮНИОН'], [4, 'Мамонт'], [9, 'Витязь'], [10, 'Аврора']];
?>
<div class="access-form" id="frmChoice4InControl" style="width:957px;height:107px">
    <div class="a-lbl" style="left:0;top:0;width:813px;height:67px;font-size:16px">Выберите поставщика</div>
    <?php foreach ($b as $k => [$tip, $cap]): $off = $tip === 9; ?>
        <button type="button" class="a-btn srok" data-tip="<?= $tip ?>" <?= $off ? 'disabled title="В Access эта кнопка запускает загрузку «Мамонта» (OptionValue 4) — уточняем, как должно быть"' : '' ?>
            style="left:<?= round((20 + $k * 1550) / 15) ?>px;top:73px;width:100px;height:30px;font-size:13px"><?= Html::encode($cap) ?></button>
    <?php endforeach ?>
    <button type="button" class="a-btn" id="Cancel" style="left:<?= round((20 + 8 * 1550) / 15) ?>px;top:73px;width:127px;height:30px;font-size:13px">Отмена</button>
    <input type="file" id="fle" accept=".xls,.xlsx" style="display:none">
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const fle = document.getElementById('fle');
    let tip = 0;
    // Группа5_AfterUpdate → KodNullEl; в frmStat — ShowFileDialogGlobal(1, …), FileCopy на \\\\TSRV, проверка строк, xp_SchKompelControlSrokLoadFle1
    document.querySelectorAll('.srok').forEach(b => b.addEventListener('click', () => { tip = +b.dataset.tip; fle.value = ''; fle.click(); }));
    fle.addEventListener('change', async () => {
        if (!fle.files[0]) return;
        document.body.style.cursor = 'wait';
        document.querySelectorAll('button').forEach(x => { x.dataset.was = x.disabled ? 1 : ''; });
        document.querySelectorAll('button').forEach(x => x.disabled = true);
        try {
            const r = await dlgPost('SrokLoad', { tip, file: fle.files[0] });
            await msgBox(r.msg || (r.ok ? 'Загрузка завершена' : 'Ошибка загрузки'), { icon: r.ok ? 'info' : (r.icon || 'warn') });
            if (r.ok) closeAccessDialog(true);
        } finally {
            document.body.style.cursor = '';
            document.querySelectorAll('button').forEach(x => x.disabled = !!x.dataset.was);
        }
    });
    document.getElementById('Cancel').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
