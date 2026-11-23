<?php
/**
 * Карточка счёта на элементы (frmMassInputSch) — современный вид.
 *
 * Обычные панели и сетки, подписи текстом: координаты Access больше не используются, они нужны были
 * только для переноса формы 1 в 1 (views/access/_form.php).
 *
 * Права и состояние экрана берутся из ScreenRules::schetElem — те же правила, что у вида «как в Access»
 * (OpenSch, Flag1C, RepaintSch): что можно менять (edit), что VBA прячет (hide), почему кнопка неактивна
 * (off), какие поля только для чтения (locked). Сервер всё равно проверяет каждое действие сам
 * (SchetSaveService::allowEd), поэтому экран только повторяет поведение Access, а не заменяет проверки.
 *
 * Имена id / data-field / data-act — прежние: их ищут _script.php и _edit.php, эти файлы не менялись.
 *
 * @var yii\web\View $this
 * @var int $id
 * @var array $d  данные из SchetService::getElem
 */
use app\components\AccessFormat as F;
use app\modules\zakupki\services\ScreenRules;
use yii\helpers\Html;
use yii\helpers\Url;

$rec = $d['rec'];
$this->title = 'Счёт ' . ($rec['НомерСчета'] ?? '');

// --- Состояние экрана и права
$st      = ScreenRules::schetElem($d);
$allowEd = $st['flags']['allowEd'];
$closed  = $st['flags']['closed'];
$all     = (bool)Yii::$app->request->get('all');            // ShowHiddenBut: показать скрытые столбцы позиций

$can    = static fn(string $n): bool => isset($st['edit'][$n]);           // поле/действие разрешено
$shown  = static fn(string $n): bool => !in_array($n, $st['hide'], true); // VBA не спрятал
$locked = static fn(string $n): bool => in_array($n, $st['locked'] ?? [], true);

/**
 * Поле «подпись + ввод». Без права правки (или с запретом из правил) — только чтение,
 * но id остаётся: на него ссылаются скрипты карточки.
 */
$field = function (string $name, string $label, string $field, string $kind = 'text', array $o = []) use ($rec, $can, $shown, $locked): string {
    if (!$shown($name)) return '';
    $v     = array_key_exists('value', $o) ? $o['value'] : ($rec[$field] ?? null);
    $text  = array_key_exists('text', $o) ? $o['text'] : F::value($v, $o['fmt'] ?? null, $o['dp'] ?? null);
    $id    = Html::encode($name);
    $lbl   = '<label class="vh-lbl" for="' . $id . '">' . Html::encode($label) . '</label>';
    $style = isset($o['style']) ? ' style="' . $o['style'] . '"' : '';
    $after = $o['after'] ?? '';
    if (!$can($name) || $locked($name)) {
        return $lbl . '<div class="vh-ctl"><div class="a-txt"><input type="text" id="' . $id . '" readonly value="'
            . Html::encode((string)$text) . '" title="' . Html::encode((string)$text) . '"' . $style . '></div>' . $after . '</div>';
    }
    $attr = 'id="' . $id . '" class="ed" data-field="' . Html::encode($field) . '" data-kind="' . $kind . '"';
    if ($kind === 'date') {
        $iso = is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : '';
        $in = '<input type="date" ' . $attr . ' value="' . $iso . '">';
    } elseif ($kind === 'path') {
        $in = '<input type="text" ' . $attr . ' readonly value="' . Html::encode((string)$text) . '" title="'
            . Html::encode($text !== '' ? "$text\nDelete — удалить путь" : '') . '">';
    } else {
        $in = '<input type="text" ' . $attr . ' value="' . Html::encode((string)$text) . '"'
            . ($kind === 'search' ? ' autocomplete="off"' : '') . '>';
    }
    return $lbl . '<div class="vh-ctl"><div class="a-txt edit">' . $in . '</div>' . $after . '</div>';
};

/** Поле со списком. Значения и ключи сравниваем как числа: SQL отдаёт 1, 1.0, 1.00. */
$select = function (string $name, string $label, string $field, array $map, array $o = []) use ($rec, $can, $shown, $locked): string {
    if (!$shown($name)) return '';
    $norm = static fn($x): string => is_numeric($x) ? (string)(0 + $x) : (string)$x;
    $cur  = $norm($rec[$field] ?? '');
    $id   = Html::encode($name);
    $lbl  = '<label class="vh-lbl" for="' . $id . '">' . Html::encode($label) . '</label>';
    if (!$can($name) || $locked($name)) {
        $text = (string)($map[$cur] ?? $cur);
        return $lbl . '<div class="vh-ctl"><div class="a-txt"><input type="text" id="' . $id . '" readonly value="'
            . Html::encode($text) . '" title="' . Html::encode($text) . '"></div></div>';
    }
    $opts = '<option value=""></option>';
    foreach ($map as $k => $t) {
        $k = $norm($k);
        $opts .= '<option value="' . Html::encode($k) . '"' . ($k === $cur ? ' selected' : '') . '>' . Html::encode($t) . '</option>';
    }
    return $lbl . '<div class="vh-ctl"><div class="a-txt a-select edit"><select id="' . $id . '" class="ed" data-field="'
        . Html::encode($field) . '" data-kind="select">' . $opts . '</select></div></div>';
};

/** Флажок. С правом правки — class="act": скрипт карточки отправляет его на сервер. */
$check = function (string $name, string $label, $on, array $o = []) use ($can, $shown): string {
    if (!$shown($name)) return '';
    $id   = Html::encode($name);
    $attr = $can($name) ? ' class="a-opt act" data-act="' . $id . '"' : ' class="a-opt" disabled';
    $tip  = isset($o['tip']) ? ' title="' . Html::encode($o['tip']) . '"' : '';
    return '<label class="vh-check"' . $tip . '><input type="checkbox"' . $attr . ' id="' . $id . '"'
        . ($on ? ' checked' : '') . '><span>' . Html::encode($label) . '</span></label>';
};

/** Кнопка. $o['act'] = false — у кнопки свой обработчик по id (＋, ✎, «Посмотреть», истории). */
$button = function (string $name, string $label, string $tip, array $o = []) use ($st, $can): string {
    $id    = Html::encode($name);
    $style = isset($o['style']) ? ' style="' . $o['style'] . '"' : '';
    $cls   = 'a-btn' . (!empty($o['cls']) ? ' ' . $o['cls'] : '');
    $act   = $o['act'] ?? true;
    $on    = $o['enabled'] ?? $can($name);
    if ($on) {
        $a = $act ? ' class="' . $cls . ' act" data-act="' . $id . '"' : ' class="' . $cls . '"';
        return '<button type="button"' . $a . ' id="' . $id . '" title="' . Html::encode($tip) . '"' . $style . '>'
            . Html::encode($label) . '</button>';
    }
    $t = $tip . (isset($st['off'][$name]) ? ': ' . $st['off'][$name] : '');
    return '<button type="button" class="' . $cls . '" id="' . $id . '" disabled title="' . Html::encode($t) . '"'
        . $style . '>' . Html::encode($label) . '</button>';
};

// --- Участники счёта: продавец, покупатель, ИНН для 1С, холдинг, контрагент.
// Поставщика меняют в отдельном окне (поиск + список фирм), поэтому рядом с «Продавцом» только кнопка.
$firmBtn = $allowEd
    ? $button('btnFirm', '…', 'Выбрать поставщика', ['act' => false, 'enabled' => true])
    : '';
$who = '<div class="vh-rows two">'
    . $field('Организация', 'Продавец', 'Организация', 'text', ['after' => $firmBtn])
    . $select('TP', 'Покупатель', 'TP', $d['combos']['TP'] ?? [])
    . $select('INN', 'ИНН для 1С', 'INN', $d['combos']['INN'] ?? [])
    . $field('Holding', 'Холдинг', 'Holding')
    . $field('KontrName', 'Контрагент', 'KontrName', 'text', ['value' => $d['calc']['KontrName']])
    . '</div>';

// --- Кто и когда менял счёт: уходит в шапку, справа от действий
$stamp = '<span class="vh-pair"><span class="vh-lbl">Дата:</span><span class="a-txt">'
    . '<input type="text" id="Дата" readonly value="' . Html::encode(F::value($rec['Дата'] ?? null, 'dd.mm.yyyy')) . '"></span></span>'
    . '<span class="vh-pair"><span class="vh-lbl">Время:</span><span class="a-txt">'
    . '<input type="text" id="Время" readonly value="' . Html::encode(F::value($rec['Время'] ?? null, 'Short Time')) . '"></span></span>'
    . '<span class="vh-pair"><span class="vh-lbl">Пользователь:</span><span class="a-txt">'
    . '<input type="text" id="UserCur" readonly value="' . Html::encode((string)($rec['UserCur'] ?? '')) . '"></span></span>';

// --- Флажки состояния: стоят в данных счёта под «Отдано в оплату», две колонки.
// PrcWithNDS/SchWithNDS/WOQuest в счёте на элементы только показываются:
// действий для них на сервере пока нет (в Access они меняли ставку НДС), поэтому они неактивны.
$numFz = $shown('NumFZ275')
    ? '<div class="a-txt vh-num"><input type="text" id="NumFZ275" readonly value="'
        . Html::encode(F::value($rec['NumFZ275'] ?? null)) . '" title="Номер заказа ФЗ-275"></div>'
    : '';
$flags = '<div class="vh-checks two">'
    . $check('TakeMe', 'Заберите меня', $d['calc']['TakeMe'], ['tip' => 'Забрать счёт: дата и время, когда его забирают'])
    . $check('NotUsed', 'Не обновлять в 1С', F::bool($rec['NotUsed'] ?? 0))
    . '<div class="vh-check-pair">' . $check('flgFZ275', 'ФЗ-275', F::bool($rec['flgFZ275'] ?? 0)) . $numFz . '</div>'
    . $check('PrcWithNDS', 'Сумма включает НДС', F::bool($rec['PrcWithNDS'] ?? 0), ['tip' => 'Пока не перенесено'])
    . $check('SchWithNDS', 'Учитывать НДС', F::bool($rec['SchWithNDS'] ?? 0), ['tip' => 'Пока не перенесено'])
    . $check('WOQuest', 'Без вопросов', F::bool($rec['WOQuest'] ?? 0), ['tip' => 'Пока не перенесено'])
    // «В 1С»: Flag1C — флажок рабочий, но только пока счёт не выгружен (Repl <= 1). Выгружен — бейдж в шапке
   
    . '</div>';

// --- Данные счёта: слева реквизиты, справа деньги
// Склад: значения из RowSource Access (0;"Сокол";1;"ОЭЗ";255;"Не указан")
$dstMap = [0 => 'Сокол', 1 => 'ОЭЗ', 255 => 'Не указан'];
$kurs   = $d['calc']['Kurs'];

$invLeft = '<div class="vh-rows">'
    . $field('НомерСчета', '№ счёта', 'НомерСчета')
    . $field('ДатаСчета', 'Счёт от', 'ДатаСчета', 'date', ['fmt' => 'Short Date'])
    . $select('Dst', 'Склад', 'Dst', $dstMap)
    . $field('Dta2Opl', 'Отдано в оплату', 'Dta2Opl', 'date', ['fmt' => 'Short Date'])
    . $field('InUEtxt', 'Валюта', 'InUEtxt')
    . '<div class="vh-span">' . $flags . '</div>'
    . '</div>';

$invRight = '<div class="vh-rows vh-money">'
    . $field('NDS', 'Ставка НДС', 'NDS', 'num', ['after' => $button('SetNDS2Str', 'Всем строкам', 'Установить указанную ставку НДС всем строкам счёта')])
    . $field('Sm', 'Сумма счёта', 'Sm', 'num', ['fmt' => 'General Number', 'dp' => 2])
    . $field('SmNDS', 'Сумма НДС', 'SmNDS', 'text', ['fmt' => 'General Number', 'dp' => 2])
    . $field('SumSchPerPos', 'Сумма по поз', 'SumSchPerPos', 'text', ['value' => $d['calc']['SumSchPerPos'], 'fmt' => 'General Number', 'dp' => 2])
    . $field('SumDost', 'Сумма дост.', 'SumDost', 'num', ['fmt' => 'General Number', 'dp' => 2])
    . $field('AdditSum', 'Доп. сумма', 'AdditSum', 'num', ['fmt' => 'General Number', 'dp' => 2])
    . $field('DopSum', 'Сумма проч.', 'DopSum', 'num', ['fmt' => 'General Number', 'dp' => 2])
    . $field('Kurs', 'Курс', 'Kurs', 'text', ['value' => $kurs['value'], 'style' => $kurs['post'] ? 'color:#0000ff' : ''])
    . '</div>'
    . '<div class="vh-hint">Синий цвет — курс поставщика</div>';

$invoice = '<div class="vh-side-split">'
    . '<div class="vh-side-col">' . $invLeft . '</div>'
    . '<div class="vh-side-col">' . $invRight . '</div>'
    . '</div>';

// --- Файлы счёта: оригинал и дополнительный счёт (Delete в поле удаляет путь)
$files = '<div class="vh-rows">'
    . $field('Pth', 'Оригинал счёта', 'Pth', 'path')
    . $field('AdditPth', 'Доп. счёт', 'AdditPth', 'path')
    . '</div>';

// --- Позиции счёта (подформа LstMassInpt): столбцы общие с накладной (lstSch)
['cols' => $posCols, 'key' => $posKey] = (function () use ($rec, $all) { return require __DIR__ . '/_pos-cols.php'; })();
$posGrid = $this->render('@app/views/access/_grid', ['tid' => 'pos', 'key' => $posKey, 'cols' => $posCols,
    'rows' => $d['pos'], 'idField' => 'AllID', 'dataFields' => ['SkladID']]);
$posGrid = '<div class="vh-scroll">' . $posGrid . '</div>';   // прокрутка внутри панели, карточка — в экран
$posTools = '<div class="vh-tools">'
    . $button('butDob', '＋', 'Добавить позицию в счёт', ['act' => false])
    . $button('Кнопка15', '✎', 'Изменить позицию', ['act' => false])
    . $button('DelRec', '−', 'Удалить позицию из счёта')
    . $button('btnShowLogPrice', 'Ист цены', 'История цены и количества выбранной позиции', ['act' => false, 'enabled' => true])
    . $button('btnShowLogDtaPost', 'Ист даты', 'История даты поставки выбранной позиции', ['act' => false, 'enabled' => true])
    . Html::a('⋯', Url::current(['all' => $all ? null : 1]), ['class' => 'a-btn' . ($all ? ' on' : ''),
        'id' => 'ShowHiddenBut', 'title' => 'Показать / спрятать скрытые столбцы позиций'])
    . '</div>';

// --- Резервы по выбранной позиции (subfrmMassInputSchRes): ColorFld = 1..4 красит одну из дат красным
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
$resGrid = $this->render('@app/views/access/_grid', ['tid' => 'res', 'key' => 'schet.elem.res', 'cols' => $resCols, 'rows' => $d['res']]);

// --- Накладные (lstNakl): двойной щелчок или «Посмотреть» открывает накладную
$naklTools = '<div class="vh-tools">'
    . $button('OpnFrm', 'Посмотреть', 'Просмотр накладных', ['act' => false, 'enabled' => true])
    . $button('AddNakl', 'Добавить', 'Добавить накладную')
    . $button('AllNakl', 'Все накладные', 'Показать все накладные')
    . '</div>';
$naklGrid = $this->render('_nakl', ['rows' => $d['nakl'], 'key' => 'schet.elem.nakl']);

// --- Договоры: активный договор подсвечен; высоту задаёт вкладка (пять строк таблицы)
$dogsGrid = $this->render('_dogs', ['rows' => $d['dogs']]);

// --- Выбор поставщика: поиск по началу названия (Fnd → FillListINN) и список фирм (xListINN).
// В Access список стоял прямо на форме; здесь он в отдельном окне, чтобы не занимать место.
// id Fnd и #firms сохранены: их ищет _edit.php (поиск, двойной щелчок — выбрать поставщика).
$firmModal = '';
if ($allowEd) {
    $firmModal = '<div class="vh-modal" id="firmModal">'
        . '<div class="vh-modal-box" role="dialog" aria-modal="true" aria-label="Выбор поставщика">'
        . '<div class="vh-modal-head"><span>Выбор поставщика</span>'
        . '<button type="button" class="a-btn" id="firmClose" title="Закрыть">✕</button></div>'
        . '<div class="vh-modal-body">'
        . '<div class="vh-search">' . $field('Fnd', 'Поиск', 'Fnd', 'search') . '</div>'
        . '<div class="vh-firms5">' . $this->render('_firms', ['rows' => $d['firms'], 'key' => 'schet.elem.firms']) . '</div>'
        . '<div class="vh-hint">Двойной щелчок по строке — выбрать поставщика</div>'
        . '</div></div></div>';
}

// --- Действия со счётом
$exit = $st['flags']['exitChecks']
    ? $button('butExit', '✕', 'Закрыть счёт (с проверками, как в Access)')
    : Html::a('✕', '#', ['class' => 'a-btn', 'id' => 'butExit', 'title' => 'Закрыть',
        'onclick' => 'closeCard();return false']);
$actions = '<div class="vh-tools">'
    . $button('butSaveAs', '💾 Сохранить', 'Сохранить счёт')
    . ($can('OpnSch') ? $button('OpnSch', '🔓 Открыть', 'Открыть закрытый счёт') : '')
    . ($shown('LoadM15') ? $button('LoadM15', 'М15', 'Загрузить из М15') : '')
    . $button('MoveDate', 'Перенос поставки', 'Перенос даты счёта', ['act' => false, 'enabled' => true])
    . $button('MailFormBtn', '✉', 'Переписка по счёту', ['act' => false, 'enabled' => true])
    . $exit
    . '</div>';

// --- Бейджи состояния
$badges = ($closed ? '<span class="badge b-red">Закрыт</span>' : '<span class="badge b-green">Открыт</span>')
    . ((int)($rec['Repl'] ?? 0) !== 0 ? '<span class="badge">В 1С</span>' : '')
    . (F::bool($rec['flgFZ275'] ?? 0) ? '<span class="badge">ФЗ-275</span>' : '')
    . ((int)($rec['InUE'] ?? 0) !== 0 ? '<span class="badge">Валюта: ' . Html::encode((string)($rec['InUEtxt'] ?? '')) . '</span>' : '')
    . (F::bool($rec['TakeMe'] ?? 0) ? '<span class="badge">Забрать</span>' : '');

$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/picker.js');
$this->registerJsFile('@web/js/access-dialog.js');
?>
<?= $this->render('_card-modern', [
    'title' => 'Счёт № ' . ($rec['НомерСчета'] ?? ''),
    'subtitle' => 'от ' . F::value($rec['ДатаСчета'] ?? null, 'Short Date')
        . ' · ' . Html::encode((string)($rec['Организация'] ?? ''))
        . ' · ShcID ' . (int)($rec['PredShcID'] ?? 0),
    'badges' => $badges,
    'actions' => $actions,
    'stamp' => $stamp,
    'who' => $who,
    'SchDta' => $invoice,
    'files' => $files,
    'panels' => [
        ['title' => 'Позиции счёта', 'html' => $posTools . $posGrid, 'cls' => 'vh-card-pos'],
    ],
    // Накладные, резервы и договоры — вкладками: в Access они стояли рядом и не помещались на экран
    'tabs' => [
        ['title' => 'Накладные', 'html' => $naklTools . $naklGrid],
        ['title' => 'Резервы по позиции', 'html' => $resGrid],
        ['title' => 'Договоры', 'html' => $dogsGrid],
    ],
]) ?>

<?= $firmModal ?>

<?= $this->render('_script', ['id' => $id, 'resUrl' => Url::to(['res'])]) ?>
<?php
$chgUrl  = \yii\helpers\Json::encode(Url::to(['vh-dlg/chg-hist']));
$postUrl = \yii\helpers\Json::encode(Url::to(['vh-dlg/sch-post']));
$logUrl  = \yii\helpers\Json::encode(Url::to(['vh-dlg/show-log']));
$moveUrl = \yii\helpers\Json::encode(Url::to(['vh-dlg/move-date', 'id' => $id]));
$this->registerJs(<<<JS
// btnShowLogPrice_Click: выбранная позиция (нет выбора — первая) → «Изменение позиции счёта», Args «0,AllID,ShcID»
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

// Выбор поставщика: окно с поиском (Fnd) и списком фирм. Выбор строки (двойной щелчок) обрабатывает _edit.php
const firmModal = document.getElementById('firmModal');
document.getElementById('btnFirm')?.addEventListener('click', () => {
    firmModal?.classList.add('on');
    document.getElementById('Fnd')?.focus();
});
document.getElementById('firmClose')?.addEventListener('click', () => firmModal?.classList.remove('on'));
firmModal?.addEventListener('click', e => { if (e.target === firmModal) firmModal.classList.remove('on'); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') firmModal?.classList.remove('on'); });
JS);
?>
<?php if ($allowEd): ?>
<?= $this->render('_edit', ['id' => $id]) ?>
<?php endif ?>
