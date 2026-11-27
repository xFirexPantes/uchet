<?php
/**
 * Входящие счета (frmStat) — современный вид: обычный HTML без координат Access.
 *
 * Разметка — панели и сетки «подпись + поле», как в карточке счёта (schet/_card-modern.php).
 * Поведение экрана — web/js/vh-schet.js; здесь только разметка и настройки (window.VH).
 * Скрипт ищет #vhList, списки панели по id (lstOplPrc, lstOplPrcToDo, lstPlat, lstDogs)
 * и форму #frmStat, поэтому все id сохранены.
 *
 * @var yii\web\View $this
 * @var app\modules\zakupki\models\VhSchetFilter $filter
 * @var array $rows
 * @var float|null $total
 */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Входящие счета';
// Контейнер макета — во всю ширину окна (класс vh-wide ставит views/layouts/main.php
// ещё на сервере: иначе список сначала рисуется узким и «разворачивается» скриптом).
$this->params['wide'] = true;
// Не показывать недорисованное: до готовности раскладки экран скрыт, виден только
// индикатор (web/js/busy.js, data-boot в views/layouts/main.php).
$this->params['boot'] = true;
$oneSch = $filter->id > 0;                                                  // открыта на один счёт (OpenArgs)
$filterApplied = $filter->dateS || $filter->datePo || $filter->nsch !== '' || (string)$filter->firma !== '';  // как IsApplyFlt
?>
<div class="vh-schet vh-modern">
    <header class="vh-head">
        <div class="vh-head-title">
            <h1>Входящие счета</h1>
            <div class="vh-sub">Счетов: <b><?= count($rows) ?></b></div>
        </div>
        <div class="vh-head-total">
            <span class="vh-lbl" id="Надпись18">Сумма,$</span>
            <input class="vh-inp vh-num" type="text" readonly value="<?= $total === null ? '' : Html::encode(Yii::$app->formatter->asDecimal($total, 2)) ?>">
        </div>
        <div class="vh-head-actions">
            <button type="button" class="vh-btn" id="flt" title="Фильтр по счетам" <?= $oneSch ? 'hidden' : '' ?>>▼ Фильтр</button>
            <button type="submit" form="frmStat" name="allSch" value="<?= $filter->allSch ? 0 : 1 ?>"
                class="vh-btn<?= $filter->allSch ? ' on' : '' ?>" title="Режим показа счетов"
                <?= $oneSch ? 'hidden' : '' ?>><?= $filter->allSch ? 'Все счета' : 'Открытые счета' ?></button>
            <button type="button" class="vh-btn" id="KompelSrokBut" title="Сроки поставки: загрузка файла поставщика">Сроки поставки</button>
            <button type="button" class="vh-btn" id="SpokKompelbtn" title="Сроки КОМПЭЛ: загрузка файла и выбор резервов">Сроки КОМПЭЛ</button>
            <a class="vh-btn" href="<?= Url::home() ?>" title="Закрыть (на главную)">✕ Закрыть</a>
        </div>
    </header>

    <?php foreach ($filter->getFirstErrors() as $e): ?>
        <div class="vh-alert"><?= Html::encode($e) ?></div>
    <?php endforeach ?>

    <form method="get" action="<?= Url::to(['index']) ?>" id="frmStat" class="vh-form">
        <?= Html::hiddenInput('allSch', $filter->allSch ? 1 : 0) ?>
        <?= Html::hiddenInput('firma', $filter->firma, ['id' => 'cboFirma']) ?>
        <?php if ($oneSch): ?>
            <?= Html::hiddenInput('id', $filter->id) ?>
            <?php // В режиме одного счёта переключатели заблокированы, а disabled-поля в GET не попадают.
                  // typeSch нужен процедуре xp_GetAllSch — передаём его скрытым полем ?>
            <?= Html::hiddenInput('typeSch', (int)$filter->typeSch) ?>
        <?php endif ?>

        <section class="vh-filters">
            <div class="vh-fgroup">
                <div class="vh-fgroup-t" id="Надпись7">Поиск</div>
                <div class="vh-frow">
                    <label class="vh-lbl" id="Надпись3" for="f-nsch">№ счета</label>
                    <input class="vh-inp" id="f-nsch" type="text" name="nsch" maxlength="18"
                        value="<?= Html::encode($filter->nsch) ?>" <?= $oneSch ? 'disabled' : '' ?>>
                    <button type="submit" class="vh-btn" title="Поиск">🔍</button>
                </div>
                <label class="vh-check">
                    <input type="checkbox" disabled <?= $filterApplied ? 'checked' : '' ?>>
                    <span id="Надпись78">Применён фильтр</span>
                </label>
            </div>

            <div class="vh-fgroup">
                <div class="vh-fgroup-t">Счета на</div>
                <label class="vh-radio"><input type="radio" name="typeSch" value="0"
                    <?= (int)$filter->typeSch === 0 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?>
                    onchange="this.form.submit()"><span id="Надпись91">Элементы</span></label>
                <label class="vh-radio"><input type="radio" name="typeSch" value="1"
                    <?= (int)$filter->typeSch === 1 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?>
                    onchange="this.form.submit()"><span id="Надпись93">Детали</span></label>
            </div>

            <div class="vh-fgroup">
                <div class="vh-fgroup-t">Давальческое сырьё</div>
                <label class="vh-radio"><input type="radio" name="typeSel" value="1"
                    <?= (int)$filter->typeSel === 1 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?>
                    onchange="this.form.submit()"><span id="Надпись47">НЕ давальческое сырьё</span></label>
                <label class="vh-radio"><input type="radio" name="typeSel" value="2"
                    <?= (int)$filter->typeSel === 2 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?>
                    onchange="this.form.submit()"><span id="Надпись50">Давальческое сырьё</span></label>
                <label class="vh-radio"><input type="radio" name="typeSel" value="3"
                    <?= (int)$filter->typeSel === 3 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?>
                    onchange="this.form.submit()"><span id="Надпись52">Не важно</span></label>
            </div>

            <div class="vh-fgroup">
                <div class="vh-fgroup-t" id="Cpt">Счета за интервал дат</div>
                <div class="vh-frow">
                    <span class="vh-lbl" id="Надпись105">с</span>
                    <input class="vh-inp" type="date" name="dateS" value="<?= Html::encode($filter->dateS) ?>" <?= $oneSch ? 'disabled' : '' ?>>
                    <span class="vh-lbl" id="Надпись5">по</span>
                    <input class="vh-inp" type="date" name="datePo" value="<?= Html::encode($filter->datePo) ?>" <?= $oneSch ? 'disabled' : '' ?>>
                    <button type="button" class="vh-btn" id="GetIntrv" <?= $oneSch ? 'disabled' : '' ?>
                        title="Выбрать интервал дат">Интервал</button>
                </div>
            </div>
        </section>

        <div class="vh-body">
            <section class="vh-panel vh-list-panel">
                <div class="vh-panel-head">
                    <span>Список счетов</span>
                    <span class="vh-tools">
                        <button type="button" class="vh-btn" id="btnHints" title="Что означают цвета в списке счетов">? Цвета</button>
                        <button type="button" class="vh-btn" id="MailFormBut" title="Переписка по счёту">Переписка</button>
                        <button type="button" class="vh-btn" id="Plat" title="Платежи по счёту (из 1С)">Платежи</button>
                        <button type="button" class="vh-btn" id="butExel" title="Забрать товар">Тов</button>
                        <button type="button" class="vh-btn" id="MakeEmail" title="Письмо поставщику по счёту">✉ Письмо</button>
                        <button type="button" class="vh-btn" id="btnLtrs1C" title="Письма по счёту">Письма</button>
                        <button type="button" class="vh-btn" id="GetPartSums" title="Долги: выгрузка в Excel по покупателю">Долги</button>
                        <button type="button" class="vh-btn" id="btnShowLogPrices" title="История изменений счетов списка">История</button>
                        <button type="button" class="vh-btn" id="usersrt" title="Сортировать по пользователю">↕ User</button>
                    </span>
                </div>
                <div class="vh-list-scroll"><?= $this->render('_list-modern', ['rows' => $rows]) ?></div>
            </section>

            <aside class="vh-side">
                <div class="vh-panel">
                    <div class="vh-panel-head">Суммы по счёту</div>
                    <div class="vh-panel-body vh-rows vh-money">
                        <span class="vh-lbl" id="Надпись61">Сумма,руб.</span>
                        <input class="vh-inp vh-num" type="text" id="SmSch" readonly>
                        <span class="vh-lbl" id="Надпись63">Осталось</span>
                        <input class="vh-inp vh-num" type="text" id="OstOPl" readonly>
                    </div>
                </div>

                <div class="vh-panel">
                    <div class="vh-panel-head" id="Надпись65">Кто забирает</div>
                    <div class="vh-panel-body vh-radios">
                        <label class="vh-radio"><input type="radio" name="whoPick" value="0"
                            title="Кто забирает (сохраняется сразу)"><span id="Надпись68">Осташов</span></label>
                        <label class="vh-radio"><input type="radio" name="whoPick" value="1"
                            title="Кто забирает (сохраняется сразу)"><span id="Надпись70">Наумов</span></label>
                    </div>
                </div>

                <?php // Вкладки панели: оплаты, «к оплате», платежи из 1С, договоры ?>
                <div class="vh-panel vh-tabs-panel">
                    <div class="vh-modern-tabs" role="tablist">
                        <button type="button" role="tab" class="vh-tab nav-link active" data-bs-target="#paneOpl">Оплаты
                            <span class="vh-count" data-count="lstOplPrc">0</span></button>
                        <button type="button" role="tab" class="vh-tab nav-link" data-bs-target="#paneToDo">К оплате
                            <span class="vh-count" data-count="lstOplPrcToDo">0</span></button>
                        <button type="button" role="tab" class="vh-tab nav-link" data-bs-target="#panePlat">Платежи
                            <span class="vh-count" data-count="lstPlat">0</span></button>
                        <button type="button" role="tab" class="vh-tab nav-link" data-bs-target="#paneDogs">Договоры
                            <span class="vh-count" data-count="lstDogs">0</span></button>
                    </div>

                    <div class="tab-pane show active" id="paneOpl">
                        <div class="vh-pane-head">
                            <span class="vh-lbl" id="Надпись51">Проценты/суммы оплат</span>
                            <span class="vh-tools">
                                <button type="button" class="vh-btn vh-btn-xs" id="AddOpl" title="Добавить оплату">+</button>
                                <button type="button" class="vh-btn vh-btn-xs" id="DelOplPrc" title="Удалить выбранную оплату">−</button>
                                <button type="button" class="vh-btn vh-btn-xs" id="ClsByPlat" title="Изменить состояние счёта (закрыть к оплате)">✓</button>
                            </span>
                        </div>
                        <div class="vh-list-box"><table id="lstOplPrc"></table></div>
                        <div class="vh-frow">
                            <span class="vh-lbl">Оплата:</span>
                            <input class="vh-inp" type="text" id="CommOpl" readonly>
                        </div>
                    </div>

                    <div class="tab-pane" id="paneToDo">
                        <div class="vh-pane-head">
                            <span class="vh-lbl" id="Надпись106">К оплате:</span>
                            <span class="vh-tools">
                                <button type="button" class="vh-btn vh-btn-xs" id="AddOplToDo" title="Добавить строку «К оплате»">+</button>
                                <button type="button" class="vh-btn vh-btn-xs" id="DelOplToDo" title="Удалить выбранную строку «К оплате»">−</button>
                            </span>
                        </div>
                        <div class="vh-list-box"><table id="lstOplPrcToDo"></table></div>
                    </div>

                    <div class="tab-pane" id="panePlat">
                        <div class="vh-pane-head"><span class="vh-lbl" id="Надпись114">Платежи:</span></div>
                        <div class="vh-list-box"><table id="lstPlat"></table></div>
                    </div>

                    <div class="tab-pane" id="paneDogs">
                        <div class="vh-pane-head"><span class="vh-lbl">Активные договоры счёта</span></div>
                        <div class="vh-list-box" id="lstDogsBox"><table id="lstDogs"></table></div>
                    </div>
                </div>

                <div class="vh-panel">
                    <div class="vh-panel-head">
                        <span id="Надпись111">Примечание</span>
                        <button type="button" class="vh-btn vh-btn-xs" id="EditPrim" title="Изменить примечание">✎</button>
                    </div>
                    <div class="vh-panel-body"><textarea class="vh-inp" id="txtPrim" rows="2" readonly></textarea></div>
                </div>

                <div class="vh-panel">
                    <div class="vh-panel-head" id="Надпись110">Комментарий</div>
                    <div class="vh-panel-body"><textarea class="vh-inp" id="txtComm" rows="3" readonly
                        title="Двойной щелчок — изменить комментарий" style="cursor:pointer"></textarea></div>
                </div>

                <div class="vh-panel">
                    <div class="vh-panel-head">
                        <span id="Надпись98">Ответ</span>
                        <input class="vh-inp vh-num vh-inp-xs" type="text" id="LastAnswDta" readonly>
                    </div>
                    <div class="vh-panel-body"><textarea class="vh-inp" id="LastAnsw" rows="3" readonly></textarea></div>
                </div>

                <div class="vh-panel">
                    <div class="vh-panel-head">Путь к счёту</div>
                    <div class="vh-panel-body">
                        <input class="vh-inp" type="text" id="PthSch" readonly
                            title="Двойной щелчок — открыть файл счёта или, если пути нет, выбрать файл (копируется на \\TSRV). Delete — удалить путь">
                    </div>
                </div>

                <div class="vh-panel">
                    <div class="vh-panel-head">Акты и отчёты</div>
                    <div class="vh-panel-body vh-tools">
                        <button type="button" class="vh-btn" id="ActBut" title="Акты">Акт</button>
                        <button type="button" class="vh-btn" id="RepBut" title="Отчеты">Отчет</button>
                    </div>
                </div>
            </aside>
        </div>
    </form>

    <?php // Подсказка: что означает цвет в каждом столбце списка (в Access это были подписи над таблицей) ?>
    <div class="vh-modal" id="vhHints">
        <div class="vh-modal-box">
            <div class="vh-modal-head">
                <span>Цвета в списке счетов</span>
                <button type="button" class="vh-modal-x" id="vhHintsX" title="Закрыть (Esc)">✕</button>
            </div>
            <div class="vh-modal-body">
                <p class="vh-hint">Цвет строки и цвет текста в столбце подсказывают состояние счёта.</p>
                <div class="vh-hints">
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Холдинг</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-red"></span><span class="vh-sw-t sw-red">Разница дат &gt; 2 нед.</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-blue"></span><span class="vh-sw-t sw-blue">Забрать полностью</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-green"></span><span class="vh-sw-t sw-green">Забрать частично</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Номер счёта</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-red"></span><span class="vh-sw-t sw-red">Задерживаются</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-yellow"></span><span class="vh-sw-t sw-yellow">Проблемные</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Дата счёта</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-red"></span><span class="vh-sw-t sw-red">Прошла ДП</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Закрыт</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-white"></span><span class="vh-sw-t">все пол. / закр к опл</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-green"></span><span class="vh-sw-t sw-green">ФЗ-275</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Дата</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-pink"></span><span class="vh-sw-t sw-pink">Письмо отправлено</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Время</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-purple"></span><span class="vh-sw-t sw-purple">нет пути к файлу</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Оплата</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-violet"></span><span class="vh-sw-t sw-violet">Не отправлено</span></div>
                    </div>
                    <div class="vh-hints-col">
                        <div class="vh-hints-t">Отдано</div>
                        <div class="vh-hint-row"><span class="vh-sw sw-cyan"></span><span class="vh-sw-t sw-cyan">Отдано в оплату</span></div>
                        <div class="vh-hint-row"><span class="vh-sw sw-peach"></span><span class="vh-sw-t sw-peach">Закрыт к оплате</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerCss(<<<'CSS'
.vh-schet { --vh-blue:#40B3D8; --vh-blue-d:#2f8baa; --vh-blue-l:#eaf7fc; --vh-blue-b:#c5e7f2; --vh-ink:#1b5c72;
    background:#f4fafc; padding:.4rem .7rem .6rem; box-sizing:border-box;
    font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d; }

/* Шапка: название и число счетов слева, итоговая сумма и кнопки справа */
.vh-head { display:flex; align-items:center; flex-wrap:wrap; gap:.4rem 1rem; padding:.35rem .7rem;
    margin:-.4rem -.7rem .4rem; background:#fff; border-bottom:3px solid var(--vh-blue); }
.vh-head-title h1 { margin:0; color:var(--vh-ink); font-size:1rem; font-weight:600; }
.vh-sub { font-size:.72rem; color:#5b8598; margin-top:.1rem; }
.vh-head-total { display:flex; align-items:center; gap:.4rem; margin-left:auto; }
.vh-head-actions { display:flex; align-items:center; gap:.3rem; }
.vh-num input, input.vh-num { text-align:right; }

.vh-alert { background:#fdecec; color:#a4232b; border:1px solid #f5c2c7; border-radius:.4rem;
    padding:.25rem .6rem; margin-bottom:.4rem; }

/* Фильтры: группы «подпись + поле», переносятся по ширине */
.vh-form { display:block; }
.vh-filters { display:flex; flex-wrap:wrap; gap:.4rem; align-items:stretch; margin-bottom:.4rem; }
.vh-fgroup { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.5rem; padding:.3rem .55rem .4rem;
    box-shadow:0 1px 3px rgba(64,179,216,.10); }
.vh-fgroup-t { color:var(--vh-ink); font-weight:600; font-size:.68rem; text-transform:uppercase;
    letter-spacing:.03em; margin-bottom:.25rem; }
.vh-fgroup-btns { display:flex; flex-wrap:wrap; gap:.3rem; align-items:center; }
.vh-frow { display:flex; align-items:center; flex-wrap:wrap; gap:.3rem; }
.vh-lbl { font-size:.72rem; color:#5b8598; white-space:nowrap; }
.vh-check, .vh-radio { display:inline-flex; align-items:center; gap:.3rem; font-size:.78rem;
    cursor:pointer; white-space:nowrap; margin:0 .8rem 0 0; }
.vh-check input, .vh-radio input { accent-color:var(--vh-blue); margin:0; }
.vh-check input:disabled + span { color:#9db9c4; }
.vh-radios { display:flex; flex-wrap:wrap; gap:.2rem .6rem; }

/* Окно-подсказка: что означает цвет в столбцах списка */
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
.vh-hints { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:.55rem .9rem; }
.vh-hints-col { min-width:0; }
.vh-hints-t { font-size:.68rem; font-weight:600; color:var(--vh-ink); text-transform:uppercase;
    letter-spacing:.03em; border-bottom:1px solid var(--vh-blue-b); padding-bottom:.15rem; margin-bottom:.25rem; }
.vh-hint-row { display:flex; align-items:center; gap:.4rem; font-size:.78rem; padding:.05rem 0; }
.vh-sw { flex:0 0 auto; width:14px; height:14px; border-radius:.2rem; border:1px solid rgba(27,92,114,.25); }
.vh-sw-t { color:#20414d; }
/* Цвета — как в форматировании списка (яркие цвета Access чуть приглушены, чтобы читались на белом) */
.sw-red .vh-sw, .vh-sw.sw-red { background:#ff0000; }
.sw-blue .vh-sw, .vh-sw.sw-blue { background:#0000ff; }
.sw-green .vh-sw, .vh-sw.sw-green { background:#00b300; }
.sw-yellow .vh-sw, .vh-sw.sw-yellow { background:#ffe100; }
.sw-white .vh-sw, .vh-sw.sw-white { background:#fff; }
.sw-pink .vh-sw, .vh-sw.sw-pink { background:#ff99cc; }
.sw-purple .vh-sw, .vh-sw.sw-purple { background:#800080; }
.sw-violet .vh-sw, .vh-sw.sw-violet { background:#993366; }
.sw-cyan .vh-sw, .vh-sw.sw-cyan { background:#00c8c8; }
.sw-peach .vh-sw, .vh-sw.sw-peach { background:#ffcc99; }
.vh-sw-t.sw-red { color:#c00; } .vh-sw-t.sw-blue { color:#0000cc; } .vh-sw-t.sw-green { color:#007a00; }
.vh-sw-t.sw-yellow { color:#9a7a00; } .vh-sw-t.sw-pink { color:#b34d80; } .vh-sw-t.sw-purple { color:#800080; }
.vh-sw-t.sw-violet { color:#993366; } .vh-sw-t.sw-cyan { color:#00807f; } .vh-sw-t.sw-peach { color:#a8641f; }

/* Основная часть: список слева, панель выбранного счёта справа */
.vh-body { display:grid; grid-template-columns:minmax(0,1fr) 380px; gap:.45rem; align-items:start; }
.vh-panel { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.5rem;
    box-shadow:0 1px 3px rgba(64,179,216,.12); overflow:hidden; margin-bottom:.45rem; }
.vh-panel-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem;
    padding:.22rem .6rem; background:var(--vh-blue-l); border-bottom:1px solid var(--vh-blue-b);
    color:var(--vh-ink); font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.03em; }
.vh-panel-body { padding:.35rem .55rem; }

/* Строки «подпись + поле»: подпись не переносится, поле занимает остаток строки */
.vh-rows { display:grid; grid-template-columns:auto minmax(0,1fr); gap:.2rem .5rem; align-items:center; }
.vh-rows .vh-lbl { white-space:nowrap; }
.vh-rows .vh-inp { width:100%; min-width:0; }
/* Деньги: подписи и значения прижаты вправо, числа читаются в столбик */
.vh-money .vh-lbl { text-align:right; }
.vh-money .vh-inp { text-align:right; }

/* Экран на всю ширину окна: контейнер макета расширяет класс vh-wide (views/layouts/main.php) */
#main > .container.vh-wide { max-width:100%; width:100%; padding-left:.4rem; padding-right:.4rem; }
.vh-schet { min-height:calc(100vh - 92px); }

.vh-list-panel { margin-bottom:0; }
.vh-list-scroll { overflow:auto; max-height:calc(100vh - 330px); }
.vh-side { min-width:0; }

/* Кнопки и поля — в корпоративных цветах */
.vh-btn { display:inline-flex; align-items:center; justify-content:center; gap:.25rem; min-height:24px; padding:0 .55rem;
    background:#fff; border:1px solid var(--vh-blue-b); border-radius:.3rem; color:var(--vh-ink);
    font:600 .72rem/1 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; cursor:pointer; white-space:nowrap; }
.vh-btn:hover:not(:disabled) { background:var(--vh-blue); border-color:var(--vh-blue); color:#fff; }
.vh-btn:disabled { background:#f7fbfd; border-color:#e3f0f5; color:#9db9c4; cursor:default; }
.vh-btn.on { background:var(--vh-blue-l); border-color:var(--vh-blue); }
.vh-btn-xs { min-height:20px; padding:0 .35rem; font-size:.72rem; }
.vh-inp { height:24px; padding:0 .35rem; border:1px solid var(--vh-blue-b); border-radius:.25rem;
    background:#fff; color:#20414d; font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; box-sizing:border-box; }
textarea.vh-inp { height:auto; padding:.2rem .35rem; width:100%; resize:vertical; }
.vh-inp:focus { outline:none; border-color:var(--vh-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
.vh-inp:read-only { background:#fbfdfe; }
.vh-inp-xs { height:20px; width:110px; }
input[type=date].vh-inp { padding:0 .2rem; }

/* Таблицы: заголовок закреплён, строки подсвечиваются, выбранная — голубая */
.vh-schet table { border-collapse:collapse; font-size:12px; }
.vh-schet th { position:sticky; top:0; z-index:1; background:var(--vh-blue-l); color:var(--vh-ink);
    font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.02em;
    padding:.2rem .35rem; border:1px solid var(--vh-blue-b); white-space:nowrap;
    overflow:hidden; text-overflow:ellipsis; text-align:left; cursor:pointer; }
.vh-schet th.asc::after { content:' ▲'; } .vh-schet th.desc::after { content:' ▼'; }
.vh-schet th.neopl { text-decoration:underline; }
.vh-schet td { padding:.12rem .35rem; border-bottom:1px solid #e6f2f7;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.vh-schet tbody tr:hover td { background:#f4fafc; }
.vh-schet tr.sel td { background:var(--vh-blue) !important; color:#fff !important; }
.vh-side table { width:100%; }
.vh-list-box { max-height:150px; overflow:auto; border-bottom:1px solid var(--vh-blue-b); }
.vh-list-box table { width:100%; }
.vh-pane-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem;
    padding:.25rem .55rem; }
.vh-tools { display:flex; flex-wrap:wrap; align-items:center; gap:.25rem; }

/* Вкладки панели (классы те же, что читает vh-schet.js) */
.vh-modern-tabs { display:flex; flex-wrap:wrap; gap:.2rem; padding:.2rem .35rem 0;
    background:var(--vh-blue-l); border-bottom:1px solid var(--vh-blue-b); }
.vh-schet .vh-tab { padding:.25rem .6rem; background:transparent; border:1px solid transparent;
    border-radius:.35rem .35rem 0 0; color:#5b8598; font:600 .72rem/1.2 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
    cursor:pointer; }
.vh-schet .vh-tab:hover { color:var(--vh-ink); }
.vh-schet .vh-tab.active { background:#fff; border-color:var(--vh-blue-b); border-bottom-color:#fff; color:var(--vh-ink); }
.vh-count { display:inline-block; min-width:1.2em; padding:0 .25em; margin-left:.2rem; border-radius:.6em;
    background:var(--vh-blue); color:#fff; font-size:.65rem; text-align:center; }
/* Панели вкладок: показываем только активную (Bootstrap-разметку не используем) */
.vh-schet .tab-pane { display:none; }
.vh-schet .tab-pane.show { display:block; }

/* Пустая строка списка (emptyRow в vh-schet.js) */
.vh-schet .text-secondary { color:#7d9aa7; }
.vh-schet .fst-italic { font-style:italic; }

/* Узкий экран: колонки складываются, список прокручивается вместе со страницей */
@media (max-width:1199.98px) {
    .vh-body { grid-template-columns:minmax(0,1fr); }
    .vh-list-scroll { max-height:none; }
}
CSS);

// Окно подсказки: открытие кнопкой «? Цвета», закрытие — ✕, щелчок по фону, Esc
$this->registerJs(<<<'JS'
(function () {
    const box = document.getElementById('vhHints');
    if (!box) return;
    const open = on => box.classList.toggle('on', on);
    document.getElementById('btnHints')?.addEventListener('click', () => open(true));
    document.getElementById('vhHintsX')?.addEventListener('click', () => open(false));
    box.addEventListener('click', e => { if (e.target === box) open(false); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && box.classList.contains('on') && !document.querySelector('.mb-ov, .picker-ov')) open(false);
    });
})();
JS, \yii\web\View::POS_END);

// Помощники, настройки (window.VH) и скрипт поведения — общие для экрана
echo $this->render('_scripts', ['filter' => $filter, 'filterApplied' => $filterApplied]);
echo $this->render('@app/views/access/_help');
