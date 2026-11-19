<?php
/**
 * Главная пилота: две кнопки раздела закупок. Полная главная frmUchet 1 в 1 будет после пилота.
 * Кнопка видна, только если есть право на форму, как Me!btn.Visible = GetPermission(...) в Access.
 *
 * @var yii\web\View $this
 * @var array $who
 */

use yii\helpers\Html;

$this->title = 'Учёт';
$user = Yii::$app->user;
?>
<style>
    .menu-pilot { display:flex; gap:16px; margin-top:24px; }
    .menu-pilot a, .menu-pilot span { display:block; width:220px; padding:18px; text-align:center; font-size:16px;
        border:1px solid #888; background:#ece9d8; color:#000; text-decoration:none; }
    .menu-pilot a:hover { background:#dcd8c4; }
    .menu-pilot span { color:#999; }
</style>

<p class="text-muted"><?= Html::encode($who['login']) ?>, база <?= Html::encode($who['db']) ?></p>

<div class="menu-pilot">
    <?php if ($user->can('form:frmStat')): ?>
        <?= Html::a('Входящие счета', ['/zakupki/vh-schet/index']) ?>
    <?php endif ?>

    <?php // Статистика: имя формы Access и маршрут поставим, когда дойдём до неё ?>
    <span title="Будет во второй очереди">Статистика</span>
</div>
