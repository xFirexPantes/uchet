<?php
/** Пустой макет для страницы входа: без шапки и меню. */
/** @var yii\web\View $this */
/** @var string $content */

use app\assets\AppAsset;
use yii\helpers\Html;

AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
    <style>
        body { background: #ece9d8; }                       /* цвет фона форм Access */
        .login-box { max-width: 360px; margin: 10vh auto; background: #fff; padding: 24px; border: 1px solid #aaa; }
        .login-box h1 { font-size: 22px; margin-bottom: 16px; }
    </style>
</head>
<body>
<?php $this->beginBody() ?>
<?= $content ?>
<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage() ?>
