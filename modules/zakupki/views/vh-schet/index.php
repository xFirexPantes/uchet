<?php
/**
 * Входящие счета (frmStat). Раскладка 1 в 1: координаты всех элементов взяты из SaveAsText
 * (Left/Top/Width/Height в твипах, 15 твипов = 1 пиксель). Список xListSch → таблица в _list.php.
 * Кнопки, которые ещё не перенесены, стоят на своих местах неактивными.
 *
 * Поведение экрана — web/js/vh-schet.js; здесь только разметка и настройки (window.VH).
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
$filterApplied = $filter->dateS || $filter->datePo || $filter->nsch !== '' || (string)$filter->firma !== '';  // как IsApplyFlt
?>
<?php foreach ($filter->getFirstErrors() as $e): ?>
    <div class="alert alert-danger py-1"><?= Html::encode($e) ?></div>
<?php endforeach ?>

<form method="get" action="<?= Url::to(['index']) ?>" class="access-form" id="frmStat">
<?= Html::hiddenInput('allSch', $filter->allSch ? 1 : 0) ?>
<?= Html::hiddenInput('firma', $filter->firma, ['id' => 'cboFirma']) ?>
<?php if ($oneSch): ?>
    <?= Html::hiddenInput('id', $filter->id) ?>
    <?php // В режиме одного счёта переключатели заблокированы, а disabled-поля в GET не попадают.
          // typeSch нужен процедуре xp_GetAllSch — передаём его скрытым полем ?>
    <?= Html::hiddenInput('typeSch', (int)$filter->typeSch) ?>
<?php endif ?>
<div class="a-cnt">Счетов: <?= count($rows) ?></div>
<?php // Переключение вида: кнопка отправляет форму с текущими фильтрами, вид запоминается в сессии ?>
<button type="submit" name="view" value="modern" class="a-btn" title="Современный вид экрана" style="left:1320px;top:714px;width:225px;height:22px;font-size:11px;">⇄ Современный вид</button>
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
<button type="submit" name="allSch" value="<?= $filter->allSch ? 0 : 1 ?>" class="a-btn <?= $filter->allSch ? 'on' : '' ?>" title="Режим показа счетов" style="left:1565px;top:123px;width:72px;<?= $oneSch ? 'display:none;' : '' ?>height:33px;"><?= $filter->allSch ? 'Все счета' : 'Открытые счета' ?></button>
<div class="a-list" style="left:4px;top:302px;width:1636px;height:414px;"><?= $this->render('_list', ['rows' => $rows]) ?></div>
<button type="button" class="a-btn" id="Plat" title="Платежи по счёту (из 1С)" style="left:1433px;top:123px;width:64px;height:33px;">Платежи</button>
<button type="button" class="a-btn" id="butExel" title="Забрать товар" style="left:1391px;top:123px;width:38px;height:33px;">Тов</button>
<div class="a-lbl" id="Надпись50" style="left:174px;top:129px;width:138px;height:16px;font-size:13px;">Давальческое сырьё</div>
<input type="radio" class="a-opt" name="typeSel" value="2" <?= (int)$filter->typeSel === 2 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:159px;top:131px;width:15px;height:17px;">
<div class="a-lbl" id="Надпись47" style="left:173px;top:113px;width:153px;height:16px;font-size:13px;">НЕ давальческое сырьё</div>
<input type="radio" class="a-opt" name="typeSel" value="1" <?= (int)$filter->typeSel === 1 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:158px;top:115px;width:15px;height:17px;">
<div class="a-lbl" id="Надпись52" style="left:174px;top:144px;width:79px;height:16px;font-size:13px;">Не важно</div>
<input type="radio" class="a-opt" name="typeSel" value="3" <?= (int)$filter->typeSel === 3 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:159px;top:146px;width:15px;height:17px;">
<div class="a-lbl" id="Надпись27" style="left:6px;top:273px;width:106px;height:14px;color:#0000ff;">Забрать полностью</div>
<div class="a-lbl" id="Надпись28" style="left:6px;top:287px;width:106px;height:14px;color:#00ff00;">Забрать частично</div>
<div class="a-lbl" id="Надпись29" style="left:465px;top:273px;width:88px;height:14px;color:#ff0000;">Задерживаются</div>
<div class="a-lbl" id="Надпись30" style="left:465px;top:287px;width:88px;height:14px;color:#ffff00;">Проблемные</div>
<div class="a-lbl" id="Надпись31" style="left:968px;top:266px;width:112px;height:14px;color:#993366;">Не отправлено</div>
<div class="a-lbl" id="Надпись32" style="left:561px;top:287px;width:88px;height:14px;color:#ff0000;">Прошла ДП</div>
<div class="a-lbl" id="Надпись33" style="left:6px;top:159px;width:111px;height:14px;color:#ff0000;">Разница дат&gt; 2 нед.</div>
<button type="button" class="a-btn" id="MakeEmail" title="Письмо поставщику по счёту" style="left:1349px;top:123px;width:38px;height:33px;">✉</button>
<div class="a-lbl" id="Надпись37" style="left:709px;top:288px;width:79px;height:14px;color:#ff99cc;background:#ccffcc;">Письмо отправ.</div>
<button type="button" class="a-btn" id="GetPartSums" title="Долги: выгрузка в Excel по покупателю" style="left:1300px;top:123px;width:44px;height:33px;font-size:12px;">Долги</button>
<div class="a-txt" style="left:234px;top:723px;width:974px;height:17px;font-size:13px;"><input type="text" id="PthSch" readonly title="Двойной щелчок — открыть файл счёта или, если пути нет, выбрать файл (копируется на \\TSRV). Delete — удалить путь"></div>
<div class="a-lbl" id="Надпись111" style="left:1228px;top:0px;width:117px;height:14px;font-weight:bold;">Примечание:</div>
<div class="a-txt" style="left:1228px;top:15px;width:368px;height:38px;"><textarea id="txtPrim" readonly></textarea></div>
<button type="button" class="a-btn" id="EditPrim" title="Изменить примечание" style="left:1600px;top:4px;width:38px;height:38px;">✎</button>
<div class="a-listbox" style="left:624px;top:19px;width:344px;height:41px;font-size:16px;"><table id="lstOplPrc"></table></div>
<button type="button" class="a-btn" id="AddOpl" title="Добавить оплату" style="left:592px;top:19px;width:30px;height:30px;">+</button>
<button type="button" class="a-btn" id="DelOplPrc" title="Удалить выбранную оплату" style="left:592px;top:79px;width:30px;height:30px;">−</button>
<div class="a-lbl" id="Надпись51" style="left:623px;top:0px;width:217px;height:20px;font-size:13px;">Проценты/суммы оплат</div>
<button type="button" class="a-btn" id="ClsByPlat" title="Изменить состояние счёта (закрыть к оплате)" style="left:592px;top:49px;width:30px;height:30px;">✓</button>
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
<input type="radio" class="a-opt" name="whoPick" value="0" title="Кто забирает (сохраняется сразу)" style="left:77px;top:130px;width:17px;height:17px;">
<div class="a-lbl" id="Надпись70" style="left:93px;top:142px;width:49px;height:16px;font-size:12px;">Наумов</div>
<input type="radio" class="a-opt" name="whoPick" value="1" title="Кто забирает (сохраняется сразу)" style="left:77px;top:144px;width:17px;height:17px;">
<div class="a-txt" style="left:625px;top:133px;width:268px;height:21px;"><input type="text" id="CommOpl" readonly></div>
<button type="button" class="a-btn" id="flt" title="Фильтр по счетам" style="left:1258px;top:123px;width:38px;height:33px;<?= $oneSch ? 'display:none;' : '' ?>">▼</button>
<div class="a-lbl" id="Надпись7" style="left:0px;top:0px;width:151px;height:18px;font-size:15px;font-weight:bold;text-align:center;">ПОИСК</div>
<div class="a-lbl" id="Надпись78" style="left:22px;top:48px;width:95px;height:16px;">Применён фильтр</div>
<input type="checkbox" class="a-opt" disabled <?= $filterApplied ? 'checked' : '' ?> style="left:7px;top:50px;width:17px;height:17px;">
<div class="a-lbl" id="Надпись3" style="left:5px;top:21px;width:64px;height:23px;font-size:16px;">№ счета</div>
<div class="a-txt" style="left:77px;top:21px;width:75px;height:23px;font-size:16px;"><input type="text" name="nsch" maxlength="18" value="<?= Html::encode($filter->nsch) ?>" <?= $oneSch ? 'disabled' : '' ?>></div>
<div class="a-lbl" id="Надпись85" style="left:257px;top:4px;width:59px;height:14px;background:#99ccff;">акт.догов.</div>
<div class="a-lbl" id="Надпись86" style="left:643px;top:288px;width:63px;height:14px;color:#00ff00;">ФЗ-275</div>
<div class="a-lbl" id="Надпись89" style="left:0px;top:113px;width:68px;height:16px;font-size:12px;background:#8080c0;">Счета на</div>
<div class="a-lbl" id="Надпись91" style="left:18px;top:128px;width:54px;height:16px;">Элементы</div>
<input type="radio" class="a-opt" name="typeSch" value="0" <?= (int)$filter->typeSch === 0 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:2px;top:130px;width:17px;height:17px;">
<div class="a-lbl" id="Надпись93" style="left:18px;top:142px;width:41px;height:16px;">Детали</div>
<input type="radio" class="a-opt" name="typeSch" value="1" <?= (int)$filter->typeSch === 1 ? 'checked' : '' ?> <?= $oneSch ? 'disabled' : '' ?> onchange="this.form.submit()" style="left:2px;top:144px;width:17px;height:17px;">
<div class="a-txt" style="left:1462px;top:180px;width:68px;height:17px;font-size:12px;"><input type="date" name="dateS" value="<?= Html::encode($filter->dateS) ?>" <?= $oneSch ? 'disabled' : '' ?>></div>
<div class="a-lbl" id="Надпись5" style="left:1534px;top:180px;width:27px;height:17px;font-size:13px;font-weight:bold;">по</div>
<div class="a-txt" style="left:1568px;top:180px;width:68px;height:17px;font-size:12px;"><input type="date" name="datePo" value="<?= Html::encode($filter->datePo) ?>" <?= $oneSch ? 'disabled' : '' ?>></div>
<button type="button" class="a-btn" id="GetIntrv" <?= $oneSch ? 'disabled' : '' ?> title="Выбрать интервал дат" style="left:1462px;top:200px;width:175px;height:23px;font-size:13px;font-weight:bold;">Интервал</button>
<div class="a-lbl" id="Cpt" style="left:1451px;top:159px;width:187px;height:17px;font-size:13px;font-weight:bold;text-align:center;">Счета за интервал дат</div>
<button type="button" class="a-btn" id="MailFormBut" title="Переписка по счёту" style="left:1453px;top:250px;width:70px;height:21px;">Переписка</button>
<button type="button" class="a-btn" id="ActBut" title="Акты" style="left:1540px;top:250px;width:41px;height:21px;">Акт</button>
<button type="button" class="a-btn" id="RepBut" title="Отчеты" style="left:1592px;top:250px;width:47px;height:21px;">Отчет</button>
<div class="a-lbl" id="Надпись98" style="left:1228px;top:57px;width:72px;height:14px;font-weight:bold;">Ответ:</div>
<div class="a-txt" style="left:1228px;top:72px;width:368px;height:49px;"><textarea id="LastAnsw" readonly></textarea></div>
<div class="a-txt" style="left:1300px;top:57px;width:71px;height:14px;text-align:right;"><input type="text" id="LastAnswDta" readonly></div>
<button type="button" class="a-btn" id="KompelSrokBut" title="Сроки поставки: загрузка файла поставщика" style="left:3px;top:66px;width:54px;height:37px;">Сроки поставки</button>
<div class="a-listbox" style="left:624px;top:171px;width:548px;height:89px;font-size:13px;"><table id="lstOplPrcToDo"></table></div>
<div class="a-lbl" id="Надпись105" style="left:1445px;top:181px;width:16px;height:17px;font-size:13px;font-weight:bold;">с</div>
<div class="a-lbl" id="Надпись106" style="left:624px;top:153px;width:223px;height:20px;font-size:13px;">К оплате:</div>
<button type="button" class="a-btn" id="AddOplToDo" title="Добавить строку «К оплате»" style="left:592px;top:172px;width:30px;height:30px;">+</button>
<button type="button" class="a-btn" id="DelOplToDo" title="Удалить выбранную строку «К оплате»" style="left:592px;top:231px;width:30px;height:30px;">−</button>
<div class="a-lbl" id="Надпись110" style="left:971px;top:0px;width:113px;height:14px;font-weight:bold;">Комментарий:</div>
<div class="a-txt" style="left:971px;top:19px;width:239px;height:91px;"><textarea id="txtComm" readonly title="Двойной щелчок — изменить комментарий" style="cursor:pointer"></textarea></div>
<button type="button" class="a-btn" id="btnShowLogPrices" title="История изменений счетов списка" style="left:1212px;top:123px;width:38px;height:33px;font-weight:bold;">Ист</button>
<button type="button" class="a-btn" id="btnLtrs1C" title="Письма по счёту" style="left:1498px;top:123px;width:64px;height:33px;">Письма</button>
<div class="a-lbl" id="Надпись114" style="left:624px;top:60px;width:63px;height:16px;font-size:13px;">Платежи:</div>
<div class="a-listbox" style="left:627px;top:79px;width:340px;height:52px;"><table id="lstPlat"></table></div>
<button type="button" class="a-btn" id="SpokKompelbtn" title="Сроки КОМПЭЛ: загрузка файла и выбор резервов" style="left:59px;top:66px;width:54px;height:37px;">Сроки КОМПЭЛ</button>
<button type="button" class="a-btn" id="usersrt" title="Сортировать по пользователю" style="left:1004px;top:278px;width:30px;height:25px;">↕</button>

</form>

<?php
$this->registerCss(<<<CSS
.picker-ov { position:fixed; inset:0; background:rgba(0,0,0,.25); z-index:1000; display:flex; align-items:center; justify-content:center; }
.picker { background:#ece9d8; border:1px solid #0a246a; box-shadow:2px 2px 8px rgba(0,0,0,.4); font:11px Tahoma, sans-serif; }
.picker-title { background:#0a246a; color:#fff; padding:3px 4px; display:flex; justify-content:space-between; align-items:center; font-weight:bold; }
.picker-x { background:#ece9d8; border:1px outset #ccc; font-size:10px; width:18px; height:16px; padding:0; cursor:pointer; }
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

// Помощники, настройки (window.VH) и скрипт поведения — общие для обоих видов экрана
echo $this->render('_scripts', ['filter' => $filter, 'filterApplied' => $filterApplied]);
echo $this->render('@app/views/access/_help');
