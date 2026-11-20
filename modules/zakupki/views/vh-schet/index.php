<?php
/**
 * Входящие счета (frmStat). Раскладка 1 в 1: координаты всех элементов взяты из SaveAsText
 * (Left/Top/Width/Height в твипах, 15 твипов = 1 пиксель). Список xListSch → таблица в _list.php.
 * Кнопки, которые ещё не перенесены, стоят на своих местах неактивными.
 *
 * @var yii\web\View $this
 * @var app\modules\zakupki\models\VhSchetFilter $filter
 * @var array $rows
 * @var float|null $total
 */

use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'Входящие счета';
$oneSch = $filter->id > 0;                                                  // открыта на один счёт (OpenArgs)
$filterApplied = $filter->dateS || $filter->datePo || $filter->nsch !== '';  // как IsApplyFlt
?>
<?php foreach ($filter->getFirstErrors() as $e): ?>
    <div class="alert alert-danger py-1"><?= Html::encode($e) ?></div>
<?php endforeach ?>

<form method="get" action="<?= Url::to(['index']) ?>" class="access-form" id="frmStat">
<?= Html::hiddenInput('allSch', $filter->allSch ? 1 : 0) ?>
<?php if ($oneSch): ?><?= Html::hiddenInput('id', $filter->id) ?><?php endif ?>
<div class="a-cnt">Счетов: <?= count($rows) ?></div>
<?php // Подформа lstDogs (subfrmFObnovlen1_01): активные договоры счёта. Место и размер из SaveAsText. ?>
<div class="a-listbox a-sub" id="lstDogsBox" style="left:158px;top:18px;width:432px;height:85px;visibility:hidden"><table id="lstDogs"></table></div>
<div class="a-frame" style="left:155px;top:110px;width:172px;height:57px;"></div>
<div class="a-frame" style="left:76px;top:110px;width:79px;height:50px;"></div>
<div class="a-frame" style="left:0px;top:0px;width:155px;height:110px;"></div>
<div class="a-frame" style="left:0px;top:110px;width:76px;height:50px;"></div>
<a class="a-btn" href="<?= Url::home() ?>" title="Закрыть (на главную)" style="left:1601px;top:59px;width:38px;height:38px;">✕</a>
<button type="submit" class="a-btn" title="Поиск" style="left:115px;top:68px;width:34px;height:34px;">🔍</button>
<div class="a-lbl" id="Надпись18" style="left:340px;top:107px;width:80px;height:21px;font-size:16px;">Сумма,$</div>
<div class="a-txt" style="left:420px;top:107px;width:170px;height:20px;font-size:16px;"><input type="text" readonly value="<?= $total === null ? '' : Yii::$app->formatter->asDecimal($total, 2) ?>"></div>
<button type="submit" name="allSch" value="<?= $filter->allSch ? 0 : 1 ?>" class="a-btn <?= $filter->allSch ? 'on' : '' ?>" title="Режим показа счетов" style="left:1565px;top:123px;width:72px;height:33px;"><?= $filter->allSch ? 'Все счета' : 'Открытые счета' ?></button>
<div class="a-list" style="left:4px;top:302px;width:1636px;height:414px;"><?= $this->render('_list', ['rows' => $rows]) ?></div>
<button type="button" class="a-btn" id="Plat" disabled title="Платежи: во второй очереди" style="left:1433px;top:123px;width:64px;height:33px;">Платежи</button>
<button type="button" class="a-btn" id="butExel" disabled title="Товары (frmGetTov): во второй очереди" style="left:1391px;top:123px;width:38px;height:33px;">Тов</button>
<div class="a-lbl" id="Надпись50" style="left:174px;top:129px;width:138px;height:16px;font-size:13px;">Давальческое сырьё</div>
<input type="radio" class="a-opt" name="typeSel" value="2" <?= (int)$filter->typeSel === 2 ? 'checked' : '' ?> <?= $oneSch && 'typeSel' === 'typeSel' ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:159px;top:131px;width:15px;height:17px;">
<div class="a-lbl" id="Надпись47" style="left:173px;top:113px;width:153px;height:16px;font-size:13px;">НЕ давальческое сырьё</div>
<input type="radio" class="a-opt" name="typeSel" value="1" <?= (int)$filter->typeSel === 1 ? 'checked' : '' ?> <?= $oneSch && 'typeSel' === 'typeSel' ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:158px;top:115px;width:15px;height:17px;">
<div class="a-lbl" id="Надпись52" style="left:174px;top:144px;width:79px;height:16px;font-size:13px;">Не важно</div>
<input type="radio" class="a-opt" name="typeSel" value="3" <?= (int)$filter->typeSel === 3 ? 'checked' : '' ?> <?= $oneSch && 'typeSel' === 'typeSel' ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:159px;top:146px;width:15px;height:17px;">
<div class="a-lbl" id="Надпись27" style="left:6px;top:273px;width:106px;height:14px;color:#0000ff;">Забрать полностью</div>
<div class="a-lbl" id="Надпись28" style="left:6px;top:287px;width:106px;height:14px;color:#00ff00;">Забрать частично</div>
<div class="a-lbl" id="Надпись29" style="left:465px;top:273px;width:88px;height:14px;color:#ff0000;">Задерживаются</div>
<div class="a-lbl" id="Надпись30" style="left:465px;top:287px;width:88px;height:14px;color:#ffff00;">Проблемные</div>
<div class="a-lbl" id="Надпись31" style="left:968px;top:266px;width:112px;height:14px;color:#993366;">Не отправлено</div>
<div class="a-lbl" id="Надпись32" style="left:561px;top:287px;width:88px;height:14px;color:#ff0000;">Прошла ДП</div>
<div class="a-lbl" id="Надпись33" style="left:6px;top:159px;width:111px;height:14px;color:#ff0000;">Разница дат&gt; 2 нед.</div>
<button type="button" class="a-btn" id="MakeEmail" disabled title="Письмо поставщику: во второй очереди" style="left:1349px;top:123px;width:38px;height:33px;">✉</button>
<div class="a-lbl" id="Надпись37" style="left:709px;top:288px;width:79px;height:14px;color:#ff99cc;background:#ccffcc;">Письмо отправ.</div>
<button type="button" class="a-btn" id="GetPartSums" disabled title="Долги: во второй очереди" style="left:1300px;top:123px;width:44px;height:33px;font-size:12px;">Долги</button>
<div class="a-txt" style="left:234px;top:723px;width:974px;height:17px;font-size:13px;"><input type="text" id="PthSch" readonly></div>
<div class="a-lbl" id="Надпись111" style="left:1228px;top:0px;width:117px;height:14px;font-weight:bold;">Примечание:</div>
<div class="a-txt" style="left:1228px;top:15px;width:368px;height:38px;"><textarea id="txtPrim" readonly></textarea></div>
<button type="button" class="a-btn" id="EditPrim" disabled title="Изменить примечание: во второй очереди" style="left:1600px;top:4px;width:38px;height:38px;">✎</button>
<div class="a-listbox" style="left:624px;top:19px;width:344px;height:41px;font-size:16px;"><table id="lstOplPrc"></table></div>
<button type="button" class="a-btn" id="AddOpl" disabled title="Добавить оплату: во второй очереди" style="left:592px;top:19px;width:30px;height:30px;">+</button>
<button type="button" class="a-btn" id="DelOplPrc" disabled title="Удалить оплату: во второй очереди" style="left:592px;top:79px;width:30px;height:30px;">−</button>
<div class="a-lbl" id="Надпись51" style="left:623px;top:0px;width:217px;height:20px;font-size:13px;">Проценты/суммы оплат</div>
<button type="button" class="a-btn" id="ClsByPlat" disabled title="Закрыть к оплате: во второй очереди" style="left:592px;top:49px;width:30px;height:30px;">✓</button>
<div class="a-lbl" id="Надпись53" style="left:1075px;top:273px;width:92px;height:14px;color:#00ffff;">Отдано в оплату</div>
<div class="a-lbl" id="Надпись54" style="left:1075px;top:286px;width:92px;height:14px;color:#ffcc99;">Закр. к оплате</div>
<div class="a-lbl" id="Надпись56" style="left:789px;top:288px;width:51px;height:14px;color:#800080;background:#ccffcc;">нет пути</div>
<div class="a-lbl" id="Надпись59" style="left:642px;top:261px;width:64px;height:26px;color:#ffffff;">все пол./
закр к опл</div>
<div class="a-lbl" id="Надпись61" style="left:340px;top:127px;width:80px;height:21px;font-size:16px;">Сумма,руб.</div>
<div class="a-txt" style="left:420px;top:127px;width:170px;height:20px;font-size:16px;background:#8080c0;"><input type="text" id="SmSch" readonly></div>
<div class="a-lbl" id="Надпись63" style="left:340px;top:147px;width:80px;height:21px;font-size:16px;">Осталось</div>
<div class="a-txt" style="left:420px;top:147px;width:170px;height:20px;font-size:16px;"><input type="text" id="OstOPl" readonly></div>
<div class="a-lbl" id="Надпись65" style="left:75px;top:113px;width:76px;height:16px;font-size:12px;background:#8080c0;">Кто забирает</div>
<div class="a-lbl" id="Надпись68" style="left:93px;top:128px;width:50px;height:16px;font-size:12px;">Осташов</div>
<input type="radio" class="a-opt" name="whoPick" value="0" disabled title="Сохранение «Кто забирает» добавим следующим шагом" style="left:77px;top:130px;width:17px;height:17px;">
<div class="a-lbl" id="Надпись70" style="left:93px;top:142px;width:49px;height:16px;font-size:12px;">Наумов</div>
<input type="radio" class="a-opt" name="whoPick" value="1" disabled title="Сохранение «Кто забирает» добавим следующим шагом" style="left:77px;top:144px;width:17px;height:17px;">
<div class="a-txt" style="left:625px;top:133px;width:268px;height:21px;"><input type="text" id="CommOpl" readonly></div>
<button type="button" class="a-btn" id="flt" disabled title="Фильтр (frmfltSch): во второй очереди" style="left:1258px;top:123px;width:38px;height:33px;">▼</button>
<div class="a-lbl" id="Надпись7" style="left:0px;top:0px;width:151px;height:18px;font-size:15px;font-weight:bold;text-align:center;">ПОИСК</div>
<div class="a-lbl" id="Надпись78" style="left:22px;top:48px;width:95px;height:16px;">Применён фильтр</div>
<input type="checkbox" class="a-opt" disabled <?= $filterApplied ? 'checked' : '' ?> style="left:7px;top:50px;width:17px;height:17px;">
<div class="a-lbl" id="Надпись3" style="left:5px;top:21px;width:64px;height:23px;font-size:16px;">№ счета</div>
<div class="a-txt" style="left:77px;top:21px;width:75px;height:23px;font-size:16px;"><input type="text" name="nsch" maxlength="18" value="<?= Html::encode($filter->nsch) ?>" <?= $oneSch ? 'disabled' : '' ?>></div>
<div class="a-lbl" id="Надпись85" style="left:257px;top:4px;width:59px;height:14px;background:#99ccff;">акт.догов.</div>
<div class="a-lbl" id="Надпись86" style="left:643px;top:288px;width:63px;height:14px;color:#00ff00;">ФЗ-275</div>
<div class="a-lbl" id="Надпись89" style="left:0px;top:113px;width:68px;height:16px;font-size:12px;background:#8080c0;">Счета на</div>
<div class="a-lbl" id="Надпись91" style="left:18px;top:128px;width:54px;height:16px;">Элементы</div>
<input type="radio" class="a-opt" name="typeSch" value="0" <?= (int)$filter->typeSch === 0 ? 'checked' : '' ?> <?= $oneSch && 'typeSch' === 'typeSel' ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:2px;top:130px;width:17px;height:17px;">
<div class="a-lbl" id="Надпись93" style="left:18px;top:142px;width:41px;height:16px;">Детали</div>
<input type="radio" class="a-opt" name="typeSch" value="1" <?= (int)$filter->typeSch === 1 ? 'checked' : '' ?> <?= $oneSch && 'typeSch' === 'typeSel' ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:2px;top:144px;width:17px;height:17px;">
<div class="a-txt" style="left:1462px;top:180px;width:68px;height:17px;font-size:12px;"><input type="date" name="dateS" value="<?= Html::encode($filter->dateS) ?>" <?= $oneSch ? 'disabled' : '' ?>></div>
<div class="a-lbl" id="Надпись5" style="left:1534px;top:180px;width:27px;height:17px;font-size:13px;font-weight:bold;">по</div>
<div class="a-txt" style="left:1568px;top:180px;width:68px;height:17px;font-size:12px;"><input type="date" name="datePo" value="<?= Html::encode($filter->datePo) ?>" <?= $oneSch ? 'disabled' : '' ?>></div>
<button type="button" class="a-btn" onclick="document.querySelector('[name=dateS]').showPicker?.()" style="left:1462px;top:200px;width:175px;height:23px;font-size:13px;font-weight:bold;">Интервал</button>
<div class="a-lbl" id="Cpt" style="left:1451px;top:159px;width:187px;height:17px;font-size:13px;font-weight:bold;text-align:center;">Счета за интервал дат</div>
<button type="button" class="a-btn" id="MailFormBut" disabled title="Переписка: во второй очереди" style="left:1453px;top:250px;width:70px;height:21px;">Переписка</button>
<button type="button" class="a-btn" id="ActBut" disabled title="Акт: во второй очереди" style="left:1540px;top:250px;width:41px;height:21px;">Акт</button>
<button type="button" class="a-btn" id="RepBut" disabled title="Отчет: во второй очереди" style="left:1592px;top:250px;width:47px;height:21px;">Отчет</button>
<div class="a-lbl" id="Надпись98" style="left:1228px;top:57px;width:72px;height:14px;font-weight:bold;">Ответ:</div>
<div class="a-txt" style="left:1228px;top:72px;width:368px;height:49px;"><textarea id="LastAnsw" readonly></textarea></div>
<div class="a-txt" style="left:1300px;top:57px;width:71px;height:14px;text-align:right;"><input type="text" id="LastAnswDta" readonly></div>
<button type="button" class="a-btn" id="KompelSrokBut" disabled title="Сроки поставки: во второй очереди" style="left:3px;top:66px;width:54px;height:37px;">Сроки поставки</button>
<div class="a-listbox" style="left:624px;top:171px;width:548px;height:89px;font-size:13px;"><table id="lstOplPrcToDo"></table></div>
<div class="a-lbl" id="Надпись105" style="left:1445px;top:181px;width:16px;height:17px;font-size:13px;font-weight:bold;">с</div>
<div class="a-lbl" id="Надпись106" style="left:624px;top:153px;width:223px;height:20px;font-size:13px;">К оплате:</div>
<button type="button" class="a-btn" id="AddOplToDo" disabled title="Добавить к оплате: во второй очереди" style="left:592px;top:172px;width:30px;height:30px;">+</button>
<button type="button" class="a-btn" id="DelOplToDo" disabled title="Удалить из «к оплате»: во второй очереди" style="left:592px;top:231px;width:30px;height:30px;">−</button>
<div class="a-lbl" id="Надпись110" style="left:971px;top:0px;width:113px;height:14px;font-weight:bold;">Комментарий:</div>
<div class="a-txt" style="left:971px;top:19px;width:239px;height:91px;"><textarea id="txtComm" readonly></textarea></div>
<button type="button" class="a-btn" id="btnShowLogPrices" disabled title="История цен: во второй очереди" style="left:1212px;top:123px;width:38px;height:33px;font-weight:bold;">Ист</button>
<button type="button" class="a-btn" id="btnLtrs1C" disabled title="Письма: во второй очереди" style="left:1498px;top:123px;width:64px;height:33px;">Письма</button>
<div class="a-lbl" id="Надпись114" style="left:624px;top:60px;width:63px;height:16px;font-size:13px;">Платежи:</div>
<div class="a-listbox" style="left:627px;top:79px;width:340px;height:52px;"><table id="lstPlat"></table></div>
<button type="button" class="a-btn" id="SpokKompelbtn" disabled title="Сроки КОМПЭЛ: во второй очереди" style="left:59px;top:66px;width:54px;height:37px;">Сроки КОМПЭЛ</button>
<button type="button" class="a-btn" id="usersrt" title="Сортировать по пользователю" style="left:1004px;top:278px;width:30px;height:25px;">↕</button>

</form>

<?php
$this->registerCss(<<<CSS
.access-form { position:relative; width:1641px; height:740px; background:#8080c0; font:11px Tahoma, sans-serif; margin:0 auto; }
.access-form > * { position:absolute; box-sizing:border-box; }
.a-lbl { white-space:pre-line; line-height:1.1; overflow:hidden; }
.a-frame { border:1px solid #fff; border-right-color:#555; border-bottom-color:#555; }
.a-txt input, .a-txt textarea { width:100%; height:100%; border:1px inset #999; background:#fff; font:inherit; color:inherit; padding:0 2px; resize:none; }
.a-txt input[readonly], .a-txt textarea[readonly] { background:#f4f4f4; }
.a-btn { background:#ece9d8; border:1px outset #ccc; font:inherit; padding:0; display:flex; align-items:center; justify-content:center; color:#000; text-decoration:none; cursor:pointer; }
.a-btn:disabled { color:#999; cursor:default; }
.a-btn.on { border-style:inset; background:#dcd8c4; }
.a-opt { margin:0; }
.a-txt input[type=date] { padding:0; font-size:11px; }
.a-txt input[type=date]::-webkit-calendar-picker-indicator { display:none; }
.a-cnt { right:4px; bottom:2px; color:#fff; }
.a-list, .a-listbox { overflow:auto; background:#fff; border:1px inset #999; }
.vh-table { border-collapse:collapse; font-size:11px; table-layout:fixed; }
.vh-table th { position:sticky; top:0; background:#ece9d8; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; padding:1px 4px; border:1px solid #bbb; font-weight:normal; }
.vh-table th.asc::after { content:' ▲'; } .vh-table th.desc::after { content:' ▼'; }
.vh-table td { padding:0 4px; border-bottom:1px solid #eee; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.vh-table tr.sel td { background:#316ac5; color:#fff !important; }
.a-listbox table { width:100%; border-collapse:collapse; }
.a-listbox td { padding:0 3px; white-space:nowrap; }
.a-listbox tr.sel td { background:#316ac5; color:#fff; }
.a-sub td { overflow:hidden; text-overflow:ellipsis; border-right:1px solid #ddd; }
.a-sub th { background:#ece9d8; font-weight:normal; padding:0 3px; position:sticky; top:0; white-space:nowrap; }
CSS);

// Общий помощник «раздвигать столбцы» для всех таблиц проекта
$this->registerJsFile('@web/js/colresize.js');
$this->registerCssFile('@web/css/colresize.css');

$panelUrl = Json::encode(Url::to(['panel']));
$schetUrl = Json::encode(Url::to(['schet/view']));
$typeSch  = (int)$filter->typeSch;
$this->registerJs(<<<JS
const tbl = document.getElementById('vhList'), body = tbl.tBodies[0];
const byId = id => document.getElementById(id);
// Видимые колонки списков: из ColumnWidths в Access (ширина 0 = скрытая колонка)
const show = { lstOplPrc: [0, 1, 3], lstOplPrcToDo: [1, 2, 3, 4, 5], lstPlat: [1, 2, 3, 5] };

// --- Сортировка по заголовку (xListSch_ColumnClick). Даты сортируются по скрытым Lng-полям.
function sortBy(col, desc) {
    const rows = [...body.rows];
    const num = rows.every(r => r.cells[col].dataset.sort === '' || !isNaN(r.cells[col].dataset.sort));
    rows.sort((a, b) => {
        const x = a.cells[col].dataset.sort, y = b.cells[col].dataset.sort;
        const d = num ? (Number(x) - Number(y)) : x.localeCompare(y, 'ru');
        return desc ? -d : d;
    });
    rows.forEach(r => body.appendChild(r));
    tbl.querySelectorAll('th').forEach(th => th.classList.remove('asc', 'desc'));
    tbl.tHead.rows[0].cells[col].classList.add(desc ? 'desc' : 'asc');
}
tbl.tHead.addEventListener('click', e => {
    if (e.target.classList.contains('rsz') || tbl.dataset.resizing) return;
    const th = e.target.closest('th'); if (th) sortBy(+th.dataset.col, th.classList.contains('asc'));
});

// --- Раздвигание столбцов (общий web/js/colresize.js)
colResize(tbl, 'vhSchet.list');
// usersrt: сортировка по пользователю (FillListSch(1): SortKey = 12, по возрастанию)
byId('usersrt')?.addEventListener('click', () => sortBy(10, false));

// --- Списки панели (RowSource у ListBox)
function fill(id, rows) {
    const t = byId(id); if (!t) return; t.innerHTML = '';
    rows.forEach(r => {
        const tr = t.insertRow(); tr.dataset.row = JSON.stringify(r);
        show[id].forEach(i => tr.insertCell().textContent = r[i] ?? '');
    });
}
// lstOplPrc_AfterUpdate: CommOpl = скрытая колонка 4 выбранной оплаты
byId('lstOplPrc')?.addEventListener('click', e => {
    const tr = e.target.closest('tr'); if (!tr) return;
    byId('lstOplPrc').querySelectorAll('tr.sel').forEach(r => r.classList.remove('sel')); tr.classList.add('sel');
    byId('CommOpl').value = JSON.parse(tr.dataset.row)[4] ?? '';
});

// --- Подформа договоров lstDogs (subfrmFObnovlen1_01, таблица). Колонки и ширины из SaveAsText
// (ColumnWidth в твипах / 15). IspID, UchetID, IsActive скрыты, как ColumnHidden в Access.
// Условное форматирование: у активного договора (IsActive = True) «Дата дог» на голубом фоне.
const dogCols = [['NumDog', '№ дог', 90], ['DataDog', 'Дата дог', 59], ['SrokWar', 'Срок гарантии,мес.', 82], ['Org', 'Организация', 164], ['DescrDog', 'Описание', 110]];
function fillDogs(rows) {
    const t = byId('lstDogs');
    if (!t.tHead) {                                   // шапка и ширины один раз, потом меняем только строки
        const cg = document.createElement('colgroup');
        dogCols.forEach(c => { const col = document.createElement('col'); col.style.width = c[2] + 'px'; cg.appendChild(col); });
        t.appendChild(cg);
        const h = t.createTHead().insertRow();
        dogCols.forEach(c => { const th = document.createElement('th'); th.textContent = c[1]; h.appendChild(th); });
        colResize(t, 'vhSchet.dogs');
    }
    if (t.tBodies[0]) t.tBodies[0].remove();
    const b = t.createTBody();
    rows.forEach(r => {
        const tr = b.insertRow();
        dogCols.forEach(c => {
            const td = tr.insertCell(); td.textContent = r[c[0]] ?? '';
            if (c[0] === 'DataDog' && (r.IsActive === true || r.IsActive == 1)) td.style.background = '#99ccff';
        });
    });
    vis('lstDogsBox', true);   // в Access SetRS4DogScet всегда делает подформу видимой
}

// --- Выбор строки (xListSch_ItemClick + SetRowSOplPrc)
const set = (id, v) => { const el = byId(id); if (el) el.value = v ?? ''; };
const vis = (id, on) => { const el = byId(id); if (el) el.style.visibility = on ? 'visible' : 'hidden'; };
function select(tr) {
    body.querySelectorAll('tr.sel').forEach(r => r.classList.remove('sel'));
    tr.classList.add('sel');
    const d = JSON.parse(tr.dataset.row);
    set('PthSch', d.pth); set('txtPrim', d.Prim); set('OstOPl', d.OstOPl); set('SmSch', d.SmSch); set('txtComm', d.Comm);
    set('CommOpl', '');
    byId('Надпись61').textContent = 'Сумма,' + (d.InUEtxt ?? '');
    // По письму Семёнова от 14.01.22: рубли обычным фоном, валюта зелёным жирным
    const sm = byId('SmSch'); if (sm) { const rub = d.InUEtxt === 'руб.'; sm.style.background = rub ? '#8080c0' : '#ccffcc'; sm.style.fontWeight = rub ? 'normal' : 'bold'; }
    document.querySelectorAll('[name=whoPick]').forEach(r => r.checked = String(r.value) === String(d.WhoPick));
    vis('ActBut', d.Acts === 'Да'); vis('RepBut', d.Reps === 'Да');
    fetch($panelUrl + '?id=' + tr.dataset.id + '&typeSch=$typeSch')
        .then(r => r.json())
        .then(p => {
            fill('lstOplPrc', p.opl); fill('lstOplPrcToDo', p.oplToDo); fill('lstPlat', p.plat);
            set('LastAnswDta', p.answ.dta); set('LastAnsw', p.answ.txt);
            fillDogs(p.dogs);
        });
}
body.addEventListener('click', e => { const tr = e.target.closest('tr'); if (tr) select(tr); });

// --- Двойной щелчок → карточка счёта (xListSch_DblClick): ID > 0 frmMassInputSch, ID < 0 frmScetaDetal.
// В Access карточка открывалась поверх списка (acDialog); здесь отдельной вкладкой.
body.addEventListener('dblclick', e => {
    const tr = e.target.closest('tr'); if (!tr) return;
    window.open($schetUrl + '?id=' + tr.dataset.id, 'schet' + tr.dataset.id);
});

// --- Как в Access: сортировка по дате (LngDta) по убыванию, выбрана первая строка
sortBy(5, true);
if (body.rows.length) select(body.rows[0]); else { vis('ActBut', false); vis('RepBut', false); }

// --- Form_Timer: обновить список раз в 30 минут
setTimeout(() => location.reload(), 30 * 60 * 1000);
JS);
echo $this->render('@app/views/access/_help');
