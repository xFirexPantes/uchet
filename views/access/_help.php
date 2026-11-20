<?php
/**
 * Кнопка «?» в правом верхнем углу окна и клавиша F1: справка по текущему окну (HelpController, docs/<маршрут>.md).
 * Подключается в макетах card и dialog; в обычные страницы — $this->render('@app/views/access/_help').
 * В макете dialog кнопка не рисуется ($button = false): «?» стоит в заголовке диалога (web/js/access-dialog.js берёт window.helpUrl).
 * @var yii\web\View $this
 * @var bool $button
 */
use yii\helpers\Html;
use yii\helpers\Url;

$route = Yii::$app->controller->route;
$url = Url::to(['/help/view', 'p' => $route]);
$button = $button ?? true;
?>
<?php if ($button): ?>
<a href="<?= Html::encode($url) ?>" target="help" id="helpQ" title="Справка (F1)"
   style="position:fixed;top:2px;right:2px;z-index:900;width:16px;height:16px;line-height:15px;text-align:center;font:bold 11px Tahoma;
          color:#0a246a;background:#ece9d8;border:1px outset #ccc;border-radius:50%;text-decoration:none;opacity:.75">?</a>
<?php endif ?>
<script>
window.helpUrl = <?= json_encode($url) ?>;
document.addEventListener('keydown', function (e) {
    if (e.key !== 'F1') return;
    e.preventDefault();
    window.open(<?= json_encode($url) ?>, 'help', 'width=860,height=700,scrollbars=yes');
});
document.getElementById('helpQ')?.addEventListener('click', function (e) {
    e.preventDefault();
    window.open(this.href, 'help', 'width=860,height=700,scrollbars=yes');
});
</script>
