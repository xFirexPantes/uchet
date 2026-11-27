<?php
/**
 * Карточка счёта на детали (frmScetaDetal) — современный вид.
 *
 * Обычные панели и сетки «подпись + поле», как в карточке счёта на элементы (elem-modern.php):
 * координаты Access не используются. Права и состояние экрана — из ScreenRules::schetDetal
 * (Form_Load, RepaintSchDetal): что можно менять (edit), что VBA прячет (hide), почему кнопка
 * неактивна (off), какие поля только для чтения.
 *
 * Имена id / data-field / data-act — как в Access: их ищут _script.php и _edit.php.
 *
 * @var yii\web\View $this
 * @var int $id
 * @var array $d  данные из SchetService::getDetal
 */
use app\components\AccessFormat as F;
use app\modules\zakupki\services\ScreenRules;
use yii\helpers\Html;
use yii\helpers\Url;

$rec = $d['rec'];
$this->title = 'Счёт на детали ' . ($rec['NumSc'] ?? '');

// --- Состояние экрана и права
$st      = ScreenRules::schetDetal($d);
$allowEd = $st['flags']['allowEd'];
$closed  = $st['flags']['closed'];

$can    = static fn(string $n): bool => isset($st['edit'][$n]);            // поле/действие разрешено
$shown  = static fn(string $n): bool => !in_array($n, $st['hide'], true);  // VBA не спрятал
$listed = static fn(string $n): bool => in_array($n, $st['show'], true);   // VBA показал (в макете скрыто)

/** Поле «подпись + ввод». Без права правки — только чтение, но id остаётся: на него ссылаются скрипты. */
$field = function (string $name, string $label, string $field, string $kind = 'text', array $o = []) use ($rec, $can, $shown): string {
    if (!$shown($name)) return '';
    $v     = array_key_exists('value', $o) ? $o['value'] : ($rec[$field] ?? null);
    $text  = array_key_exists('text', $o) ? $o['text'] : F::value($v, $o['fmt'] ?? null, $o['dp'] ?? null);
    $id    = Html::encode($name);
    $lbl   = '<label class="vh-lbl" for="' . $id . '">' . Html::encode($label) . '</label>';
    $after = $o['after'] ?? '';
    if (!$can($name)) {
        return $lbl . '<div class="vh-ctl"><div class="a-txt"><input type="text" id="' . $id . '" readonly value="'
            . Html::encode((string)$text) . '" title="' . Html::encode((string)$text) . '"></div>' . $after . '</div>';
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
$select = function (string $name, string $label, string $field, array $map, array $o = []) use ($rec, $can, $shown): string {
    if (!$shown($name)) return '';
    $norm = static fn($x): string => is_numeric($x) ? (string)(0 + $x) : (string)$x;
    $cur  = $norm($rec[$field] ?? '');
    $id   = Html::encode($name);
    $lbl  = '<label class="vh-lbl" for="' . $id . '">' . Html::encode($label) . '</label>';
    if (!$can($name)) {
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

/** Кнопка. $o['act'] = false — у кнопки свой обработчик по id (＋, ✎, «Посмотреть»). */
$button = function (string $name, string $label, string $tip, array $o = []) use ($st, $can): string {
    $id  = Html::encode($name);
    $cls = 'a-btn' . (!empty($o['cls']) ? ' ' . $o['cls'] : '');
    $act = $o['act'] ?? true;
    $on  = $o['enabled'] ?? $can($name);
    if ($on) {
        $a = $act ? ' class="' . $cls . ' act" data-act="' . $id . '"' : ' class="' . $cls . '"';
        return '<button type="button"' . $a . ' id="' . $id . '" title="' . Html::encode($tip) . '">'
            . Html::encode($label) . '</button>';
    }
    $t = $tip . (isset($st['off'][$name]) ? ': ' . $st['off'][$name] : '');
    return '<button type="button" class="' . $cls . '" id="' . $id . '" disabled title="' . Html::encode($t) . '">'
        . Html::encode($label) . '</button>';
};

// --- Участники счёта: продавец, ИНН, покупатель, холдинг.
// ИНН выбирают в окне поиска по фирмам (Fnd + xListINN), поэтому рядом только кнопка.
$firmBtn = $allowEd ? $button('btnFirm', '…', 'Выбрать контрагента по ИНН', ['act' => false, 'enabled' => true]) : '';
$who = '<div class="vh-rows two">'
    . $field('NmeOrg', 'Продавец', 'NmeOrg')
    . $select('IspFrm', 'Покупатель', 'IspFrm', $d['combos']['IspFrm'] ?? [])
    . $field('INN', 'ИНН', 'INN', 'text', ['after' => $firmBtn])
    . $field('Holding', 'Холдинг', 'Holding')
    . '</div>';

// --- Кто и когда менял счёт: в шапке справа
$stamp = '<span class="vh-pair"><span class="vh-lbl">Дата:</span><span class="a-txt">'
    . '<input type="text" id="DateSosd" readonly value="' . Html::encode(F::value($rec['DateSosd'] ?? null, 'Short Date')) . '"></span></span>'
    . '<span class="vh-pair"><span class="vh-lbl">Кто:</span><span class="a-txt">'
    . '<input type="text" id="Who" readonly value="' . Html::encode((string)($rec['Who'] ?? '')) . '"></span></span>'
    . '<span class="vh-pair"><span class="vh-lbl">ShcID:</span><span class="a-txt">'
    . '<input type="text" id="txtShcID" readonly value="' . Html::encode((string)($rec['IDSc'] ?? $id)) . '"></span></span>';

// --- Тип счёта (TpeScet): Стандартный 1, Услуги 2, Не сопоставлен 0
$tpe = (string)(0 + ($rec['TpeScet'] ?? 0));
$tpeEdit = $can('TpeScet');
$radio = function (string $id, string $label, string $value, bool $on) use ($tpeEdit): string {
    return '<label class="vh-radio"><input type="radio" name="TpeScet" id="' . $id . '" value="' . $value . '"'
        . ($tpeEdit ? ' class="ed" data-field="TpeScet" data-kind="radio"' : ' disabled')
        . ($on ? ' checked' : '') . '><span>' . Html::encode($label) . '</span></label>';
};
$tpeBlock = '<div class="vh-radios">'
    . $radio('Переключатель113', 'Стандартный', '1', $tpe === '1')
    . $radio('Переключатель115', 'Услуги', '2', $tpe === '2')
    . $radio('Переключатель117', 'Не сопоставлен', '0', $tpe === '0')
    . '</div>';

// --- Флажки состояния
$numFz = $shown('NumFZ275')
    ? '<div class="a-txt vh-num"><input type="text" id="NumFZ275" readonly value="'
        . Html::encode(F::value($rec['NumFZ275'] ?? null)) . '" title="Номер заказа ФЗ-275"></div>'
    : '';
$flags = '<div class="vh-checks two">'
    . $check('TakeMe', 'Заберите меня', $d['calc']['TakeMe'], ['tip' => 'Забрать счёт: дата и время, когда его забирают'])
    . $check('NotUsed', 'Не обновлять в 1С', $st['flags']['notUsed'])
    . $check('IsTo1C', 'В 1С', $st['flags']['to1C'])
    . '<div class="vh-check-pair">' . $check('flgFZ275', 'ФЗ-275', F::bool($rec['flgFZ275'] ?? 0)) . $numFz . '</div>'
    . $check('PrcWithNDS', 'Сумма включает НДС', F::bool($rec['PrcWithNDS'] ?? 0))
    . $check('SchWithNDS', 'Учитывать НДС', F::bool($rec['SchWithNDS'] ?? 0))
    . '</div>';

// --- Данные счёта: слева реквизиты, справа деньги
$dstMap = [0 => 'Сокол', 1 => 'ОЭЗ', 255 => 'Не указан'];

$invLeft = '<div class="vh-rows">'
    . $field('NumSc', '№ счёта', 'NumSc')
    . $field('DtaSc', 'Счёт от', 'DtaSc', 'date', ['fmt' => 'Short Date'])
    . $select('Dst', 'Склад', 'Dst', $dstMap)
    . $field('Dta2Opl', 'Отдано в оплату', 'Dta2Opl', 'date', ['fmt' => 'Short Date'])
    . '<div class="vh-span"><span class="vh-lbl">Тип счёта</span>' . $tpeBlock . '</div>'
    . '<div class="vh-span">' . $flags . '</div>'
    . '</div>';

$invRight = '<div class="vh-rows vh-money">'
    . $field('NDS', 'Ставка НДС', 'NDS', 'num')
    . $field('Sm', 'Сумма счёта', 'Sm', 'num', ['fmt' => 'General Number', 'dp' => 2])
    . $field('SmNDS', 'Сумма НДС', 'SmNDS', 'num', ['fmt' => 'General Number', 'dp' => 2])
    . $field('SumSchPerPos', 'Сумма по поз', 'SumSchPerPos', 'text', ['value' => $d['calc']['SumSchPerPos'], 'fmt' => 'General Number', 'dp' => 2])
    . $field('SumDost', 'Сумма дост.', 'SumDost', 'num', ['fmt' => 'General Number', 'dp' => 2])
    . '</div>';

$invoice = '<div class="vh-side-split">'
    . '<div class="vh-side-col">' . $invLeft . '</div>'
    . '<div class="vh-side-col">' . $invRight . '</div>'
    . '</div>';

// --- Файлы счёта: оригинал и дополнительный счёт (Delete в поле удаляет путь)
$files = '<div class="vh-rows">'
    . $field('Pth', 'Оригинал счёта', 'Pth', 'path')
    . $field('Additpth', 'Доп. счёт', 'AdditPth', 'path')
    . '</div>';

// --- Позиции (подформа LstPosSch = subfrmScetaDetal)
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
$posGrid = $this->render('@app/views/access/_grid', ['tid' => 'pos', 'key' => 'schet.detal.pos', 'cols' => $posCols,
    'rows' => $d['pos'], 'idField' => 'IDPos']);
$posGrid = '<div class="vh-scroll">' . $posGrid . '</div>';

// Дата поставки и ДП сразу всем строкам (MassSetDTPost / MassSetDTDP берут дату из полей рядом)
$massDates = '<div class="vh-tools">'
    . '<label class="vh-lbl" for="DTPost">Поставка:</label>'
    . '<input type="text" class="vh-inp vh-inp-xs ed" id="DTPost" data-field="DTPost" data-kind="localdate" value="">'
    . $button('MassSetDTPost', 'Установить', 'Установить дату «Поставка» всем строкам', ['enabled' => true])
    . '<label class="vh-lbl" for="DPDT">ДП:</label>'
    . '<input type="text" class="vh-inp vh-inp-xs ed" id="DPDT" data-field="DPDT" data-kind="localdate" value="">'
    . $button('MassSetDTDP', 'Установить', 'Установить дату «ДП» всем строкам', ['enabled' => true])
    . '</div>';

$posTools = '<div class="vh-tools">'
    . $button('butDob', '＋', 'Добавить позицию в счёт', ['act' => false])
    . $button('EdtPosSchDet', '✎', 'Изменить позицию', ['act' => false])
    . $button('DelRec', '−', 'Удалить позицию из счёта')
    . '</div>' . $massDates;

// --- Накладные (lstNakl): двойной щелчок или «Посмотреть» открывает накладную
$naklTools = '<div class="vh-tools">'
    . $button('OpnFrm', 'Посмотреть', 'Просмотр накладных', ['act' => false, 'enabled' => true])
    . $button('AddNakl', 'Добавить', 'Добавить накладную')
    . $button('AllNakl', 'Все накладные', 'Показать все накладные')
    . '</div>';
$naklGrid = $this->render('_nakl', ['rows' => $d['nakl'], 'key' => 'schet.detal.nakl']);

// --- Договоры: активный договор подсвечен
$dogsGrid = $this->render('_dogs', ['rows' => $d['dogs']]);
// Цвета — ConditionalFormat14 подформы lstDogs (subfrmFObnovlen1_01)
$dogsTools = '<div class="vh-tools">'
    . $this->render('@app/views/access/_color-legend', [
        'id'    => 'dogsColors',
        'title' => 'Цвета в договорах',
        'note'  => 'Подсвечен договор, действующий для этого счёта.',
        'btn'   => 'a-btn',
        'blocks' => [
            ['t' => 'Дата договора', 'rows' => [
                ['sw' => '#99ccff', 'label' => 'действующий договор ([IsActive]=True)'],
            ]],
        ],
    ])
    . '</div>';

// --- Выбор контрагента по ИНН: поиск (Fnd → FillListINN) и список фирм (xListINN) в отдельном окне.
// id Fnd и #firms сохранены: их ищет _edit.php (поиск, двойной щелчок — выбрать фирму).
$firmModal = '';
if ($allowEd) {
    $firmModal = '<div class="vh-modal" id="firmModal">'
        . '<div class="vh-modal-box" role="dialog" aria-modal="true" aria-label="Выбор контрагента">'
        . '<div class="vh-modal-head"><span>Выбор контрагента по ИНН</span>'
        . '<button type="button" class="a-btn" id="firmClose" title="Закрыть">✕</button></div>'
        . '<div class="vh-modal-body">'
        . '<div class="vh-search">' . $field('Fnd', 'Поиск', 'Fnd', 'search') . '</div>'
        . '<div class="vh-firms5">' . $this->render('_firms', ['rows' => $d['firms'], 'key' => 'schet.detal.firms']) . '</div>'
        . '<div class="vh-hint">Двойной щелчок по строке — выбрать контрагента</div>'
        . '</div></div></div>';
}

// --- Действия со счётом
$actions = '<div class="vh-tools">'
    . $button('butSaveAs', '💾 Сохранить', 'Сохранить счёт')
    . ($can('OpnSch') ? $button('OpnSch', '🔓 Открыть', 'Открыть закрытый счёт') : '')
    . ($listed('LoadM15') ? $button('LoadM15', 'М15', 'Загрузить из М15') : '')
    . $button('butExit', '✕', 'Закрыть счёт (с проверками, как в Access)')
    . '</div>';

// --- Бейджи состояния
$badges = ($closed ? '<span class="badge b-red">Закрыт</span>' : '<span class="badge b-green">Открыт</span>')
    . ($st['flags']['to1C'] ? '<span class="badge">В 1С</span>' : '')
    . (F::bool($rec['flgFZ275'] ?? 0) ? '<span class="badge">ФЗ-275</span>' : '')
    . (F::bool($rec['TakeMe'] ?? 0) ? '<span class="badge">Забрать</span>' : '');

$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/colresize.css');
$this->registerCssFile('@web/css/dlg-modern.css');   // обёртки окон (выбор, сообщения, диалоги)
$this->registerJsFile('@web/js/colresize.js');
$this->registerJsFile('@web/js/picker.js');
$this->registerJsFile('@web/js/access-dialog.js');
?>
<?= $this->render('_card-modern', [
    'title' => 'Счёт на детали № ' . ($rec['NumSc'] ?? ''),
    'subtitle' => 'от ' . F::value($rec['DtaSc'] ?? null, 'Short Date')
        . ' · ' . Html::encode((string)($rec['NmeOrg'] ?? ''))
        . ' · ShcID ' . (int)($rec['IDSc'] ?? $id),
    'badges' => $badges,
    'actions' => $actions,
    'stamp' => $stamp,
    'who' => $who,
    'SchDta' => $invoice,
    'files' => $files,
    'panels' => [
        ['title' => 'Позиции счёта', 'html' => $posTools . $posGrid, 'cls' => 'vh-card-pos'],
    ],
    'tabs' => [
        ['title' => 'Накладные', 'html' => $naklTools . $naklGrid],
        ['title' => 'Договоры', 'html' => $dogsTools . $dogsGrid],
    ],
]) ?>

<?= $firmModal ?>

<?= $this->render('_script', ['id' => $id, 'resUrl' => null]) ?>
<?php if ($allowEd): ?>
<?= $this->render('_edit', ['id' => $id]) ?>
<?php endif ?>
<?php
// Окно выбора контрагента и правка позиции (✎ EdtPosSchDet_Click: окно frmScetaDetalAdd по выбранной позиции)
$posUrl = \yii\helpers\Json::encode(Url::to(['pos-detal', 'id' => $id]));
$this->registerJs(<<<JS
(function () {
    const modal = document.getElementById('firmModal');
    document.getElementById('btnFirm')?.addEventListener('click', () => {
        modal?.classList.add('on');
        document.getElementById('Fnd')?.focus();
    });
    document.getElementById('firmClose')?.addEventListener('click', () => modal?.classList.remove('on'));
    modal?.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('on'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') modal?.classList.remove('on'); });

    // ✎: выбранная позиция (нет выбора — первая), после ✓ список перечитывается
    document.getElementById('EdtPosSchDet')?.addEventListener('click', () => {
        const tb = document.querySelector('#pos tbody'), tr = tb?.querySelector('tr.sel') || tb?.rows[0];
        if (!tr) { msgBox('Выберите элемент для редактирования'); return; }
        accessDialog($posUrl + '&pos=' + encodeURIComponent(tr.dataset.id), 660, 250)
            .then(r => { if (r.reload) location.reload(); });
    });
})();
JS, \yii\web\View::POS_END);
