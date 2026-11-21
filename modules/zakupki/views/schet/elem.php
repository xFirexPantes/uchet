<?php
/**
 * Карточка счёта на элементы (frmMassInputSch), шаг А: просмотр 1 в 1.
 * Раскладка: layout/frmMassInputSch.php (из SaveAsText). Подформы:
 *   LstMassInpt = SubFrmMassInputSch (позиции), lstSchRes = subfrmMassInputSchRes (резервы),
 *   lstDogs = subfrmFObnovlen1_01 (договоры), lstNakl (накладные), xListINN (фирмы).
 *
 * @var yii\web\View $this
 * @var int $id
 * @var array $d  данные из SchetService::getElem
 */
use app\components\AccessFormat as F;
use app\modules\zakupki\services\ScreenRules;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$rec  = $d['rec'];
$user = Yii::$app->user;
$this->title = 'Счёт ' . ($rec['НомерСчета'] ?? '');

// --- Что можно менять, что видно, фон: правила экрана (services/ScreenRules::schetElem — OpenSch, RepaintSch)
$st      = ScreenRules::schetElem($d);
$allowEd = $st['flags']['allowEd'];
$all     = (bool)Yii::$app->request->get('all');            // ShowHiddenBut: показать скрытые столбцы
$btn = fn(string $n, string $tip, string $label) => isset($st['edit'][$n])
    ? '<button type="button" class="a-btn" id="' . $n . '" title="' . $tip . '">' . $label . '</button>'
    : '<button type="button" class="a-btn" disabled title="' . $tip . ': ' . ($st['off'][$n] ?? '') . '">' . $label . '</button>';

// --- Позиции (SubFrmMassInputSch): столбцы общие с накладной (lstSch)
['cols' => $posCols, 'key' => $posKey] = (function () use ($rec, $all) { return require __DIR__ . '/_pos-cols.php'; })();

// --- Резервы (subfrmMassInputSchRes): ColorFld = 1..4 красит одну из дат красным
$red = fn($n) => fn($r) => (int)($r['ColorFld'] ?? 0) === $n ? 'color:' . ($n === 1 ? '#ed1c24' : '#ff0000') . ';' : '';
$resCols = [
    ['NmeRes',      'Резерв',   145, []],
    ['NameProcess', 'Заказ',    52,  []],
    ['Avt',         'Автомат',  46,  ['chk' => 1]],
    ['DtaPostAvt',  'ЖДПА',     61,  ['cf' => $red(1)]],
    ['PlandateAvt', 'ПлСтА',    80,  ['cf' => $red(2)]],
    ['DtaPost',     'ЖДПД',     66,  ['cf' => $red(3)]],
    ['PlandateMnl', 'ПлСтД',    74,  ['cf' => $red(4)]],
    ['InControl',   'Контроль', 56,  []],
    ['ClassVl',     'Риск',     59,  []],
];

$slots = [
    'LstMassInpt' => $this->render('@app/views/access/_grid', ['tid' => 'pos', 'key' => $posKey, 'cols' => $posCols,
                        'rows' => $d['pos'], 'idField' => 'AllID', 'dataFields' => ['SkladID']]),
    'lstSchRes'   => $this->render('@app/views/access/_grid', ['tid' => 'res', 'key' => 'schet.elem.res', 'cols' => $resCols, 'rows' => $d['res']]),
    'lstDogs'     => $this->render('_dogs', ['rows' => $d['dogs']]),
    'lstNakl'     => $this->render('_nakl', ['rows' => $d['nakl'], 'key' => 'schet.elem.nakl']),
    // «Посмотреть» (OpnFrm): открыть выбранную накладную (скрипт в _script.php)
    'OpnFrm'      => '<button type="button" class="a-btn" id="OpnFrm" title="Просмотр накладных">Посмотреть</button>',
    'xListINN'    => $this->render('_firms', ['rows' => $d['firms'], 'key' => 'schet.elem.firms']),
    // ✕: у кого есть право править — закрытие с проверками (butExit_Click), у остальных просто закрыть вкладку
    'butExit'     => $st['flags']['exitChecks']
        ? '<button type="button" class="a-btn act" data-act="butExit" title="Закрыть счёт (с проверками, как в Access)">✕</button>'
        : '<a class="a-btn" href="#" onclick="closeCard();return false" title="Закрыть">✕</a>',
    // ＋ butDob: добавить позицию (облегчённый поиск frmFndElem → окно frmPosInput, режим 5), скрипт в _edit.php
    'butDob'      => $btn('butDob', 'Добавить позицию в счёт', '＋'),
    // ✎ Кнопка15: правка позиции в окне frmPosInput (скрипт ниже), у открытого счёта и при праве править
    'Кнопка15'    => $btn('Кнопка15', 'Изменить позицию', '✎'),
    // Только просмотр, работают и без права править: история цен позиции (frmChgPredShcetaHistory 0), история дат поставки (frmShowLogSklad) и переписка (frmSchPost)
    'btnShowLogPrice' => '<button type="button" class="a-btn" id="btnShowLogPrice" title="История цены и кол-ва выбранной позиции" style="font-weight:bold">Ист</button>',
    'btnShowLogDtaPost' => '<button type="button" class="a-btn" id="btnShowLogDtaPost" title="История даты поставки выбранной позиции" style="font-weight:bold">Ист</button>',
    'MoveDate'        => $id > 0
        ? '<button type="button" class="a-btn" id="MoveDate" title="Перенос даты счета">Перенос поставки</button>'
        : '<button type="button" class="a-btn" disabled title="Перенос даты: счёт ещё не сохранён">Перенос поставки</button>',
    'MailFormBtn'     => $id > 0
        ? '<button type="button" class="a-btn" id="MailFormBtn" title="Переписка по счёту" style="font-size:20px">✉</button>'
        : '<button type="button" class="a-btn" disabled title="Переписка: счёт ещё не сохранён" style="font-size:20px">✉</button>',
    'ShowHiddenBut' => Html::a('⋯', Url::current(['all' => $all ? null : 1]), ['class' => 'a-btn' . ($all ? ' on' : ''), 'title' => 'Показать / спрятать скрытые столбцы позиций']),
];
$kurs = $d['calc']['Kurs'];
$calc = [
    'SumSchPerPos' => $d['calc']['SumSchPerPos'],
    'KontrName'    => $d['calc']['KontrName'],
    'Kurs'         => ['value' => $kurs['value'], 'style' => $kurs['post'] ? 'color:#0000ff;' : ''],   // синий = курс поставщика
    'TakeMe'       => $d['calc']['TakeMe'],
    'IsTo1C'       => (int)($rec['Repl'] ?? 0) !== 0,       // Flag1C
];
$icons = [
    'butDob'            => ['＋', 'Добавить позицию'],
    'Кнопка15'          => ['✎', 'Изменить позицию'],
    'DelRec'            => ['−', 'Удалить позицию'],
    'butSaveAs'         => ['💾', 'Сохранить счёт'],
    'OpnSch'            => ['🔓', 'Открыть закрытый счёт'],
    'LoadM15'           => ['М15', 'Загрузить из М15'],
    'LoadSchFromXl'     => ['XLS', 'Загрузить счёт из Excel'],
    'btnShowLogPrice'   => ['Ист', 'История цен'],
    'btnShowLogDtaPost' => ['Ист', 'История дат поставки'],
    'MailFormBtn'       => ['✉', 'Письма по счёту'],
];

$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/picker.js');   // окна выбора (frmfndFirm, frmfndZac и др.)
$this->registerJsFile('@web/js/access-dialog.js');
?>
<div class="access-form" id="frmMassInputSch" style="width:1247px;height:882px;background:<?= F::color($st['bg']) ?>">
<?= $this->render('@app/views/access/_form', [
    'layout' => require __DIR__ . '/layout/frmMassInputSch.php',
    'rec' => $rec, 'calc' => $calc, 'combos' => $d['combos'],
    'state' => $st, 'slots' => $slots, 'icons' => $icons,
]) ?>
</div>
<?= $this->render('_script', ['id' => $id, 'resUrl' => Url::to(['res'])]) ?>
<?php
$chgUrl  = \yii\helpers\Json::encode(Url::to(['vh-dlg/chg-hist']));
$postUrl = \yii\helpers\Json::encode(Url::to(['vh-dlg/sch-post']));
$logUrl  = \yii\helpers\Json::encode(Url::to(['vh-dlg/show-log']));
$moveUrl = \yii\helpers\Json::encode(Url::to(['vh-dlg/move-date', 'id' => $id]));
$this->registerJs(<<<JS
// btnShowLogPrice_Click: выбранная позиция (нет выбора — первая, как текущая запись) → «Изменение позиции счёта», Args «0,AllID,ShcID»
document.getElementById('btnShowLogPrice')?.addEventListener('click', () => {
    const tb = document.querySelector('#pos tbody'), tr = tb?.querySelector('tr.sel') || tb?.rows[0];
    if (!tr) return;
    accessDialog($chgUrl + '?' + new URLSearchParams({ rgm: 0, all: 0, allId: tr.dataset.id, shcId: $id }), 1040, 332);
});
// btnShowLogDtaPost_Click: frmShowLogSklad, OpenArgs «DtaPost», AllID выбранной позиции
document.getElementById('btnShowLogDtaPost')?.addEventListener('click', () => {
    const tb = document.querySelector('#pos tbody'), tr = tb?.querySelector('tr.sel') || tb?.rows[0];
    if (!tr) return;
    accessDialog($logUrl + '?' + new URLSearchParams({ allId: tr.dataset.id }), 333, 242);
});
// MoveDate_Click: frmFndIsp4Nakl 2 (KodNullEl = ID счёта); после переноса позиции перечитываются
document.getElementById('MoveDate')?.addEventListener('click', async () => {
    const r = await accessDialog($moveUrl, 664, 348);
    if (r.reload) location.reload();
});
// MailFormBtn_Click: frmSchPost по счёту
document.getElementById('MailFormBtn')?.addEventListener('click', () => accessDialog($postUrl + '?id=$id', 1078, 493));
JS);
?>
<?php if ($allowEd): ?>
<?= $this->render('_edit', ['id' => $id]) ?>
<?php endif ?>
