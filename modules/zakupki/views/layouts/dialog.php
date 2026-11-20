<?php
/**
 * Макет формы-диалога внутри iframe (web/js/access-dialog.js): только форма, без плашки и шапки.
 * @var yii\web\View $this
 * @var string $content
 */
use yii\helpers\Html;

// окна сообщений вместо alert/confirm/prompt (web/js/msgbox.js) — до скриптов форм
$this->registerJsFile('@web/js/msgbox.js', ['position' => \yii\web\View::POS_HEAD]);
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
<body style="margin:0;background:#ece9d8;overflow:hidden">
<?php $this->beginBody() ?>
<?= $content ?>
<?= $this->render('@app/views/access/_help', ['button' => false]) ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
