<?php
/** @var yii\web\View $this */
/** @var app\models\LoginForm $model */

use yii\bootstrap5\ActiveForm;
use yii\bootstrap5\Html;

$this->title = 'Вход';
?>
<div class="login-box">
    <h1><?= Html::encode(Yii::$app->name) ?></h1>
    <?php if (Yii::$app->params['isTest']): ?>
        <div class="alert alert-warning py-1 text-center">ТЕСТОВАЯ КОПИЯ</div>
    <?php endif ?>

    <?php foreach (Yii::$app->session->getAllFlashes() as $type => $msg): ?>
        <div class="alert alert-<?= $type === 'error' ? 'danger' : $type ?>"><?= Html::encode($msg) ?></div>
    <?php endforeach ?>

    <p class="text-muted">Логин и пароль те же, что в Access.</p>

    <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
        <?= $form->field($model, 'login')->textInput(['autofocus' => true, 'autocomplete' => 'username']) ?>
        <?= $form->field($model, 'password')->passwordInput(['autocomplete' => 'current-password']) ?>
        <?= Html::submitButton('Войти', ['class' => 'btn btn-primary w-100']) ?>
    <?php ActiveForm::end(); ?>
</div>
