<?php
/**
 * Статистика (frmSt) — современный вид в общем оформлении экранов «Учёта» (vh-*).
 *
 * Фильтры — как на главной форме Access:
 *   Вид (ПолеСоСписком24), Поиск (FindElem), Фирмы (f, мультивыбор через frmFndFirm 7),
 *   WhatFirm, SkladManagers, даты PickDate/PickDate2, Сырьё (TypeSel),
 *   «Заказ>0/Все» (fltZ1), ФЗ-275 (список xp_frmStLstFZ275), состояние проблемы (fltStatProblEl).
 *
 * Видимость фильтров по видам — как в fnRepaintFormStat (см. StatFilter::VIEW_RULES):
 *   FZ275 — только вид 1; Состояние — виды 1 и 3; Даты — вид 1 при fltZ1 = 1;
 *   Фирмы/WhatFirm/Менеджер/Сырьё — все виды, кроме 7; «Заказ» — виды 1, 2, 4, b.
 *
 * Колонки списка в Access задавала подформа frmMinimum, а их состав и ширину менял
 * fnRepaintFormStat при смене «Вида» (каждый режим — своя процедура xp_frmStNewRS…).
 * Поэтому таблица строится по фактическим полям, которые вернула процедура:
 * известные поля получают русские подписи и ширину, служебные скрываются.
 *
 * @var yii\web\View $this
 * @var app\modules\zakupki\models\StatFilter $filter
 * @var array $vids
 * @var array $firms
 * @var array $managers
 * @var array $statuses
 * @var array $fz275    список ФЗ-275 (xp_frmStLstFZ275): 0 — все, -2 — ПГ, -3 — не ПГ
 * @var array $rows
 * @var string|null $error
 * @var string|null $note
 * @var array $footer  граница цены, разница, курс (StatService::footer)
 * @var array $hints   подсказки по колонкам: поле => текст (StatService::hints)
 * @var array $flags   ExactDate/DatePost по коду строки (StatService::dateFlags)
 */

use app\modules\zakupki\services\StatContext;
use app\modules\zakupki\services\StatService;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'Статистика';
// Контейнер макета — во всю ширину окна (класс vh-wide ставит views/layouts/main.php
// ещё на сервере: иначе страница сначала рисуется узкой и «разворачивается» скриптом).
$this->params['wide'] = true;

// Колонки — по первой строке результата: состав у каждого вида свой.
// Подписи и видимость части колонок зависят от вида (fnRepaintFormStat) — см. StatColumns.
$cols = StatService::columns($rows, (string)$filter->vid);

$hasDateFilter = $filter->dateS || $filter->datePo;
// Видимость фильтров — правила fnRepaintFormStat (после applyViewRules в контроллере)
$showDates  = $filter->showDates();
$showStatus = $filter->showStatus();
$showZ1     = $filter->showZ1();
$showFz275  = $filter->showFz275();
$showFirms  = $filter->showFirms();
$showWhat   = $filter->showWhat();

// Ссылки собираем от текущих значений фильтра, чтобы смена одного параметра не теряла остальные.
// Пустые значения выбрасываем, иначе в адресе копятся «?fnd=&city=&post=…».
$attrs = array_filter($filter->getAttributes(), static fn($v) => $v !== '' && $v !== null);
$base = ['index'] + $attrs;
$noDates = ['index'] + array_diff_key($attrs, ['dateS' => 1, 'datePo' => 1]);
$noSort = ['index'] + array_merge($attrs, ['sort' => '', 'dir' => 'asc']);

// Выбранные фирмы для мультивыбора: «%» — все
$selFirms = $filter->f === '%' || $filter->f === ''
    ? []
    : array_filter(array_map('trim', explode(',', (string)$filter->f)), static fn($v) => $v !== '');

$hasAllFirm = in_array(0, array_map('intval', array_column($firms, 'id')), true);
$hasAllManager = in_array('%', array_column($managers, 'value'), true);
$hasAllStatus = in_array(255, array_column($statuses, 'id'), true);
?>
<div class="vh-stat">
    <header class="vh-head">
        <div class="vh-head-title">
            <h1>Статистика</h1>
            <div class="vh-sub">Строк: <b id="stCount"><?= count($rows) ?></b>
                <span class="vh-upd" id="stUpd" title="Список перечитывается сам каждые 9 минут, как в Access"></span>
            </div>
        </div>
        <div class="vh-head-actions">
            <button type="button" class="vh-btn" id="stRefresh" title="Перечитать список сейчас">⟳ Обновить</button>
            <a class="vh-btn" href="<?= Url::home() ?>" title="Закрыть (на главную)">✕ Закрыть</a>
        </div>
    </header>

    <div id="stMsg">
        <?php foreach ($filter->getFirstErrors() as $e): ?>
            <div class="vh-alert"><?= Html::encode($e) ?></div>
        <?php endforeach ?>
        <?php if ($error): ?>
            <div class="vh-alert"><?= Html::encode($error) ?></div>
        <?php endif ?>
        <?php if ($note): ?>
            <div class="vh-note"><?= Html::encode($note) ?></div>
        <?php endif ?>
    </div>

    <?php /* Нижняя часть страницы — стек подформ frmSt в порядке Access:
             SubfrmStat (список), SubMinimFilter, s (SubFrmStatSch), subFrmStAnalog1, Invoices.
             Высота ограничена экраном: список — 25% остатка, остальные панели — по 18.75%. */ ?>
    <div class="vh-stack">

    <form method="get" action="<?= Url::to(['index']) ?>" id="frmSt" class="vh-form vh-pane-main">
        <?= Html::hiddenInput('sort', $filter->sort) ?>
        <?= Html::hiddenInput('dir', $filter->dir) ?>
        <?php /* Вид на момент отправки: по нему StatFilter понимает, что вид сменили,
                 и применяет правила fnRepaintFormStat (fltZ1). При обычной отправке
                 vidPrev == vid, и выбор пользователя не перебивается. */ ?>
        <?= Html::hiddenInput('vidPrev', $filter->vid) ?>

        <section class="vh-filters">
            <div class="vh-fgroup">
                <div class="vh-fgroup-t">Вид</div>
                <div class="vh-frow">
                    <select class="vh-inp" name="vid" onchange="this.form.submit()">
                        <?php if (!$vids): ?>
                            <option value="<?= Html::encode($filter->vid) ?>"><?= Html::encode($filter->vid) ?></option>
                        <?php endif ?>
                        <?php foreach ($vids as $v): ?>
                            <option value="<?= Html::encode($v['num']) ?>"
                                <?= (string)$v['num'] === (string)$filter->vid ? 'selected' : '' ?>>
                                <?= Html::encode($v['name']) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <div class="vh-fgroup">
                <div class="vh-fgroup-t">Поиск</div>
                <div class="vh-frow">
                    <input class="vh-inp vh-inp-w" type="text" name="fnd" maxlength="100"
                        value="<?= Html::encode($filter->fnd) ?>" placeholder="часть наименования"
                        title="Ищется по части текста, как FindElem в Access">
                    <button type="submit" class="vh-btn" title="Найти">🔍</button>
                </div>
            </div>

            <?php /* f: мультивыбор фирм, в Access — frmFndFirm (OpenArgs 7) → xp_frmStMultiSelectResult.
                     Скрыт в виде 7: там fnRepaintFormStat прячет FndZac вместе с WhatFirm/TypeSel. */ ?>
            <?php if ($showFirms): ?>
                <div class="vh-fgroup vh-fgroup-firms">
                    <div class="vh-fgroup-t">Фирмы</div>
                    <details class="vh-firm"<?= $selFirms ? ' open' : '' ?>>
                        <summary title="Выбрать фирмы (в Access — кнопка «Выбрать фирму»)">
                            <?= Html::encode($filter->firmTitle($firms)) ?>
                        </summary>
                        <div class="vh-firm-list">
                            <label class="vh-check">
                                <input type="checkbox" name="f[]" value="%" <?= $filter->f === '%' ? 'checked' : '' ?>>
                                <span>Все</span>
                            </label>
                            <?php foreach ($firms as $fr): ?>
                                <label class="vh-check">
                                    <input type="checkbox" name="f[]" value="<?= Html::encode($fr['id']) ?>"
                                        <?= in_array($fr['id'], $selFirms, true) ? 'checked' : '' ?>>
                                    <span><?= Html::encode($fr['name']) ?></span>
                                </label>
                            <?php endforeach ?>
                        </div>
                    </details>
                </div>

                <div class="vh-fgroup">
                    <div class="vh-fgroup-t">Фирма (WhatFirm)</div>
                    <div class="vh-frow">
                        <select class="vh-inp" name="firm" onchange="this.form.submit()"
                            title="WhatFirm: в Access скрыт в виде 7">
                            <?php if (!$hasAllFirm): ?>
                                <option value="0">Все</option>
                            <?php endif ?>
                            <?php foreach ($firms as $fr): ?>
                                <option value="<?= Html::encode($fr['id']) ?>"
                                    <?= (string)$fr['id'] === (string)$filter->firm ? 'selected' : '' ?>>
                                    <?= Html::encode($fr['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>

                <div class="vh-fgroup">
                    <div class="vh-fgroup-t">Менеджер</div>
                    <div class="vh-frow">
                        <select class="vh-inp" name="manager" onchange="this.form.submit()"
                            title="SkladManagers: ответственный и раскраска долгов">
                            <?php if (!$hasAllManager): ?>
                                <option value="%">Все</option>
                            <?php endif ?>
                            <?php foreach ($managers as $m): ?>
                                <option value="<?= Html::encode($m['value']) ?>"
                                    <?= (string)$m['value'] === (string)$filter->manager ? 'selected' : '' ?>>
                                    <?= Html::encode($m['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
            <?php endif ?>

            <?php /* WhatFirm / менеджер скрыты в виде 7, но значения Access всё равно передаёт в процедуру:
                     держим их скрытыми полями, чтобы смена вида не теряла выбор. */ ?>
            <?php if (!$showWhat): ?>
                <input type="hidden" name="firm" value="<?= (int)$filter->firm ?>">
                <input type="hidden" name="manager" value="<?= Html::encode($filter->manager) ?>">
                <input type="hidden" name="typeSel" value="<?= (int)$filter->typeSel ?>">
            <?php endif ?>

            <?php /* Фирмы скрыты в виде 7, но второй параметр процедуры (Nz(Me!f, "%")) Access передаёт
                     всегда — сохраняем выбор скрытым полем, чтобы он не потерялся при смене вида. */ ?>
            <?php if (!$showFirms): ?>
                <input type="hidden" name="f" value="<?= Html::encode($filter->f) ?>">
            <?php endif ?>

            <?php /* PickDate/PickDate2: вид 1 при fltZ1 = 1 (Form_Load frmSt).
                     В Access оба поля Locked = True и заполняются только кнопкой «Даты»
                     (GetInt_Click → frmGetInterval → KodNullEl(0..1)), поэтому здесь они
                     readonly, а даты выбираются в том же окне интервала, что и на других экранах. */ ?>
            <?php if ($showDates): ?>
                <div class="vh-fgroup">
                    <div class="vh-fgroup-t">Дата документа</div>
                    <div class="vh-frow">
                        <span class="vh-lbl">с</span>
                        <input class="vh-inp" type="date" name="dateS" value="<?= Html::encode($filter->dateS) ?>"
                            readonly title="В Access поле заблокировано: даты выбираются кнопкой «Даты»">
                        <span class="vh-lbl">по</span>
                        <input class="vh-inp" type="date" name="datePo" value="<?= Html::encode($filter->datePo) ?>"
                            readonly title="В Access поле заблокировано: даты выбираются кнопкой «Даты»">
                        <button type="button" class="vh-btn" id="stDates"
                            title="Выбрать интервал дат (в Access — кнопка «Даты», форма frmGetInterval)">Даты</button>
                        <?php if ($hasDateFilter): ?>
                            <a class="vh-btn vh-btn-xs" href="<?= Url::to($noDates) ?>" title="Сбросить даты">✕</a>
                        <?php endif ?>
                    </div>
                </div>
            <?php endif ?>

            <?php /* TypeSel: в Access fnRepaintFormStat прячет фильтр «Сырьё» только для вида 7 */ ?>
            <?php if ($showWhat): ?>
                <div class="vh-fgroup">
                    <div class="vh-fgroup-t">Сырьё</div>
                    <label class="vh-radio"><input type="radio" name="typeSel" value="1"
                        <?= (int)$filter->typeSel === 1 ? 'checked' : '' ?> onchange="this.form.submit()"><span>НЕ давальческое</span></label>
                    <label class="vh-radio"><input type="radio" name="typeSel" value="2"
                        <?= (int)$filter->typeSel === 2 ? 'checked' : '' ?> onchange="this.form.submit()"><span>Давальческое</span></label>
                    <label class="vh-radio"><input type="radio" name="typeSel" value="3"
                        <?= (int)$filter->typeSel === 3 ? 'checked' : '' ?> onchange="this.form.submit()"><span>Не важно</span></label>
                </div>
            <?php endif ?>

            <?php /* fltZ1: виден в видах 1, 2, 4, b; в остальных Access скрывает и задаёт сам.
                     В виде b подписи другие: «Все» / «Нет счетов более недели». */ ?>
            <?php if ($showZ1): ?>
                <div class="vh-fgroup">
                    <?php if ($filter->z1Title() !== ''): ?>
                        <div class="vh-fgroup-t"><?= Html::encode($filter->z1Title()) ?></div>
                    <?php endif ?>
                    <label class="vh-radio"><input type="radio" name="fltZ1" value="1"
                        <?= (int)$filter->fltZ1 === 1 ? 'checked' : '' ?> onchange="this.form.submit()"><span><?= Html::encode($filter->z1Labels()[0]) ?></span></label>
                    <label class="vh-radio"><input type="radio" name="fltZ1" value="0"
                        <?= (int)$filter->fltZ1 === 0 ? 'checked' : '' ?> onchange="this.form.submit()"><span><?= Html::encode($filter->z1Labels()[1]) ?></span></label>
                </div>
            <?php else: ?>
                <input type="hidden" name="fltZ1" value="<?= (int)$filter->fltZ1 ?>">
            <?php endif ?>

            <?php /* fltStatProblEl: виден в видах 1 и 3 (fnRepaintFormStat + fltZ1_AfterUpdate).
                     В остальных видах Access его скрывает, но значение НЕ очищает —
                     оно всё равно уходит в @WorkProblEl, поэтому держим скрытое поле. */ ?>
            <?php if ($showStatus): ?>
                <div class="vh-fgroup">
                    <div class="vh-fgroup-t">Состояние</div>
                    <div class="vh-frow">
                        <select class="vh-inp" name="fltStatProblEl" onchange="this.form.submit()"
                            title="Состояние проблемы: в Access видно в видах 1 и 3">
                            <?php if (!$hasAllStatus): ?>
                                <option value="255">Все</option>
                            <?php endif ?>
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= (int)$s['id'] ?>"
                                    <?= (int)$filter->fltStatProblEl === (int)$s['id'] ? 'selected' : '' ?>>
                                    <?= Html::encode($s['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="fltStatProblEl" value="<?= (int)$filter->fltStatProblEl ?>">
            <?php endif ?>

            <?php /* FZ275: список xp_frmStLstFZ275 (0 — все, -2 — ПГ, -3 — не ПГ).
                     Виден только в виде 1, в остальных Access ставит 0. */ ?>
            <?php if ($showFz275): ?>
                <div class="vh-fgroup">
                    <div class="vh-fgroup-t">ФЗ-275</div>
                    <div class="vh-frow">
                        <select class="vh-inp" name="fz275" onchange="this.form.submit()"
                            title="ФЗ-275: в Access виден только в виде 1">
                            <?php if (!$fz275): ?>
                                <option value="0">(Все записи)</option>
                            <?php endif ?>
                            <?php foreach ($fz275 as $z): ?>
                                <option value="<?= (int)$z['id'] ?>"
                                    <?= (int)$filter->fz275 === (int)$z['id'] ? 'selected' : '' ?>>
                                    <?= Html::encode($z['name']) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </div>
                </div>
            <?php endif ?>

            <div class="vh-fgroup">
                <div class="vh-fgroup-t">Прочее</div>
                <button type="submit" class="vh-btn" title="Применить фильтры">Применить</button>
            </div>
        </section>

        <section class="vh-panel">
            <div class="vh-panel-head">
                <span>Список элементов</span>
                <span class="vh-tools">
                    <?php /* Легенда цветов — как кнопка «? Цвета» в списке счетов (vh-schet) */ ?>
                    <button type="button" class="vh-btn vh-btn-xs" id="stHints"
                            title="Что означают цвета в списке элементов">? Цвета</button>
                    <?php if ($filter->sort !== ''): ?>
                        Сортировка: <?= Html::encode(StatService::label($filter->sort)) ?>
                        <?= $filter->dir === 'desc' ? '▼' : '▲' ?>
                        <a class="vh-btn vh-btn-xs" href="<?= Url::to($noSort) ?>"
                            title="Без сортировки (как в Access, когда все переключатели выключены)">✕</a>
                    <?php endif ?>
                </span>
            </div>

            <div class="vh-list-scroll">
                <table class="vh-list" id="stList">
                    <colgroup>
                        <?php foreach ($cols as [$f, , $w]): ?>
                            <col<?= $w ? ' style="width:' . (int)$w . 'px"' : '' ?>>
                        <?php endforeach ?>
                    </colgroup>
                    <thead>
                    <tr>
                        <?php foreach ($cols as [$f, $title, , $right]):
                            $next = ($filter->sort === $f && $filter->dir === 'asc') ? 'desc' : 'asc';
                            $cls = $filter->sort === $f ? ($filter->dir === 'desc' ? 'desc' : 'asc') : ''; ?>
                            <th class="<?= $cls ?>" title="Сортировать">
                                <a href="<?= Url::to(array_merge($base, ['sort' => $f, 'dir' => $next])) ?>"><?= Html::encode($title) ?></a>
                            </th>
                        <?php endforeach ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?= $this->render('_rows', [
                        'rows'  => $rows,
                        'cols'  => $cols,
                        'bnd'   => $footer['bnd'],
                        'vid'   => (string)$filter->vid,
                        'paint' => StatContext::needPaintDolg(),
                        'flags' => $flags,
                        'empty' => $error ? 'Список не загружен' : 'Нет записей, удовлетворяющих условию',
                    ]) ?>
                    </tbody>
                </table>
            </div>

            <?php /*
              Футер подформы frmMinimum: PriceBound, KursUSD, Razn, WarnTS.
              В Access граница правилась по двойному щелчку (InputBox), здесь — обычное поле:
              Enter или «Применить» перечитывает список с новыми Bnd/Razn/Kurs (Razn_AfterUpdate).
            */ ?>
            <div class="vh-foot">
                <?php if (!$footer['isB']): ?>
                    <label class="vh-foot-f">
                        <span><?= $footer['dl'] ? 'Граница, &#36;' : 'Граница, ₽' ?></span>
                        <input class="vh-inp vh-inp-n" type="number" step="0.01" name="bnd"
                            value="<?= Html::encode(number_format($footer['bnd'], 2, '.', '')) ?>"
                            title="Цена от этой границы выделяется в списке; в Access задавалась двойным щелчком">
                    </label>
                    <input type="hidden" name="dl" value="<?= (int)$footer['dl'] ?>">

                    <label class="vh-foot-f">
                        <span>Курс</span>
                        <input class="vh-inp vh-inp-n" type="text" name="kurs"
                            value="<?= Html::encode(number_format($footer['kurs'], 4, '.', '')) ?>" readonly
                            title="Курс доллара на экране учёта (Forms!frmUchet!KursUSD)">
                    </label>
                <?php endif ?>

                <?php if ($footer['is7']): ?>
                    <label class="vh-foot-f">
                        <span>Разница</span>
                        <input class="vh-inp vh-inp-n" type="number" step="0.01" name="razn"
                            value="<?= Html::encode(number_format($footer['razn'], 2, '.', '')) ?>"
                            title="Разница цены для вида 7">
                    </label>
                <?php else: ?>
                    <input type="hidden" name="razn" value="<?= Html::encode($filter->razn) ?>">
                <?php endif ?>

                <?php if ($footer['warn']): ?>
                    <span class="vh-foot-warn" title="Вид 1 учитывает тех.запас, остальные — нет">Выборка без учёта тех.запаса</span>
                <?php endif ?>

                <button type="submit" class="vh-btn" title="Перечитать список с новыми значениями">Применить</button>
            </div>
            <?php /* Descr в frmSt: подсказка по колонке, на которой стоит курсор */ ?>
            <div class="vh-stat-hint" id="stHint" role="status" aria-live="polite"></div>
        </section>
    </form>

    <?php /* SubMinimFilter — резервы текущего элемента.
             Источник: exec dbo.xp_SubMinimumFilter @txtID, @Rgm (Rgm — вид списка).
             Колонки (ColumnOrder после Form_Load): Пробл, В, Дата, Расч.ст-ть, Жел.дата пост.р-ва,
             План.старт, Таргет, Максим.цена, Осталось, тема, примечание, заказчик, исп-ль, ИНН,
             область применения, токсичность, ответственный, отв.техподдержки, изделий, кол-во,
             всего, срок согласования, ФЗ275, заказ. При виде b закупочные колонки скрыты. */ ?>
    <section class="vh-panel" id="stReserves">
        <div class="vh-panel-head">
            <span>Резервы <span class="vh-an-code" id="stResCode"></span></span>
            <span class="vh-tools">
                <span id="stResInfo">выберите строку в списке</span>
                <?php /* Легенда цветов подформы резервов — ConditionalFormat14 по DtaPost */ ?>
                <button type="button" class="vh-btn vh-btn-xs" id="stResHints"
                        title="Что означают цвета в списке резервов">? Цвета</button>
                <?php /* TrgRecalc: пересчёт таргета по резерву; при виде b скрыта (fnRepaintFormStat) */ ?>
                <?php if ((string)$filter->vid !== 'b'): ?>
                    <button type="button" class="vh-btn vh-btn-xs" id="stTrgRecalc"
                            title="Обновить данные таргета и целевого предложения по всем элементам, входящим в резерв">↻ Таргет</button>
                <?php endif ?>
            </span>
        </div>
        <div class="vh-list-scroll" id="stResBox"></div>
    </section>

    <?php /* s — подформа SubFrmStatSch: счета/накладные по элементу
             (в Access колонки Недополучено = NedopolByNakl, Количество = Кол). */ ?>
    <section class="vh-panel" id="stSch">
        <div class="vh-panel-head"><span>Счета элемента (SubFrmStatSch)</span></div>
        <div class="vh-list-scroll">
            <div class="vh-stub">Тут будет список счетов по текущему элементу.</div>
        </div>
    </section>

    <section class="vh-panel vh-an">
        <div class="vh-panel-head">
            <span>Аналоги <span class="vh-an-code" id="stAnCode"></span></span>
            <span class="vh-tools" id="stAnInfo">выберите строку в списке</span>
        </div>
        <div class="vh-list-scroll vh-an-scroll" id="stAnBox"></div>
    </section>

    <?php /* Invoices — подформа SubFrmInvoices: предложения поставщиков по элементу.
             Источник: exec dbo.xp_GetInvoices1 @SkladID, @Numtbl, @IDRes, @Tpe, @DtaTxt
             (@Numtbl/@IDRes/@Tpe = 0 — все предложения элемента). */ ?>
    <section class="vh-panel" id="stInvoices">
        <div class="vh-panel-head">
            <span>Счета поставщиков <span class="vh-an-code" id="stInvCode"></span></span>
            <span class="vh-tools">
                <span id="stInvInfo">выберите строку в списке</span>
                <button type="button" class="vh-btn vh-btn-xs" id="stInvHints"
                        title="Что означают цвета в списке предложений">? Цвета</button>
            </span>
        </div>
        <div class="vh-list-scroll" id="stInvBox"></div>
    </section>

    </div><?php /* .vh-stack */ ?>

    <?php /* Легенда цветов списка — по кнопке «? Цвета».
             Цвета и условия — fnRepaintFormStat (frmSt) и ConditionalFormat14 frmMinimum;
             расшифровки «На Сокол» / «На Торэкс» — по подсказкам Descr главной формы. */ ?>
    <div class="vh-modal" id="stHintsBox">
        <div class="vh-modal-box" role="dialog" aria-modal="true" aria-labelledby="stHintsT">
            <div class="vh-modal-head">
                <span id="stHintsT">Цвета в списке элементов</span>
                <button type="button" class="vh-modal-x" id="stHintsX" title="Закрыть (Esc)">✕</button>
            </div>
            <div class="vh-modal-body">
                <p class="vh-hint">Цвет фона или текста в столбце подсказывает состояние элемента.</p>
                <div class="vh-hints">
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Наименование</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plain"></span><span class="vh-sw-t vh-b">жёсткий срок поставки (ExactDate)</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plain"></span><span class="vh-sw-t sw-green">срок поставки уже наступил (DatePost)</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Номинал</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plum"></span><span class="vh-sw-t">состояние проблемы 5</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plain"></span><span class="vh-sw-t sw-magenta-t">не рекомендуется к поставке ([NotRec])</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Примечание</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-sky"></span><span class="vh-sw-t sw-sky">На Торэкс</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Код</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-blue"></span><span class="vh-sw-t sw-blue">элемент можно забирать</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Заказать</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-red"></span><span class="vh-sw-t sw-red">число &gt; 0 — надо заказать</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Дешевле</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-blue"></span><span class="vh-sw-t sw-blue">цена дошла до границы</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Покупать в</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-sky"></span><span class="vh-sw-t sw-sky">задание на перенос для склада</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-peach"></span><span class="vh-sw-t sw-peach">для участка входного контроля</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Кол-во</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-yellow"></span><span class="vh-sw-t sw-yellow">есть проблемные элементы</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Минимум</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-sky"></span><span class="vh-sw-t sw-sky">совпадение складов</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Аналог</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-azure"></span><span class="vh-sw-t sw-azure">нет аналога</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Корпус</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-violet"></span><span class="vh-sw-t sw-violet">в резерв не заказан более недели</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Недополучено (вид 1)</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-green"></span><span class="vh-sw-t sw-green">доставка на Сокол</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plain"></span><span class="vh-sw-t vh-b">жирный — есть недополучение</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Изготовитель (вид 1)</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-green"></span><span class="vh-sw-t sw-green">в работе</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-yellow"></span><span class="vh-sw-t sw-yellow">закупка тех.запаса</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-blue"></span><span class="vh-sw-t sw-blue">контроль сроков</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Строка</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-mint"></span><span class="vh-sw-t sw-green">счёт запрошен</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Флажки</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plain"></span><span class="vh-sw-t">☑ «Счёт запр.» — запросить счёт</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plain"></span><span class="vh-sw-t">☑ «Не рек» — не рекомендуется к поставке (клик переключает)</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plain"></span><span class="vh-sw-t">☑ «ВП5», «Без аналог.» — отметки, только просмотр</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php /* Легенда цветов подформы резервов SubMinimFilter — ConditionalFormat14 по DtaPost */ ?>
    <div class="vh-modal" id="stResHintsBox">
        <div class="vh-modal-box" role="dialog" aria-modal="true" aria-labelledby="stResHintsT">
            <div class="vh-modal-head">
                <span id="stResHintsT">Цвета в списке резервов</span>
                <button type="button" class="vh-modal-x" id="stResHintsX" title="Закрыть (Esc)">✕</button>
            </div>
            <div class="vh-modal-body">
                <p class="vh-hint">Цвет фона в столбцах «Название темы» и «Пробл» подсказывает срок поступления.</p>
                <div class="vh-hints">
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Название темы</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-magenta"></span><span class="vh-sw-t">дата поступления ещё не наступила</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-olive"></span><span class="vh-sw-t">поступление сегодня</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Пробл / В</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-plain"></span><span class="vh-sw-t sw-red">красный «!» — из-за этого резерва элемент попал в долг</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php /* Легенда цветов подформы SubFrmInvoices — ConditionalFormat14 по IsCurTbl */ ?>
    <div class="vh-modal" id="stInvHintsBox">
        <div class="vh-modal-box" role="dialog" aria-modal="true" aria-labelledby="stInvHintsT">
            <div class="vh-modal-head">
                <span id="stInvHintsT">Цвета в предложениях поставщиков</span>
                <button type="button" class="vh-modal-x" id="stInvHintsX" title="Закрыть (Esc)">✕</button>
            </div>
            <div class="vh-modal-body">
                <p class="vh-hint">Список — предложения поставщиков по текущему элементу.</p>
                <div class="vh-hints">
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Св.табл</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-green"></span><span class="vh-sw-t sw-green">строка из текущей сводной таблицы</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Галки</div>
                        <div class="vh-hint-row"><span class="vh-sw-t">«В расчёт» — предложение участвует в расчёте</span></div>
                        <div class="vh-hint-row"><span class="vh-sw-t">«Активный» — согласовано к закупке</span></div>
                        <div class="vh-hint-row"><span class="vh-sw-t">«Проект» — цена проектная (ProjectPrice &gt; 0)</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php /* Диалог текста: frmShowPrim (ветки 58/81/83) — примечание элемента, комментарий производства, просмотр */ ?>
    <div class="vh-sdlg" id="stDlg" hidden>
        <div class="vh-sdlg-box" role="dialog" aria-modal="true" aria-labelledby="stDlgT">
            <div class="vh-sdlg-head">
                <span id="stDlgT"></span>
                <button type="button" class="vh-btn vh-btn-xs" id="stDlgX" title="Закрыть">✕</button>
            </div>
            <div class="vh-sdlg-body">
                <textarea id="stDlgText" spellcheck="false"></textarea>
                <select id="stDlgSel" size="12" hidden></select>
            </div>
            <div class="vh-sdlg-foot">
                <span class="vh-sdlg-msg" id="stDlgMsg"></span>
                <button type="button" class="vh-btn" id="stDlgOk">Сохранить</button>
                <button type="button" class="vh-btn" id="stDlgNo">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<?php
// Окно интервала дат — форма Access frmGetInterval (общий interval.js проекта).
// interval.js рисует окно поверх страницы и использует dateBox/msgBox из msgbox.js.
$this->registerJsFile('@web/js/msgbox.js');
$this->registerJsFile('@web/js/interval.js');

$this->registerCss(<<<'CSS'
.vh-stat { --vh-blue:#40B3D8; --vh-blue-d:#2f8baa; --vh-blue-l:#eaf7fc; --vh-blue-b:#c5e7f2; --vh-ink:#1b5c72;
    background:#f4fafc; padding:.4rem .7rem .6rem; box-sizing:border-box;
    font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d;
    /* Вся страница — одна колонка высотой в экран: шапка и фильтры занимают своё,
       остаток делят панели подформ (см. .vh-stack). */
    height:calc(100vh - 92px); display:flex; flex-direction:column; overflow:auto; }
#main > .container.vh-wide { max-width:100%; width:100%; padding-left:.4rem; padding-right:.4rem; }

/* Шапка: название и число строк слева, кнопки справа */
.vh-head { display:flex; align-items:center; flex-wrap:wrap; gap:.4rem 1rem; padding:.35rem .7rem; flex:0 0 auto;
    margin:-.4rem -.7rem .4rem; background:#fff; border-bottom:3px solid var(--vh-blue); }
.vh-head-title h1 { margin:0; color:var(--vh-ink); font-size:1rem; font-weight:600; }
.vh-sub { font-size:.72rem; color:#5b8598; margin-top:.1rem; }
.vh-upd { margin-left:.4rem; color:#7d9aa7; }
.vh-head-actions { display:flex; align-items:center; gap:.3rem; margin-left:auto; }

.vh-alert { background:#fdecec; color:#a4232b; border:1px solid #f5c2c7; border-radius:.4rem;
    padding:.25rem .6rem; margin-bottom:.4rem; }
.vh-note { background:#fff8e6; color:#7a5a00; border:1px solid #f0dfa8; border-radius:.4rem;
    padding:.25rem .6rem; margin-bottom:.4rem; }

/* Фильтры: группы «подпись + поле», переносятся по ширине */
.vh-form { display:block; }
/* Форма — контейнер первой панели стека: фильтры сверху (своя высота), список забирает остаток */
.vh-pane-main { display:flex; flex-direction:column; min-height:0; }
.vh-filters { display:flex; flex-wrap:wrap; gap:.4rem; align-items:stretch; margin-bottom:.4rem; flex:0 0 auto; }
.vh-fgroup { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.5rem; padding:.3rem .55rem .4rem;
    box-shadow:0 1px 3px rgba(64,179,216,.10); }
.vh-fgroup-t { color:var(--vh-ink); font-weight:600; font-size:.68rem; text-transform:uppercase;
    letter-spacing:.03em; margin-bottom:.25rem; }
.vh-frow { display:flex; align-items:center; flex-wrap:wrap; gap:.3rem; }
.vh-lbl { font-size:.72rem; color:#5b8598; white-space:nowrap; }
.vh-check, .vh-radio { display:inline-flex; align-items:center; gap:.3rem; font-size:.78rem;
    cursor:pointer; white-space:nowrap; margin:0 .8rem 0 0; }
.vh-check input, .vh-radio input { accent-color:var(--vh-blue); margin:0; }
.vh-check input:disabled + span { color:#9db9c4; }

/* Мультивыбор фирм: свёрнутый список с чекбоксами */
.vh-firm { position:relative; }
.vh-firm > summary { cursor:pointer; color:var(--vh-ink); font-size:.78rem; max-width:220px;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.vh-firm > summary::marker { color:var(--vh-blue); }
.vh-firm-list { position:absolute; z-index:20; margin-top:.25rem; min-width:220px; max-width:320px; max-height:260px;
    overflow:auto; background:#fff; border:1px solid var(--vh-blue-b); border-radius:.4rem;
    box-shadow:0 8px 24px rgba(27,92,114,.18); padding:.3rem .45rem; }
.vh-firm-list .vh-check { display:flex; margin:0; padding:.08rem 0; }

/* Кнопки и поля — в корпоративных цветах */
.vh-btn { display:inline-flex; align-items:center; justify-content:center; gap:.25rem; min-height:24px; padding:0 .55rem;
    background:#fff; border:1px solid var(--vh-blue-b); border-radius:.3rem; color:var(--vh-ink);
    font:600 .72rem/1 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; cursor:pointer;
    white-space:nowrap; text-decoration:none; }
.vh-btn:hover:not(:disabled) { background:var(--vh-blue); border-color:var(--vh-blue); color:#fff; }
.vh-btn:disabled { background:#f7fbfd; border-color:#e3f0f5; color:#9db9c4; cursor:default; }
.vh-btn-xs { min-height:20px; padding:0 .35rem; font-size:.72rem; }
.vh-inp { height:24px; padding:0 .35rem; border:1px solid var(--vh-blue-b); border-radius:.25rem;
    background:#fff; color:#20414d; font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; box-sizing:border-box; }
.vh-inp:focus { outline:none; border-color:var(--vh-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
.vh-inp:read-only { background:#fbfdfe; }
.vh-inp-w { width:180px; } .vh-inp-n { width:96px; text-align:right; }
input[type=date].vh-inp { padding:0 .2rem; }

/* Панели списка и подформ: стек на остаток экрана.
   Список элементов (SubfrmStat) — половина остатка, четыре панели подформ делят вторую
   половину поровну (по 12.5%). Пропорции заданы flex-grow: 2 против 0.5. */
.vh-stack { display:flex; flex-direction:column; gap:.4rem; flex:1 1 auto; min-height:0; }
.vh-stack > * { flex:0.5 1 0; min-height:4rem; display:flex; flex-direction:column; }
.vh-stack > .vh-pane-main { flex:2 1 0; min-height:7rem; }
.vh-pane-main > .vh-panel { flex:1 1 auto; min-height:0; }

.vh-panel { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.5rem;
    box-shadow:0 1px 3px rgba(64,179,216,.12); overflow:hidden; display:flex; flex-direction:column; min-height:0; }
.vh-panel-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex:0 0 auto;
    padding:.22rem .6rem; background:var(--vh-blue-l); border-bottom:1px solid var(--vh-blue-b);
    color:var(--vh-ink); font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.03em; }
.vh-tools { display:flex; align-items:center; gap:.35rem; text-transform:none; letter-spacing:0; font-weight:400; }

.vh-list-scroll { overflow:auto; flex:1 1 auto; min-height:0; }
/* Заглушка нереализованной подформы */
.vh-stub { padding:.6rem; color:#9db9c4; font-style:italic; }
.vh-list { border-collapse:collapse; width:100%; font-size:12px; }
.vh-list th { position:sticky; top:0; z-index:1; background:var(--vh-blue-l); color:var(--vh-ink);
    font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.02em;
    padding:.2rem .35rem; border:1px solid var(--vh-blue-b); white-space:nowrap; text-align:left; }
.vh-list th a { color:inherit; text-decoration:none; }
.vh-list th.asc::after { content:' ▲'; } .vh-list th.desc::after { content:' ▼'; }
.vh-list td { padding:.12rem .35rem; border-bottom:1px solid #e6f2f7;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:420px; }
.vh-list tbody tr { cursor:default; }
.vh-list tbody tr:hover:not(.sel) td { background:#f4fafc; }
/* Текущая запись: в Access OnCurrent красит строку по Код = CurKd */
.vh-list tbody tr.sel td { background:#d6ecf7; box-shadow:inset 3px 0 var(--vh-blue); }
.vh-list .empty { color:#7d9aa7; font-style:italic; padding:.5rem .6rem !important; }
/* Резервы: условное форматирование подформы SubMinimFilter (ConditionalFormat14)
   на колонках «Название темы» и «Пробл»: [DtaPost]>Date() — пурпурный, =Date() — оливковый. */
.vh-list td.res-future { background:rgba(255,0,255,.16); }
.vh-list td.res-today { background:rgba(128,128,0,.20); }
/* Галка «Счёт запр.» в списке (CheckBox RequestSch) */
.vh-chk { width:13px; height:13px; margin:0; cursor:pointer; vertical-align:middle; accent-color:var(--vh-blue); }

/* Легенда цветов — окно поверх страницы (как «? Цвета» в списке счетов) */
.vh-modal { display:none; position:fixed; inset:0; z-index:1000; background:rgba(27,92,114,.28);
    align-items:center; justify-content:center; }
.vh-modal.on { display:flex; }
.vh-modal-box { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.6rem; min-width:320px;
    max-width:min(920px,94vw); max-height:calc(100vh - 24px); display:flex; flex-direction:column;
    box-shadow:0 16px 44px rgba(27,92,114,.30); overflow:hidden; }
.vh-modal-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; flex:0 0 auto;
    padding:.4rem .7rem; background:var(--vh-blue-l); border-bottom:1px solid var(--vh-blue-b);
    color:var(--vh-ink); font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; }
.vh-modal-x { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.3rem; color:var(--vh-ink);
    width:22px; height:20px; font-size:11px; line-height:1; cursor:pointer; }
.vh-modal-x:hover { background:var(--vh-blue); border-color:var(--vh-blue); color:#fff; }
.vh-modal-body { padding:.6rem .75rem; overflow:auto; }
.vh-hint { margin:0 0 .5rem; font-size:.72rem; color:#5b8598; }
/* Легенда — списком по колонкам, как «Цвета в списке счетов» (vh-schet) */
.vh-hints { display:grid; grid-template-columns:1fr; gap:.55rem; }
#stHintsBox .vh-modal-box { max-width:560px; }
.vh-hints-col { min-width:0; }
.vh-hints-t { font-size:.68rem; font-weight:600; color:var(--vh-ink); text-transform:uppercase;
    letter-spacing:.03em; border-bottom:1px solid var(--vh-blue-b); padding-bottom:.15rem; margin-bottom:.25rem; }
.vh-hint-row { display:flex; align-items:center; gap:.4rem; font-size:.78rem; padding:.05rem 0; }
.vh-sw { flex:0 0 auto; width:14px; height:14px; border-radius:.2rem; border:1px solid rgba(27,92,114,.25); }
.vh-sw-t { color:#20414d; }
.vh-b { font-weight:700; }
/* Цвета — ровно те, что ставит fnRepaintFormStat / ConditionalFormat14 (Access BGR → RGB) */
.vh-sw.sw-red { background:#ff0000; }      .vh-sw-t.sw-red { color:#c00; }
.vh-sw.sw-blue { background:#0000ff; }     .vh-sw-t.sw-blue { color:#0000cc; }
.vh-sw.sw-green { background:#ccffcc; }    .vh-sw-t.sw-green { color:#0a7a2a; }
.vh-sw.sw-yellow { background:#ffff00; }   .vh-sw-t.sw-yellow { color:#8a7a00; }
.vh-sw.sw-sky { background:#ccffff; }      .vh-sw-t.sw-sky { color:#0f7f8f; }
.vh-sw.sw-peach { background:#fce6d4; }    .vh-sw-t.sw-peach { color:#a8641f; }
.vh-sw.sw-azure { background:#0ec2ff; }    .vh-sw-t.sw-azure { color:#0a7f9c; }
.vh-sw.sw-violet { background:#9966ff; }   .vh-sw-t.sw-violet { color:#5a3a9a; }
.vh-sw.sw-mint { background:#b1ffb1; }
.vh-sw.sw-magenta { background:rgba(255,0,255,.35); }
.vh-sw.sw-olive { background:rgba(128,128,0,.45); }
.vh-sw.sw-plum { background:#993366; }     .vh-sw-t.sw-magenta-t { color:#c000c0; }
.vh-sw.sw-plain { background:#fff; }
.empty { color:#7d9aa7; font-style:italic; padding:.5rem .6rem; }

/* Футер frmMinimum: граница цены, курс, разница, «без тех.запаса» */
.vh-foot { display:flex; align-items:center; flex-wrap:wrap; gap:.3rem .8rem;
    padding:.3rem .6rem; background:var(--vh-blue-l); border-top:1px solid var(--vh-blue-b); }
.vh-foot-f { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; color:var(--vh-ink); margin:0; }
.vh-foot-f > span { white-space:nowrap; }
.vh-foot-warn { color:#c05a00; font-size:.72rem; font-weight:600; }

/* Строка подсказки под списком (в Access — Descr.Caption главной формы frmSt) */
.vh-stat-hint { min-height:22px; padding:.15rem .6rem; background:#fbfeff; border-top:1px solid #e6f2f7;
    color:#3f6d80; font-size:.72rem; line-height:1.35; }
.vh-stat-hint:empty::before { content:'Наведите курсор на ячейку — появится подсказка о цветах и обозначениях'; color:#9db9c4; }

/* Подформа аналогов (subFrmStAnalog): свой заголовок и полоса прокрутки */
.vh-an-code { text-transform:none; letter-spacing:0; font-weight:400; color:#5b8598; }
.vh-an-scroll { min-height:34px; padding:.1rem .2rem; }

/* Диалог текста (frmShowPrim): примечание, комментарий, выбор состояния */
.vh-sdlg { position:fixed; inset:0; z-index:1050; display:flex; align-items:center; justify-content:center;
    background:rgba(27,92,114,.28); }
.vh-sdlg[hidden] { display:none; }
.vh-sdlg-box { width:min(560px,94vw); background:#fff; border:1px solid var(--vh-blue-b); border-radius:.5rem;
    box-shadow:0 12px 32px rgba(27,92,114,.28); display:flex; flex-direction:column; }
.vh-sdlg-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.3rem .6rem;
    background:var(--vh-blue-l); border-bottom:1px solid var(--vh-blue-b); color:var(--vh-ink); font-weight:600; }
.vh-sdlg-body { padding:.5rem .6rem; }
.vh-sdlg-body textarea { width:100%; height:150px; box-sizing:border-box; resize:vertical; padding:.3rem .4rem;
    border:1px solid var(--vh-blue-b); border-radius:.3rem; font:12px/1.45 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
    color:#20414d; }
.vh-sdlg-body textarea:focus, .vh-sdlg-body select:focus { outline:none; border-color:var(--vh-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
.vh-sdlg-body select { width:100%; border:1px solid var(--vh-blue-b); border-radius:.3rem;
    font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d; }
.vh-sdlg-foot { display:flex; align-items:center; gap:.35rem; padding:.35rem .6rem;
    background:var(--vh-blue-l); border-top:1px solid var(--vh-blue-b); }
.vh-sdlg-msg { margin-right:auto; color:#a4232b; }
.vh-sdlg-msg.ok { color:#1b7a3d; }

@media (max-width:1199.98px) {
    /* На узком экране пропорции не работают: панели получают свою высоту, страница прокручивается */
    .vh-stat { height:auto; min-height:calc(100vh - 92px); }
    .vh-stack > *, .vh-stack > .vh-pane-main { flex:0 0 auto; }
    .vh-list-scroll { max-height:50vh; }
}
CSS);

$this->registerJs('window.ST = ' . Json::encode([
    'rowsUrl' => Url::to(['rows']),
    'analogsUrl' => Url::to(['analogs']),        // аналоги текущего элемента (subFrmStAnalog)
    'reservesUrl' => Url::to(['reserves']),      // резервы текущего элемента (SubMinimFilter)
    'invoicesUrl' => Url::to(['invoices']),      // предложения поставщиков (SubFrmInvoices)
    'trgRecalcUrl' => Url::to(['trg-recalc']),   // пересчёт таргета по резерву (TrgRecalc)
    'statusesUrl' => Url::to(['statuses']),      // состояния проблемы (frmfndStatus, OpenArgs 25)
    'actUrl' => Url::to(['act']),                // сохранение примечания / комментария / состояния
    'cardUrl' => Url::to(['/sklad/elem/view']),   // карточка элемента (Fobnovlen1) — модуль «Склад»
    'vid'     => (string)$filter->vid,           // текущий вид: часть двойных щелчков зависит от него
    'canProblEl' => Yii::$app->user->can('ProblEl', ['withMe' => false]),
    'hints'   => $hints,                         // подсказки по колонкам (Descr.Caption в Access)
    'period'  => 540000,            // TimerInterval = 540000 мс (9 минут), как в frmMinimum
]) . ';', \yii\web\View::POS_BEGIN);

$this->registerJs(<<<'JS'
/**
 * Автообновление списка «Статистики» — Form_Timer формы frmMinimum (TimerInterval = 540000).
 *
 * Что делает: раз в 9 минут перечитывает список с теми же фильтрами и возвращается
 * к текущей записи по «Код» (в Access: запоминает txtID, перезапрашивает, ищет запись заново).
 * Обновляется только тело таблицы — фильтры и ввод в полях не трогаются.
 */
(function () {
    const cfg = window.ST || {};
    const tbl = document.getElementById('stList');
    if (!tbl || !cfg.rowsUrl) return;

    const body   = tbl.tBodies[0];
    const scroll = tbl.closest('.vh-list-scroll');
    const count  = document.getElementById('stCount');
    const upd    = document.getElementById('stUpd');
    const msg    = document.getElementById('stMsg');
    const btn    = document.getElementById('stRefresh');
    const period = cfg.period || 540000;

    let cur = '';            // Код текущей записи
    let busy = false;
    let last = Date.now();

    const setCur = code => {
        cur = code || '';
        [...body.rows].forEach(tr => tr.classList.toggle('sel', !!cur && tr.dataset.code === cur));
        loadAnalogs(cur);
        loadReserves(cur);
        loadInvoices(cur);
    };

    /**
     * Аналоги текущего элемента — подформа аналогов (subFrmStAnalog).
     * В Access подформа сама перезапрашивалась на Form_Current: exec xp_frmStRS1Analog(Me![Код]).
     */
    async function loadAnalogs(code) {
        const box  = document.getElementById('stAnBox');
        const info = document.getElementById('stAnInfo');
        const lab  = document.getElementById('stAnCode');
        if (!box || !cfg.analogsUrl) return;

        if (!code) {
            box.replaceChildren();
            if (lab) lab.textContent = '';
            if (info) info.textContent = 'выберите строку в списке';
            return;
        }

        if (lab) lab.textContent = '№ ' + code;
        if (info) info.textContent = 'загрузка…';
        try {
            const r = await fetch(cfg.analogsUrl + '?id=' + encodeURIComponent(code),
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            if (cur !== code) return;                    // пока грузили, выбрали другую запись
            box.innerHTML = d.html || '';
            if (info) info.textContent = d.error ? '' : (d.count ? 'строк: ' + d.count : 'аналогов нет');
        } catch (e) {
            if (cur !== code) return;
            if (info) info.textContent = '';
            const x = document.createElement('div');
            x.className = 'vh-alert';
            x.textContent = 'Не удалось получить аналоги: ' + e.message;
            box.replaceChildren(x);
        }
    }

    /**
     * Резервы текущего элемента — подформа SubMinimFilter.
     * В Access подформа перезапрашивалась вместе с текущей записью:
     * exec xp_SubMinimumFilter @txtID, @Rgm (Rgm — вид списка).
     */
    async function loadReserves(code) {
        const box  = document.getElementById('stResBox');
        const info = document.getElementById('stResInfo');
        const lab  = document.getElementById('stResCode');
        if (!box || !cfg.reservesUrl) return;

        if (!code) {
            box.replaceChildren();
            if (lab) lab.textContent = '';
            if (info) info.textContent = 'выберите строку в списке';
            return;
        }

        if (lab) lab.textContent = '№ ' + code;
        if (info) info.textContent = 'загрузка…';
        try {
            const r = await fetch(cfg.reservesUrl + '?id=' + encodeURIComponent(code)
                + '&vid=' + encodeURIComponent(cfg.vid || '1'),
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            if (cur !== code) return;
            box.innerHTML = d.html || '';
            if (info) info.textContent = d.error ? '' : (d.count ? 'строк: ' + d.count : 'резервов нет');
        } catch (e) {
            if (cur !== code) return;
            if (info) info.textContent = '';
            const x = document.createElement('div');
            x.className = 'vh-alert';
            x.textContent = 'Не удалось получить резервы: ' + e.message;
            box.replaceChildren(x);
        }
    }

    /**
     * Предложения поставщиков текущего элемента — подформа Invoices (SubFrmInvoices).
     * В Access подформа перезапрашивалась вместе с текущей записью:
     * exec xp_GetInvoices1 @SkladID, @Numtbl, @IDRes, @Tpe, @DtaTxt (в frmSt — только просмотр).
     */
    async function loadInvoices(code) {
        const box  = document.getElementById('stInvBox');
        const info = document.getElementById('stInvInfo');
        const lab  = document.getElementById('stInvCode');
        if (!box || !cfg.invoicesUrl) return;

        if (!code) {
            box.replaceChildren();
            if (lab) lab.textContent = '';
            if (info) info.textContent = 'выберите строку в списке';
            return;
        }

        if (lab) lab.textContent = '№ ' + code;
        if (info) info.textContent = 'загрузка…';
        try {
            const r = await fetch(cfg.invoicesUrl + '?id=' + encodeURIComponent(code),
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            if (cur !== code) return;                    // пока грузили, выбрали другую запись
            box.innerHTML = d.html || '';
            if (info) info.textContent = d.error ? '' : (d.count ? 'строк: ' + d.count : 'предложений нет');
        } catch (e) {
            if (cur !== code) return;
            if (info) info.textContent = '';
            const x = document.createElement('div');
            x.className = 'vh-alert';
            x.textContent = 'Не удалось получить предложения: ' + e.message;
            box.replaceChildren(x);
        }
    }

    // TrgRecalc_Click: нет резервов — «Не выбран резерв»; IDRes <= 0 — «Не удается найти резерв»;
    // иначе номер сводной таблицы (по умолчанию 1), подтверждение и xp_UpdTrgRec.
    const trgBtn = document.getElementById('stTrgRecalc');
    if (trgBtn && cfg.trgRecalcUrl) {
        trgBtn.addEventListener('click', async () => {
            const box = document.getElementById('stResBox');
            const info = document.getElementById('stResInfo');
            const rows = box ? box.querySelectorAll('#stResList tbody tr') : [];
            if (!rows.length) { alert('Не выбран резерв'); return; }

            const idres = parseInt(rows[0].dataset.idres || '0', 10);
            if (!(idres > 0)) { alert('Не удается найти резерв'); return; }

            const raw = prompt('Укажите номер сводной таблицы', '1');
            if (raw === null) return;
            const numtbl = parseInt(raw, 10);
            if (!(numtbl > 0)) return;
            if (!confirm('Обновить данные таргета и целевого предложения по всем элементам, входящим в резерв?')) return;

            trgBtn.disabled = true;
            try {
                const r = await fetch(cfg.trgRecalcUrl, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest',
                               'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ idres: idres, numtbl: numtbl }).toString(),
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const d = await r.json();
                if (!d.ok) { alert(d.error || 'Не удалось обновить таргет'); return; }
                // Access после UpdTrgRec перезапрашивает подформу резервов
                await loadReserves(cur);
                if (info) info.textContent = 'таргет обновлён';
            } catch (e) {
                alert('Не удалось обновить таргет: ' + e.message);
            } finally {
                trgBtn.disabled = false;
            }
        });
    }

    // OnCurrent: щелчок по строке делает её текущей
    tbl.addEventListener('click', e => {
        if (tbl.dataset.resizing) return;               // тянули столбец — это не выбор записи
        const tr = e.target.closest('tbody tr[data-code]');
        if (tr) setCur(tr.dataset.code);
    });

    /**
     * RequestSch_MouseDown / NotRec_MouseDown: галки «Счёт запр.» и «Не рек».
     * Access переключает флаг процедурой UpdateNotRecFlg(txtID, "ReqSch"|"NotRec")
     * и перезапрашивает строку (StndOnLne). Запрос идёт через postAct — он добавляет CSRF-токен.
     */
    tbl.addEventListener('change', async e => {
        const chk = e.target.closest('input.vh-chk');
        if (!chk || !cfg.actUrl || chk.disabled) return;
        const code = chk.dataset.code;
        const act  = chk.dataset.act || 'ReqSch';
        chk.disabled = true;
        try {
            const d = await postAct({ id: code, act });
            if (!d.ok) {
                chk.checked = !chk.checked;             // вернуть прежнее состояние
                alert(d.error || 'Не удалось изменить флажок');
                return;
            }
            // StndOnLne: перезапрос списка с возвратом к текущей записи — цвета придут с сервера
            refresh();
        } catch (err) {
            chk.checked = !chk.checked;
            alert('Не удалось изменить флажок: ' + err.message);
        } finally {
            chk.disabled = false;
        }
    });

    /**
     * Подсказка по колонке — Descr.Caption главной формы frmSt, который подставляли
     * обработчики *_GotFocus подформы frmMinimum. В вебе вместо фокуса ячейки — наведение:
     * у таблицы нет поячеечного фокуса, а смысл подсказки тот же.
     */
    const hint = document.getElementById('stHint');
    const hintFor = f => (cfg.hints && f && cfg.hints[f]) || '';

    const showHint = f => {
        if (!hint) return;
        const t = hintFor(f);
        hint.textContent = t || '';
    };

    tbl.addEventListener('mouseover', e => {
        const td = e.target.closest('tbody td[data-f]');
        showHint(td ? td.dataset.f : '');
    });
    tbl.addEventListener('mouseleave', () => showHint(''));

    // OpenKartElem: двойной щелчок по строке открывает карточку элемента (Fobnovlen1) в отдельном окне.
    // В Access это DoCmd.OpenForm "Fobnovlen1", ..., acDialog, Me![Код] — окно поверх списка.
    const openCard = code => {
        if (!cfg.cardUrl) return;
        window.open(cfg.cardUrl + '?id=' + encodeURIComponent(code), 'elem' + code);
    };

    // Колонки, у которых в Access обработчик двойного щелчка не задан (или он ничего не делает)
    const NOOP_FIELDS = new Set(['LowR', 'NotRec', 'VP5', 'NoAnalog', 'KolPok', 'FZ275Kol',
        'RequestSch', 'IDInvoice', 'OrderKat', 'chk', 'MainPlace', 'IDCursor']);

    const cellVal = (tr, f) => (tr.querySelector('td[data-f="' + f + '"]')?.textContent || '').trim();

    /**
     * Двойной щелчок по строке списка — маршрутизация по колонке, как набор обработчиков
     * *_DblClick подформы frmMinimum:
     *   Наименование, Номинал, Корпус, Изготовитель, Количество, Недополучено, txtSV, МинКоличество
     *                            → OpenKartElem (карточка элемента);
     *   ГдеКупил = «ПЕРЕЛОЖИТЬ НА СКЛАДЕ» → frmMoveEl (перенос, пока не перенесён — сообщаем);
     *   Код                      → frmfndStatus 25 (право ProblEl, вид 1/3, «Проблемы» в «ГдеКупил»),
     *                              иначе OpenKartElem;
     *   Аналог                   → frmShowPrim 58 — просмотр текста;
     *   Примечание               → frmShowPrim 83 + xp_SkladUpdPrim — правка текста;
     *   SostProblEl              → только вид «b»: комментарий производства (xp_SkladTrackingUpdComm);
     *   DfltIsp                  → только вид «b»: просмотр «Информация» (frmShowPrim 81).
     */
    tbl.addEventListener('dblclick', e => {
        if (tbl.dataset.resizing) return;
        if (e.target.closest('input')) return;          // галка «Счёт запр.» — не открывать карточку
        const tr = e.target.closest('tbody tr[data-code]');
        if (!tr) return;
        const code = tr.dataset.code;
        const f = e.target.closest('td[data-f]')?.dataset.f || '';

        if (f === 'Analog') {
            if (cfg.vid !== 'b') showText('Аналог элемента', cellVal(tr, f));
            else openCard(code);
            return;
        }
        if (f === 'Примечание' || f === 'ПримечаниеДолг') {
            editText('Примечание элемента', cellVal(tr, f), 500, code, 'Prim');
            return;
        }
        if (f === 'SostProblEl') {
            // В Access: только для вида «b», у остальных обработчик пустой
            if (cfg.vid === 'b') editText('Комментарий производства', cellVal(tr, f), 1024, code, 'Comm');
            return;
        }
        if (f === 'DfltIsp') {
            // В Access: только для вида «b» (просмотр «Информация»), у остальных обработчик пустой
            if (cfg.vid === 'b') showText('Информация', cellVal(tr, f));
            return;
        }
        if (f === 'Код') {
            const problem = (cfg.vid === '1' || cfg.vid === '3') && cellVal(tr, 'ГдеКупил').includes('Проблемы');
            if (cfg.canProblEl && problem) { pickStatus(code); return; }
            openCard(code);
            return;
        }
        if (f === 'ГдеКупил') {
            // В Access: frmMoveEl с OpenArgs = Код & "3"; форма переноса ещё не перенесена
            if (cellVal(tr, f) === 'ПЕРЕЛОЖИТЬ НА СКЛАДЕ') {
                showText('Перенос элемента', 'Перенос элемента на склад (форма frmMoveEl) пока не перенесён в веб.');
                return;
            }
            openCard(code);
            return;
        }
        if (NOOP_FIELDS.has(f)) return;
        openCard(code);                                  // OpenKartElem — по умолчанию, как в Access
    });

    /* ---------- Диалог текста: frmShowPrim (ветки 58 — просмотр, 83 — правка) ---------- */
    const dlg   = document.getElementById('stDlg');
    const dlgT  = document.getElementById('stDlgT');
    const dlgTx = document.getElementById('stDlgText');
    const dlgSl = document.getElementById('stDlgSel');
    const dlgMs = document.getElementById('stDlgMsg');
    const dlgOk = document.getElementById('stDlgOk');
    const dlgNo = document.getElementById('stDlgNo');
    const dlgX  = document.getElementById('stDlgX');

    let dlgSubmit = null;        // что делать по «Сохранить»; null — режим просмотра

    const dlgMsg = (text, ok = false) => {
        if (!dlgMs) return;
        dlgMs.textContent = text || '';
        dlgMs.classList.toggle('ok', !!ok);
    };

    function closeDlg() {
        if (dlg) dlg.hidden = true;
        dlgSubmit = null;
        dlgMsg('');
    }

    function openDlg(title, mode) {
        if (!dlg) return false;
        if (dlgT) dlgT.textContent = title || '';
        const readOnly = mode === 'text-ro' || mode === 'list';
        if (dlgTx) dlgTx.hidden = mode !== 'text' && mode !== 'text-ro';
        if (dlgSl) dlgSl.hidden = mode !== 'list';
        if (dlgOk) dlgOk.hidden = readOnly;
        dlgMsg('');
        dlg.hidden = false;
        return true;
    }

    function showText(title, text) {
        if (!openDlg(title, 'text-ro') || !dlgTx) return;
        dlgTx.value = text || '';
        dlgTx.setAttribute('readonly', 'readonly');
        dlgTx.focus();
        dlgTx.setSelectionRange(0, 0);
    }

    function editText(title, text, max, code, act) {
        if (!openDlg(title, 'text') || !dlgTx) return;
        dlgTx.value = text || '';
        dlgTx.removeAttribute('readonly');
        dlgTx.maxLength = max || 500;
        dlgTx.focus();
        dlgTx.setSelectionRange(0, 0);
        dlgSubmit = async () => {
            // CommonStartSP6 Warn = 1: Access предупреждает, что текст уменьшится, но сохраняет
            const cut = dlgTx.value.slice(0, max || 500);
            const res = await postAct({ act, id: code, text: cut });
            if (!res.ok) { dlgMsg(res.error || 'Не удалось сохранить.'); return; }
            closeDlg();
            refresh();                                   // Form_Timer / StndOnLne: перечитать список
        };
    }

    /** Выбор состояния проблемы: frmfndStatus (OpenArgs 25) → xp_StatWorkWithProblemUpdSost. */
    async function pickStatus(code) {
        if (!openDlg('Выбор состояния', 'list') || !dlgSl) return;
        if (dlgOk) dlgOk.hidden = true;                  // пока список не загружен, сохранять нечего
        dlgSl.replaceChildren();
        dlgMsg('загрузка…');
        let items = [];
        try {
            const r = await fetch(cfg.statusesUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (d.error) { dlgMsg(d.error); return; }
            items = d.items || [];
        } catch (err) {
            dlgMsg('Не удалось получить список состояний: ' + err.message);
            return;
        }
        if (!items.length) { dlgMsg('Список состояний пуст.'); return; }
        dlgMsg('');
        if (dlgOk) dlgOk.hidden = false;
        dlgSl.replaceChildren(...items.map(it => {
            const o = document.createElement('option');
            o.value = it.id;
            o.textContent = it.name;
            return o;
        }));
        dlgSl.focus();
        dlgSubmit = async () => {
            const sost = dlgSl.value;
            if (!sost) { dlgMsg('Выберите состояние.'); return; }
            const res = await postAct({ act: 'Sost', id: code, sost });
            if (!res.ok) { dlgMsg(res.error || 'Не удалось изменить состояние.'); return; }
            closeDlg();
            refresh();
        };
    }

    /** POST на StatController::actionAct с CSRF-токеном (как в остальных экранах проекта). */
    async function postAct(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
        const cp = document.querySelector('meta[name=csrf-param]')?.content;
        if (cp) fd.append(cp, document.querySelector('meta[name=csrf-token]')?.content || '');
        try {
            const r = await fetch(cfg.actUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) {
                // 400 — обычно истёк CSRF-токен: страница открыта давно, сессия перезаписана
                return { ok: false, error: r.status === 400
                    ? 'Сервер отклонил запрос (400). Обновите страницу (⟳ Обновить) и повторите.'
                    : 'Ошибка сервера: HTTP ' + r.status };
            }
            return await r.json();
        } catch (e) {
            return { ok: false, error: 'Не удалось связаться с сервером: ' + e.message };
        }
    }

    if (dlgOk) dlgOk.addEventListener('click', () => { if (dlgSubmit) dlgSubmit(); });
    if (dlgNo) dlgNo.addEventListener('click', closeDlg);
    if (dlgX)  dlgX.addEventListener('click', closeDlg);
    if (dlgSl) dlgSl.addEventListener('dblclick', () => { if (dlgSubmit) dlgSubmit(); });
    if (dlg)   dlg.addEventListener('click', e => { if (e.target === dlg) closeDlg(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && dlg && !dlg.hidden) closeDlg(); });

    // Легенда цветов: «? Цвета» в шапке списка (как btnHints в списке счетов)
    const hints    = document.getElementById('stHintsBox');
    const hintsBtn = document.getElementById('stHints');
    const hintsX   = document.getElementById('stHintsX');
    const closeHints = () => hints && hints.classList.remove('on');
    if (hintsBtn && hints) hintsBtn.addEventListener('click', () => hints.classList.add('on'));
    if (hintsX) hintsX.addEventListener('click', closeHints);
    if (hints)  hints.addEventListener('click', e => { if (e.target === hints) closeHints(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeHints(); });

    // Легенда цветов подформы резервов
    const resHints    = document.getElementById('stResHintsBox');
    const resHintsBtn = document.getElementById('stResHints');
    const resHintsX   = document.getElementById('stResHintsX');
    const closeResHints = () => resHints && resHints.classList.remove('on');
    if (resHintsBtn && resHints) resHintsBtn.addEventListener('click', () => resHints.classList.add('on'));
    if (resHintsX) resHintsX.addEventListener('click', closeResHints);
    if (resHints)  resHints.addEventListener('click', e => { if (e.target === resHints) closeResHints(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeResHints(); });

    // Легенда цветов подформы предложений поставщиков (SubFrmInvoices)
    const invHints    = document.getElementById('stInvHintsBox');
    const invHintsBtn = document.getElementById('stInvHints');
    const invHintsX   = document.getElementById('stInvHintsX');
    const closeInvHints = () => invHints && invHints.classList.remove('on');
    if (invHintsBtn && invHints) invHintsBtn.addEventListener('click', () => invHints.classList.add('on'));
    if (invHintsX) invHintsX.addEventListener('click', closeInvHints);
    if (invHints)  invHints.addEventListener('click', e => { if (e.target === invHints) closeInvHints(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeInvHints(); });

    const stamp = () => {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        if (upd) upd.textContent = 'обновлено ' + p(d.getHours()) + ':' + p(d.getMinutes());
    };

    async function refresh(silent) {
        if (busy) return;
        busy = true;
        if (btn) btn.disabled = true;
        const top = scroll ? scroll.scrollTop : 0;
        try {
            // Фоновое обновление по таймеру не показывает окно «система занята»
            const r = await fetch(cfg.rowsUrl + location.search,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' }, busy: silent ? false : undefined });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            body.innerHTML = d.html || '';
            showHint('');                                // строки новые — старая подсказка уже не про них
            if (count) count.textContent = d.count ?? 0;
            if (msg) {
                const box = el => { const x = document.createElement('div'); x.className = el.cls; x.textContent = el.text; return x; };
                msg.replaceChildren(...[d.error && box({ cls: 'vh-alert', text: d.error }),
                                        d.note && box({ cls: 'vh-note', text: d.note })].filter(Boolean));
            }
            setCur(cur);                                 // вернулись к той же записи по Код
            const tr = cur ? [...body.rows].find(r => r.dataset.code === cur) : null;
            if (tr) tr.scrollIntoView({ block: 'nearest' });
            else if (scroll) scroll.scrollTop = top;
            last = Date.now();
            stamp();
        } catch (e) {
            if (msg) {
                const x = document.createElement('div');
                x.className = 'vh-alert';
                x.textContent = 'Не удалось обновить список: ' + e.message;
                msg.replaceChildren(x);
            }
            if (upd) upd.textContent = 'обновление не удалось';
        } finally {
            busy = false;
            if (btn) btn.disabled = false;
        }
    }

    // Вкладка не видна — запрос не шлём; вернулись и срок прошёл — обновляем сразу
    setInterval(() => {
        if (document.hidden) return;
        if (Date.now() - last < period - 1000) return;
        refresh(true);
    }, 30000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - last >= period) refresh(true);
    });
    if (btn) btn.addEventListener('click', refresh);
})();
JS, \yii\web\View::POS_END);

// «Все» и конкретные фирмы в мультивыборе исключают друг друга
$this->registerJs(<<<'JS'
/**
 * Кнопка «Даты» — GetInt_Click формы frmSt: открывает frmGetInterval (interval.js),
 * полученные даты кладёт в PickDate/PickDate2 и сразу перечитывает список
 * (в Access после заполнения вызывается cboflt_AfterUpdate).
 */
(function () {
    const btn = document.getElementById('stDates');
    const frm = document.getElementById('frmSt');
    if (!btn || !frm || typeof getInterval !== 'function') return;

    btn.addEventListener('click', async () => {
        const cur = { beg: frm.dateS?.value || '', end: frm.datePo?.value || '' };
        // Режим 1: начать с переданных дат (Form_Load frmGetInterval); пусто — «месяц» на сегодня
        const r = await getInterval(cur.beg || cur.end ? 1 : 0, cur);
        if (!r) return;
        frm.dateS.value = r[0];
        frm.datePo.value = r[1];
        frm.submit();
    });
})();

(function () {
    const boxes = [...document.querySelectorAll('input[name="f[]"]')];
    const all = boxes.find(b => b.value === '%');
    if (!all) return;
    boxes.forEach(cb => cb.addEventListener('change', () => {
        if (cb === all && cb.checked) {
            boxes.forEach(o => { if (o !== all) o.checked = false; });
        } else if (cb !== all && cb.checked) {
            all.checked = false;
        }
    }));
})();
JS, \yii\web\View::POS_END);

echo $this->render('@app/views/access/_help');
