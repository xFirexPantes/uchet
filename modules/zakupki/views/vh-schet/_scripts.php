<?php
/**
 * Общее для экрана «Входящие счета»: помощники, настройки (window.VH) и скрипт поведения.
 * Подключается из index-modern.php (современный вид).
 *
 * Скрипт vh-schet.js ищет таблицу #vhList и списки панели по id (lstOplPrc, lstOplPrcToDo, lstPlat,
 * lstDogs) — современный вид обязан сохранить те же id, иначе поведение к ним не привяжется.
 *
 * @var yii\web\View $this
 * @var app\modules\zakupki\models\VhSchetFilter $filter
 * @var bool $filterApplied
 */

use yii\helpers\Json;
use yii\helpers\Url;

// Общий помощник «раздвигать столбцы» для всех таблиц проекта
$this->registerJsFile('@web/js/colresize.js');
$this->registerCssFile('@web/css/colresize.css');
// Корпоративное оформление окон ввода (interval.js рисует окно прямо в этой странице, класс .dlg-modern)
$this->registerCssFile('@web/css/dlg-modern.css');

// Окна и помощники, которыми пользуется скрипт экрана
$this->registerJsFile('@web/js/interval.js');
$this->registerJsFile('@web/js/picker.js');
$this->registerJsFile('@web/js/access-dialog.js');
$this->registerJsFile('@web/js/msgbox.js');

// Настройки экрана: адреса действий, CSRF-токен, тип счёта, признак применённого фильтра
$this->registerJs('window.VH = ' . Json::encode([
    'panelUrl'  => Url::to(['panel']),
    'actUrl'    => Url::to(['act']),
    'primUrl'   => Url::to(['prim']),
    'getPrcUrl' => Url::to(['get-prc']),
    'whoBuyUrl' => Url::to(['who-buy']),
    'partUrl'   => Url::to(['part-sums']),
    'pthUrl'    => Url::to(['pth-file']),
    'fltUrl'    => Url::to(['vh-dlg/flt']),
    'chgUrl'    => Url::to(['vh-dlg/chg-hist']),
    'mailUrl'   => Url::to(['vh-dlg/mail-txt']),
    'postUrl'   => Url::to(['vh-dlg/sch-post']),
    'srokUrl'   => Url::to(['vh-dlg/srok']),
    'docsUrl'   => Url::to(['vh-dlg/docs']),
    'platUrl'   => Url::to(['vh-dlg/plat']),
    'tovUrl'    => Url::to(['vh-dlg/get-tov']),
    'actsUrl'   => Url::to(['vh-dlg/acts']),
    'dlgActUrl' => Url::to(['vh-dlg/act']),
    'schetUrl'  => Url::to(['schet/view']),
    'csrfP'     => Yii::$app->request->csrfParam,
    'csrfT'     => Yii::$app->request->csrfToken,
    'typeSch'   => (int)$filter->typeSch,
    'fltOn'     => (bool)$filterApplied,
]) . ';', \yii\web\View::POS_BEGIN);

// Поведение экрана — в конце страницы, когда разметка уже есть
$this->registerJsFile('@web/js/vh-schet.js', ['position' => \yii\web\View::POS_END]);

// Признак включённого режима «только неоплаченные» (щелчок по заголовку «Оплата», RemoveDataSch)
$this->registerCss('th.neopl { text-decoration:underline; } th.neopl::after { content:" ▾"; }');
