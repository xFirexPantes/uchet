<?php
/**
 * Карточка счёта на детали (frmScetaDetal, «Счёт на детали»), шаг А: просмотр 1 в 1.
 * Раскладка: layout/frmScetaDetal.php. Подформы: LstPosSch = subfrmScetaDetal (позиции),
 * lstDogs (договоры), lstNakl (накладные/акты), xListINN (фирмы).
 *
 * @var yii\web\View $this
 * @var int $id
 * @var array $d  данные из SchetService::getDetal
 */
use app\components\AccessFormat as F;
use app\modules\zakupki\services\ScreenRules;

$rec = $d['rec'];
$this->title = 'Счёт на детали ' . ($rec['NumSc'] ?? '');

// Что можно менять, что видно, фон: правила экрана (services/ScreenRules::schetDetal — Form_Load, RepaintSchDetal)
$st = ScreenRules::schetDetal($d);

// Позиции (subfrmScetaDetal): порядок = порядок перехода (TabIndex), скрытые ScID, ID1C, Who, DT, IDPos не показываем
$posCols = [
    ['SkladID',    'SkladID',             59,  []],
    ['Nme',        'Наименование',        233, []],
    ['AbsID',      'ID Техкарты',         80,  []],
    ['FullNme',    'Точное наименование', 367, []],
    ['Kol',        'Кол-во',              87,  ['al' => 'r']],
    ['Nom',        'Номинал',             96,  []],
    ['Korp',       'Корпус',              96,  []],
    ['Prc',        'Цена',                87,  ['al' => 'r']],
    ['NDS',        'Ставка НДС',          73,  ['al' => 'r']],
    ['Sm',         'Сумма',               107, ['al' => 'r']],
    ['SmNDS',      'Сумма НДС',           100, ['al' => 'r']],
    ['Vsego',      'Всего',               127, ['al' => 'r']],
    ['PostDt',     'Готовность ожид.',    96,  []],
    ['DP',         'ДП',                  96,  []],
    ['TakeMeDate', 'Заберите меня',       96,  []],
    ['Ned',        'Недополучено',        96,  ['al' => 'r']],
];

$slots = [
    'LstPosSch' => $this->render('@app/views/access/_grid', ['tid' => 'pos', 'key' => 'schet.detal.pos', 'cols' => $posCols,
                      'rows' => $d['pos'], 'idField' => 'IDPos']),
    'lstDogs'   => $this->render('_dogs', ['rows' => $d['dogs']]),
    'lstNakl'   => $this->render('_nakl', ['rows' => $d['nakl'], 'key' => 'schet.detal.nakl']),
    // «Посмотреть» (OpnFrm): открыть выбранную накладную (скрипт в _script.php)
    'OpnFrm'      => '<button type="button" class="a-btn" id="OpnFrm" title="Просмотр накладных">Посмотреть</button>',
    'xListINN'  => $this->render('_firms', ['rows' => $d['firms'], 'key' => 'schet.detal.firms']),
    // ✎ — открыть позицию в окне (скрипт ниже), только у открытого счёта
    'EdtPosSchDet' => isset($st['edit']['EdtPosSchDet'])
        ? '<button type="button" class="a-btn" id="EdtPosSchDet" title="Изменить позицию">✎</button>'
        : '<button type="button" class="a-btn" disabled title="Изменить позицию: ' . $st['off']['EdtPosSchDet'] . '">✎</button>',
    // ＋ butDob_Click: CheckNumSch(False) на сервере, потом frmScetaDetalAdd с новой позицией (IDPos 0); скрипт в _edit.php
    'butDob'    => isset($st['edit']['EdtPosSchDet'])
        ? '<button type="button" class="a-btn" id="butDob" title="Добавить позицию">＋</button>'
        : '<button type="button" class="a-btn" disabled title="Добавить позицию: ' . $st['off']['EdtPosSchDet'] . '">＋</button>',
    // AllowEd у деталей всегда True: ✕ — закрытие с проверками (butExit_Click)
    'butExit'   => '<button type="button" class="a-btn act" data-act="butExit" title="Закрыть счёт (с проверками, как в Access)">✕</button>',
];
$calc = [
    'SumSchPerPos' => $d['calc']['SumSchPerPos'],
    'TakeMe'       => $d['calc']['TakeMe'],
    'IsTo1C'       => $st['flags']['to1C'],
    'NotUsed'      => $st['flags']['notUsed'],
];
$icons = [
    'butDob'       => ['＋', 'Добавить позицию'],
    'EdtPosSchDet' => ['✎', 'Изменить позицию'],
    'DelRec'       => ['−', 'Удалить позицию'],
    'butSaveAs'    => ['💾', 'Сохранить счёт'],
    'OpnSch'       => ['🔓', 'Открыть закрытый счёт'],
    'LoadM15'      => ['М15', 'Загрузить из М15'],
];
$tips = ['MassSetDTPost' => 'Установить дату «Поставка» всем строкам', 'MassSetDTDP' => 'Установить дату «ДП» всем строкам'];
foreach ($tips as $n => $t) $icons[$n] = ['Установить', $t];

$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/access-dialog.js');
// EdtPosSchDet_Click: окно frmScetaDetalAdd по выбранной позиции (если не выбрана — первая), после ✓ список перечитывается
$posUrl = \yii\helpers\Json::encode(\yii\helpers\Url::to(['pos-detal', 'id' => $id]));
$this->registerJs(<<<JS
document.getElementById('EdtPosSchDet')?.addEventListener('click', () => {
    const tb = document.querySelector('#pos tbody'), tr = tb?.querySelector('tr.sel') || tb?.rows[0];
    if (!tr) { msgBox('Выберите элемент для редактирования'); return; }
    accessDialog($posUrl + '&pos=' + encodeURIComponent(tr.dataset.id), 647, 135).then(r => { if (r.reload) location.reload(); });
});
JS);
?>
<div class="access-form" id="frmScetaDetal" style="width:1716px;height:843px;background:<?= F::color($st['bg']) ?>">
<?= $this->render('@app/views/access/_form', [
    'layout' => require __DIR__ . '/layout/frmScetaDetal.php',
    'rec' => $rec, 'calc' => $calc, 'combos' => $d['combos'],
    'state' => $st, 'slots' => $slots, 'icons' => $icons,
]) ?>
</div>
<?= $this->render('_script', ['id' => $id, 'resUrl' => null]) ?>
<?= $this->render('_edit', ['id' => $id]) ?>
