<?php
/**
 * Макет отдельного окна (карточка счёта и другие формы, которые в Access открывались поверх, acDialog):
 * без меню и шапки сайта, только форма.
 * @var yii\web\View $this
 * @var string $content
 */
use yii\helpers\Html;

// окна сообщений вместо alert/confirm/prompt (web/js/msgbox.js) — до скриптов форм
$this->registerJsFile('@web/js/msgbox.js', ['position' => \yii\web\View::POS_HEAD]);
// индикатор занятости (web/js/busy.js) — карточки не подключают AppAsset
$this->registerCssFile('@web/css/busy.css');
$this->registerJsFile('@web/js/busy.js', ['position' => \yii\web\View::POS_HEAD]);
$this->beginPage();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <?= Html::csrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body style="margin:0;background:#ece9d8">
<?php $this->beginBody() ?>
<?php if (!empty(Yii::$app->params['isTest'])): ?>
    <div style="background:#c00;color:#fff;text-align:center;font:bold 12px Tahoma">ТЕСТОВАЯ КОПИЯ</div>
<?php endif ?>
<?= $content ?>
<?= $this->render('@app/views/access/_help') ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
