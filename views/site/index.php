<?php
/** @var yii\web\View $this */
/** @var array $who */

use yii\helpers\Html;

$this->title = 'Учёт';
?>
<h1>Учёт</h1>
<p>
    Вы вошли как <b><?= Html::encode($who['login']) ?></b>
    (пользователь базы <?= Html::encode($who['dbUser']) ?>, база <?= Html::encode($who['db']) ?>).
</p>
<p class="text-muted">Здесь будет меню. Первая кнопка: «Закупки».</p>
<p>EditIzgEl: <?= Yii::$app->user->can('EditIzgEl') ? 'да' : 'нет' ?>, форма frmStat: <?= Yii::$app->user->can('form:frmStat') ? 'да' : 'нет' ?></p>