<?php
/**
 * «Входящие счета» — современный вид (каркас).
 *
 * Данные, поведение и окна — те же, что в виде «как в Access»: разметку обрабатывает web/js/vh-schet.js,
 * который ищет таблицу #vhList, списки панели (lstOplPrc, lstOplPrcToDo, lstPlat, lstDogs), поля панели
 * (SmSch, OstOPl, txtPrim, txtComm, LastAnsw, LastAnswDta, PthSch, CommOpl) и кнопки по id.
 * Поэтому здесь сохранены ВСЕ id формы Access, ТИПЫ полей (input/textarea — скрипт пишет в .value)
 * и ПОРЯДОК колонок таблицы (скрипт правит tr.cells[1] — примечание, tr.cells[16] — комментарий).
 *
 * Отличие от старого вида — только раскладка: вместо координат left/top — сетка Bootstrap 5,
 * корпоративные цвета (голубой/белый) и во всю ширину экрана (контейнер страницы не ограничиваем).
 * Переключение вида — кнопка «Как в Access» (?view=access), выбор запоминается в сессии.
 *
 * @var yii\web\View $this
 * @var app\modules\zakupki\models\VhSchetFilter $filter
 * @var array $rows
 * @var float|null $total
 */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Входящие счета';
$oneSch = $filter->id > 0;                                                  // открыта на один счёт (OpenArgs)
$filterApplied = $filter->dateS || $filter->datePo || $filter->nsch !== '' || (string)$filter->firma !== '';  // как IsApplyFlt
$typeSch = (int)$filter->typeSch;
?>
<div class="vh-modern">

    <?php // Шапка в одну строку: заголовок, счётчик и переключатель вида ?>
    <div class="vh-head d-flex align-items-center flex-wrap gap-2 mb-2">
        <h1 class="h5 mb-0" title="Список счетов за период и работа по выбранному счёту">Входящие счета</h1>
        <span class="badge vh-count fw-normal">Счетов: <?= count($rows) ?></span>
        <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
            <?php // Переключение вида: вид запоминается в сессии, фильтры сохраняются в адресе ?>
            <a class="btn btn-sm btn-outline-secondary" title="Вид как в форме Access"
               href="<?= Html::encode(Url::to(array_merge(['index'], Yii::$app->request->get(), ['view' => 'access']))) ?>">☰ Вид как в Access</a>
        </div>
    </div>

    <?php foreach ($filter->getFirstErrors() as $e): ?>
        <div class="alert alert-danger py-1"><?= Html::encode($e) ?></div>
    <?php endforeach ?>

    <?php // Фильтры — та же форма GET (butF_Click): имена полей как у формы Access ?>
    <form method="get" action="<?= Url::to(['index']) ?>" class="card card-body py-2 vh-filter mb-2" id="frmStat">
        <?= Html::hiddenInput('firma', $filter->firma, ['id' => 'cboFirma']) ?>
        <?= Html::hiddenInput('view', 'modern') ?>
        <?php if ($oneSch): ?>
            <?= Html::hiddenInput('id', $filter->id) ?>
            <?php // В режиме одного счёта переключатели заблокированы, а disabled-поля в GET не попадают.
                  // typeSch при этом нужен процедуре xp_GetAllSch — передаём его скрытым полем ?>
            <?= Html::hiddenInput('typeSch', $typeSch) ?>
        <?php endif ?>

        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label small text-secondary mb-1" for="frmDateS">Счета с</label>
                <input type="date" class="form-control form-control-sm" id="frmDateS" name="dateS"
                       value="<?= Html::encode($filter->dateS) ?>" <?= $oneSch ? 'disabled' : '' ?>>
            </div>
            <div class="col-auto">
                <label class="form-label small text-secondary mb-1" for="frmDatePo">по</label>
                <input type="date" class="form-control form-control-sm" id="frmDatePo" name="datePo"
                       value="<?= Html::encode($filter->datePo) ?>" <?= $oneSch ? 'disabled' : '' ?>>
            </div>
            <div class="col-auto">
                <label class="form-label small text-secondary mb-1" for="frmNsch">№ счёта</label>
                <input type="text" class="form-control form-control-sm" id="frmNsch" name="nsch" maxlength="18"
                       value="<?= Html::encode($filter->nsch) ?>" <?= $oneSch ? 'disabled' : '' ?>>
            </div>

            <?php // TypeSel: сырьё (1 — НЕ давальческое, 2 — давальческое, 3 — не важно) ?>
            <div class="col-auto">
                <span class="form-label small text-secondary mb-1 d-block">Сырьё</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Тип сырья">
                    <?php foreach ([1 => 'НЕ дав.', 2 => 'Дав.', 3 => 'Не важно'] as $v => $t): ?>
                        <input type="radio" class="btn-check" name="typeSel" id="typeSel<?= $v ?>" value="<?= $v ?>"
                               autocomplete="off" <?= (int)$filter->typeSel === $v ? 'checked' : '' ?>
                               <?= $oneSch ? 'disabled' : '' ?> onchange="this.form.submit()">
                        <label class="btn btn-outline-secondary" for="typeSel<?= $v ?>"><?= $t ?></label>
                    <?php endforeach ?>
                </div>
            </div>

            <?php // GrpTypeSch: счета на элементы (0) или детали (1) ?>
            <div class="col-auto">
                <span class="form-label small text-secondary mb-1 d-block">Счета на</span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Счета на">
                    <?php foreach ([0 => 'Элементы', 1 => 'Детали'] as $v => $t): ?>
                        <input type="radio" class="btn-check" name="typeSch" id="typeSch<?= $v ?>" value="<?= $v ?>"
                               autocomplete="off" <?= $typeSch === $v ? 'checked' : '' ?>
                               <?= $oneSch ? 'disabled' : '' ?> onchange="this.form.submit()">
                        <label class="btn btn-outline-secondary" for="typeSch<?= $v ?>"><?= $t ?></label>
                    <?php endforeach ?>
                </div>
            </div>

            <?php // AllSch: без галки — только открытые счета. Скрытое 0 + галка 1: PHP берёт последнее значение.
                   // В режиме одного счёта галка не нужна (в Access AllSch.Visible = False) ?>
            <div class="col-auto" <?= $oneSch ? 'style="display:none"' : '' ?>>
                <div class="form-check mb-1">
                    <?= Html::hiddenInput('allSch', 0) ?>
                    <input class="form-check-input" type="checkbox" name="allSch" value="1" id="chkAllSch"
                           <?= $filter->allSch ? 'checked' : '' ?>
                           onchange="this.form.submit()">
                    <label class="form-check-label small" for="chkAllSch">Все счета</label>
                </div>
            </div>

            <div class="col-auto ms-auto d-flex gap-2 flex-wrap align-items-center">
                <button type="submit" class="btn btn-sm btn-primary">Найти</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="GetIntrv"
                        title="Выбрать интервал дат" <?= $oneSch ? 'disabled' : '' ?>>Интервал</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="flt"
                        title="Фильтр по продавцу и датам" <?= $oneSch ? 'style="display:none"' : '' ?>>Фильтр ▼</button>
            </div>

            <?php // Признак применённого фильтра (Надпись78 + флажок) — только для чтения, как в Access.
                   // В той же строке, что и поля: экономит высоту ?>
            <div class="col-auto d-flex align-items-center">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="fltApplied" disabled
                           <?= $filterApplied ? 'checked' : '' ?>>
                    <label class="form-check-label small text-secondary" for="fltApplied">Применён фильтр</label>
                </div>
            </div>
        </div>
    </form>

    <?php // Отдельный блок под фильтром: работа со сроками поставки (от выбранного счёта не зависит) ?>
    <div class="card vh-srok mb-2">
        <div class="card-body d-flex align-items-center flex-wrap gap-2 py-1 px-2">
            <strong class="vh-srok-title" title="Загрузка файла поставщика и выбор резервов КОМПЭЛ">Сроки поставки</strong>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="KompelSrokBut"
                        title="Сроки поставки: загрузка файла поставщика">Сроки пост.</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="SpokKompelbtn"
                        title="Сроки КОМПЭЛ: загрузка файла и выбор резервов">Сроки КОМПЭЛ</button>
            </div>
        </div>
    </div>

    <div class="row g-2 vh-body">
        <?php // ---------------- Список счетов (xListSch) ---------------- ?>
        <div class="col-12 col-xl vh-list-col">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center gap-2 py-1 px-2">
                    <strong class="me-2" title="Щелчок — выбрать, двойной — открыть карточку">Счета</strong>
                    <span class="text-secondary small d-none d-lg-inline">Щелчок — выбрать, двойной — открыть карточку</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="usersrt"
                            title="Сортировать по пользователю">↕ Пользователь</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnShowLogPrices"
                            title="История изменений счетов списка">Ист</button>
                </div>
                <div class="table-responsive vh-modern-scroll">
                    <?php // Порядок колонок = порядок в _list.php: скрипт правит tr.cells[1] и tr.cells[16] ?>
                    <?= $this->render('_list-modern', ['rows' => $rows]) ?>
                </div>
            </div>
        </div>

        <?php // ---------------- Панель выбранного счёта ---------------- ?>
        <div class="col-12 col-xl-auto vh-side">
            <div class="card">
                <div class="card-header py-1 px-2">
                    <strong>Выбранный счёт</strong>
                </div>
                <div class="card-body py-2 px-2">

                    <?php // Сумма и остаток. Подпись «Сумма,руб./у.е.» меняет скрипт — id Надпись61 ?>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="vh-sum">
                                <div class="vh-sum-k" id="Надпись61">Сумма,руб.</div>
                                <input type="text" class="form-control form-control-sm vh-sum-in" id="SmSch" readonly>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="vh-sum">
                                <div class="vh-sum-k">Осталось</div>
                                <input type="text" class="form-control form-control-sm vh-sum-in" id="OstOPl" readonly>
                            </div>
                        </div>
                    </div>

                    <?php // «Кто забирает» (WhoPick_AfterUpdate) — сохраняется сразу ?>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="small text-secondary">Кто забирает</span>
                        <div class="btn-group btn-group-sm ms-auto" role="group" aria-label="Кто забирает">
                            <?php foreach ([0 => 'Осташов', 1 => 'Наумов'] as $v => $t): ?>
                                <input type="radio" class="btn-check" name="whoPick" id="whoPick<?= $v ?>" value="<?= $v ?>" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="whoPick<?= $v ?>"><?= $t ?></label>
                            <?php endforeach ?>
                        </div>
                    </div>

                    <?php // Действия: id те же, что в форме Access — к ним привязан vh-schet.js ?>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="AddOpl" title="Добавить оплату">＋ Оплата</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="AddOplToDo" title="Добавить строку «К оплате»">＋ К оплате</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ClsByPlat" title="Изменить состояние счёта (закрыть к оплате)">✓ Закрыть</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="Plat" title="Платежи по счёту (из 1С)">Платежи</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="MakeEmail" title="Письмо поставщику по счёту">✉ Письмо</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLtrs1C" title="Письма по счёту">Письма</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="MailFormBut" title="Переписка по счёту">Переписка</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ActBut" title="Акты">Акт</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="RepBut" title="Отчеты">Отчёт</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="GetPartSums" title="Долги: выгрузка в Excel по покупателю">Долги</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="butExel" title="Забрать товар">Тов</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="EditPrim" title="Изменить примечание">✎ Примечание</button>
                    </div>

                    <?php // Списки панели: те же id, что у ListBox Access (скрипт наполняет их по show[]).
                          // data-count — место счётчика строк на вкладке: его обновляет vh-schet.js (fill) ?>
                    <ul class="nav nav-tabs nav-fill vh-modern-tabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#paneOpl" type="button" role="tab">
                                Оплаты <span class="badge rounded-pill text-bg-secondary" data-count="lstOplPrc">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#paneTodo" type="button" role="tab">
                                К оплате <span class="badge rounded-pill text-bg-secondary" data-count="lstOplPrcToDo">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#panePlat" type="button" role="tab">
                                Платежи <span class="badge rounded-pill text-bg-secondary" data-count="lstPlat">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#paneDogs" type="button" role="tab">
                                Договоры <span class="badge rounded-pill text-bg-secondary" data-count="lstDogs">0</span>
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content border border-top-0 rounded-bottom p-2 mb-2">
                        <div class="tab-pane fade show active" id="paneOpl" role="tabpanel">
                            <table class="table table-sm mb-0"><tbody id="lstOplPrc"></tbody></table>
                            <button type="button" class="btn btn-sm btn-link text-danger px-0" id="DelOplPrc" title="Удалить выбранную оплату">− удалить оплату</button>
                        </div>
                        <div class="tab-pane fade" id="paneTodo" role="tabpanel">
                            <table class="table table-sm mb-0"><tbody id="lstOplPrcToDo"></tbody></table>
                            <button type="button" class="btn btn-sm btn-link text-danger px-0" id="DelOplToDo" title="Удалить выбранную строку «К оплате»">− удалить строку</button>
                        </div>
                        <div class="tab-pane fade" id="panePlat" role="tabpanel">
                            <table class="table table-sm mb-0"><tbody id="lstPlat"></tbody></table>
                        </div>
                        <div class="tab-pane fade" id="paneDogs" role="tabpanel">
                            <?php // lstDogsBox — скрипт делает подформу видимой (SetRS4DogScet); до выбора счёта её нет ?>
                            <div class="vh-modern-dogs" id="lstDogsBox" style="display:none"><table id="lstDogs"></table></div>
                        </div>
                    </div>

                    <?php // Примечание и комментарий: двойной щелчок по комментарию — правка (frmShowPrim 83) ?>
                    <div class="mb-1">
                        <div class="small text-secondary mb-1">Примечание</div>
                        <textarea class="form-control form-control-sm" id="txtPrim" rows="1" readonly></textarea>
                    </div>
                    <div class="mb-1">
                        <div class="small text-secondary mb-1">Комментарий <span class="fw-normal">(двойной щелчок — изменить)</span></div>
                        <textarea class="form-control form-control-sm" id="txtComm" rows="2" readonly style="cursor:pointer"></textarea>
                    </div>

                    <?php // Последний ответ: скрипт пишет дату (LastAnswDta) и текст (LastAnsw) ?>
                    <div class="mb-1">
                        <div class="small text-secondary mb-1 d-flex gap-2 align-items-center">
                            <span>Ответ</span>
                            <input type="text" class="form-control form-control-sm ms-auto w-auto" id="LastAnswDta" readonly>
                        </div>
                        <textarea class="form-control form-control-sm" id="LastAnsw" rows="1" readonly></textarea>
                    </div>

                    <?php // Путь к счёту: двойной щелчок открывает файл или прикрепляет новый, Delete — удалить ?>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small">📎</span>
                        <input type="text" class="form-control form-control-sm vh-modern-path" id="PthSch" readonly
                               title="Двойной щелчок — открыть файл счёта или выбрать новый; Delete — удалить путь">
                    </div>

                    <?php // Скрытые поля, которые читает скрипт (ListSubItems в Access) ?>
                    <input type="hidden" id="CommOpl">
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Стили вида: корпоративные голубой/белый, карточки, «липкая» шапка таблицы, компактные строки
$this->registerCss(<<<CSS
/* Палитра: те же голубые, что в оформлении входа (site.css) */
.vh-modern { --vh-blue:#40B3D8; --vh-blue-d:#2f8baa; --vh-blue-l:#eaf7fc; --vh-blue-b:#c5e7f2; --vh-ink:#1b5c72; }
/* Вид на всю ширину экрана: контейнер страницы не ограничиваем (стиль подключён только этим экраном) */
main > .container { max-width:none; padding-left:1rem; padding-right:1rem; }

/* ---------- Каркас: три закреплённые области (фильтр, таблица, панель) ----------
   Высота страницы фиксирована; каждая область прокручивается внутри себя, поэтому
   раздвигание колонок или длинный текст в панели не сдвигают соседей и не тянут страницу. */
.vh-modern { display:flex; flex-direction:column; height:calc(100vh - 96px); min-height:32rem; }
.vh-modern .vh-head { flex:0 0 auto; }
.vh-modern .vh-filter, .vh-modern .vh-srok { flex:0 0 auto; }
.vh-modern .vh-body { flex:1 1 auto; min-height:0; flex-wrap:nowrap; }
/* min-width:0 — ключ к независимости колонок: широкая таблица прокручивается внутри своей
   области, а не распирает flex-строку и не выталкивает панель вниз (раньше .row переносился) */
.vh-modern .vh-list-col, .vh-modern .vh-side { min-width:0; display:flex; }
.vh-modern .vh-list-col > .card, .vh-modern .vh-side > .card {
    flex:1 1 auto; min-height:0; display:flex; flex-direction:column; }
.vh-modern .vh-list-col .vh-modern-scroll { flex:1 1 auto; min-height:0; max-height:none; overflow:auto; }
/* Панель: прокручивается только тело карточки — шапка «Выбранный счёт» остаётся на месте */
.vh-modern .vh-side > .card > .card-body { flex:1 1 auto; min-height:0; overflow-y:auto; }

.vh-modern h1 { color:var(--vh-ink); }
.vh-modern .vh-head { border-bottom:3px solid var(--vh-blue); padding-bottom:.35rem; }
.vh-modern .vh-count { background:var(--vh-blue-l); color:var(--vh-ink); border:1px solid var(--vh-blue-b); }
/* Кнопка «?» (views/access/_help.php) — в цветах темы */
#helpQ { color:#1b5c72 !important; background:#eaf7fc !important; border:1px solid #c5e7f2 !important; }

/* Карточки и их шапки */
.vh-modern .card { border-color:var(--vh-blue-b); border-radius:.6rem; box-shadow:0 1px 3px rgba(64,179,216,.12); }
.vh-modern .card-header { background:var(--vh-blue-l); border-bottom:1px solid var(--vh-blue-b); color:var(--vh-ink); }

/* Фильтр и отдельный блок «Сроки поставки» под ним */
.vh-modern .vh-filter { background:#fff; }
.vh-modern .vh-srok { background:linear-gradient(90deg,var(--vh-blue-l),#fff 65%); }
.vh-modern .vh-srok-title { color:var(--vh-ink); }

/* Кнопки: основная — голубая, контурные — голубая рамка и заливка */
.vh-modern .btn-primary { --bs-btn-bg:var(--vh-blue); --bs-btn-border-color:var(--vh-blue-d);
    --bs-btn-hover-bg:var(--vh-blue-d); --bs-btn-hover-border-color:var(--vh-blue-d);
    --bs-btn-active-bg:var(--vh-blue-d); --bs-btn-active-border-color:var(--vh-blue-d); }
.vh-modern .btn-outline-secondary, .vh-modern .btn-outline-primary {
    --bs-btn-color:var(--vh-ink); --bs-btn-border-color:var(--vh-blue-b);
    --bs-btn-hover-color:#fff; --bs-btn-hover-bg:var(--vh-blue); --bs-btn-hover-border-color:var(--vh-blue);
    --bs-btn-active-color:#fff; --bs-btn-active-bg:var(--vh-blue-d); --bs-btn-active-border-color:var(--vh-blue-d); }

/* Суммы счёта */
.vh-modern .vh-sum { background:var(--vh-blue-l); border-radius:.5rem; padding:.3rem .5rem; }
.vh-modern .vh-sum-k { font-size:.7rem; text-transform:uppercase; letter-spacing:.03em; color:#4a7c8c; }
.vh-modern .vh-sum-in { border:0; background:transparent; padding:0; font-size:1rem; font-weight:600; }
.vh-modern .vh-sum-in:read-only { background:transparent; }
/* Валюта счёта (InUEtxt ≠ «руб.»): в Access поле красилось в зелёный, здесь — мягкая плашка */
.vh-modern .vh-sum-in.vh-sum-ue { background:#d1e7dd; color:#0f5132; border-radius:.25rem; padding:0 .25rem; }

/* Список счетов */
.vh-modern-scroll table { margin-bottom:0; font-size:.8125rem; }
.vh-modern-scroll thead th { position:sticky; top:0; z-index:2; background:var(--vh-blue-l); font-size:.7rem;
    text-transform:uppercase; letter-spacing:.03em; color:var(--vh-ink); font-weight:600; white-space:nowrap;
    border-bottom:2px solid var(--vh-blue-b); }
.vh-modern-scroll td { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; vertical-align:middle; }
.vh-modern-scroll tbody tr { cursor:pointer; }
.vh-modern-scroll tbody tr:hover > * { background:#f6fbfd; }
.vh-modern-scroll tbody tr.sel > * { box-shadow:inset 0 0 0 9999px rgba(64,179,216,.16); }

/* Вкладки панели */
.vh-modern-tabs { border-bottom-color:var(--vh-blue-b); }
.vh-modern-tabs .nav-link { font-size:.75rem; padding:.3rem .3rem; color:var(--vh-ink); white-space:nowrap; }
.vh-modern-tabs .nav-link.active { color:var(--vh-blue-d); font-weight:600;
    border-color:var(--vh-blue-b) var(--vh-blue-b) #fff; }
.vh-modern-tabs .badge { background:var(--vh-blue) !important; font-size:.65rem; }
.vh-modern .tab-content { border-color:var(--vh-blue-b); }

.vh-modern .table > :not(caption) > * > * { padding:.25rem .4rem; }
.vh-modern-dogs { overflow:auto; max-height:10rem; }
.vh-modern-dogs table { width:100%; border-collapse:collapse; font-size:.75rem; }
.vh-modern-dogs th { background:var(--vh-blue-l); font-weight:600; padding:.15rem .3rem; white-space:nowrap; }
.vh-modern-dogs td { padding:.15rem .3rem; border-bottom:1px solid #eef6f9; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.vh-modern-path { font-family:ui-monospace,Consolas,monospace; font-size:.75rem; }

/* Панель счёта: на широких экранах фиксированная ширина, остальное забирает таблица */
@media (min-width:1200px) { .vh-modern .vh-side { flex:0 0 auto; width:380px; } }

/* ---------- Окна поверх экрана: access-dialog.js, picker.js, msgbox.js ----------
   Класс vh-modern-dlg ставится скриптами, когда на странице есть .vh-modern.
   Каркас окна: закреплённая голубая шапка + прокручиваемое тело (iframe формы).
   Окна лежат в body, вне .vh-modern, поэтому палитра объявляется здесь же. */
.vh-modern-dlg { --vh-blue:#40B3D8; --vh-blue-d:#2f8baa; --vh-blue-l:#eaf7fc; --vh-blue-b:#c5e7f2; --vh-ink:#1b5c72; }
.vh-modern-dlg.picker-ov, .vh-modern-dlg.mb-ov { background:rgba(27,92,114,.30); }
.vh-modern-dlg .picker { display:flex; flex-direction:column; overflow:hidden !important; background:#fff;
    border:1px solid var(--vh-blue-b); border-radius:.6rem; box-shadow:0 16px 44px rgba(27,92,114,.30);
    max-height:calc(100vh - 16px); }
/* Окно выбора: шапка, поиск и кнопки закреплены — прокручивается только список (его высота — 287 px из picker.js) */
.vh-modern-dlg .picker-list { flex:0 1 auto; min-height:5rem; border:1px solid var(--vh-blue-b); border-radius:.3rem; }
.vh-modern-dlg .picker-title { flex:0 0 auto; background:linear-gradient(180deg,var(--vh-blue),var(--vh-blue-d));
    color:#fff; padding:.4rem .6rem; font:600 .85rem/1.3 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; }
.vh-modern-dlg .picker-title > span { padding-left:.15rem; }
.vh-modern-dlg .picker-x { background:rgba(255,255,255,.16); border:1px solid rgba(255,255,255,.5); color:#fff;
    width:22px; height:20px; border-radius:.25rem; font-size:11px; line-height:1; cursor:pointer; }
.vh-modern-dlg .picker-x:hover { background:rgba(255,255,255,.34); }
.vh-modern-dlg .picker-body { flex:1 1 auto; min-height:0; overflow:auto; background:#fff; }
.vh-modern-dlg .picker-top { background:#fff; border-bottom:1px solid var(--vh-blue-b); }
.vh-modern-dlg .picker-top input { border:1px solid var(--vh-blue-b); border-radius:.3rem; color:var(--vh-ink); }
.vh-modern-dlg .picker-top input:focus { outline:none; border-color:var(--vh-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
.vh-modern-dlg .picker-foot { background:var(--vh-blue-l); border-top:1px solid var(--vh-blue-b); }
.vh-modern-dlg .picker .a-btn { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.35rem; color:var(--vh-ink); }
.vh-modern-dlg .picker .a-btn:hover:not(:disabled) { background:var(--vh-blue); border-color:var(--vh-blue); color:#fff; }
.vh-modern-dlg .picker .a-grid th { background:var(--vh-blue-l); color:var(--vh-ink); border-color:var(--vh-blue-b); }
.vh-modern-dlg .picker .a-grid td { border-color:#e6f2f7; }
.vh-modern-dlg .picker .a-grid tr.sel td { background:var(--vh-blue) !important; color:#fff !important; }
/* Окна сообщений (msgbox.js): белая карточка, голубая шапка, кнопки-контур */
.vh-modern-dlg .mb { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.6rem; overflow:hidden;
    box-shadow:0 16px 44px rgba(27,92,114,.30); font:13px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; }
.vh-modern-dlg .mb-t { background:linear-gradient(180deg,var(--vh-blue),var(--vh-blue-d)); padding:.4rem .7rem;
    font-size:.85rem; font-weight:600; }
.vh-modern-dlg .mb-i.info, .vh-modern-dlg .mb-i.question { background:var(--vh-blue); }
.vh-modern-dlg .mb-x { color:#20414d; }
.vh-modern-dlg .mb-f button { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.35rem;
    height:26px; color:var(--vh-ink); font-size:12px; }
.vh-modern-dlg .mb-f button:hover { background:var(--vh-blue); border-color:var(--vh-blue); color:#fff; }
.vh-modern-dlg .mb-in, .vh-modern-dlg .mb-sel { border:1px solid var(--vh-blue-b); border-radius:.3rem; padding:3px 4px; }
.vh-modern-dlg .mb-in:focus, .vh-modern-dlg .mb-sel:focus { outline:none; border-color:var(--vh-blue);
    box-shadow:0 0 0 2px rgba(64,179,216,.22); }

/* Тёмная тема: не оставляем белые карточки */
[data-bs-theme="dark"] .vh-modern .card, [data-bs-theme="dark"] .vh-modern .vh-filter { background:var(--bs-body-bg); }
[data-bs-theme="dark"] .vh-modern .vh-srok { background:#10262e; }
[data-bs-theme="dark"] .vh-modern .vh-sum { background:#10262e; }
[data-bs-theme="dark"] .vh-modern-scroll thead th { background:#10262e; color:#9fd8ea; }
CSS);

// Помощники, настройки (window.VH) и скрипт поведения — общие для обоих видов экрана
echo $this->render('_scripts', ['filter' => $filter, 'filterApplied' => $filterApplied]);
echo $this->render('@app/views/access/_help');
