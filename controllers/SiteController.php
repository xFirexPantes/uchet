<?php
namespace app\controllers;

use app\models\LoginForm;
use Yii;
use yii\filters\VerbFilter;

/**
 * Вход, выход, главная. Главная в пилоте = простое меню (кнопка «Закупки»),
 * frmUchet 1 в 1 делаем после пилота.
 */
class SiteController extends BaseController
{
    protected array $guestActions = ['login', 'error'];

    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            // Выход только через POST, чтобы чужая ссылка не могла разлогинить человека
            'verbs' => ['class' => VerbFilter::class, 'actions' => ['logout' => ['post']]],
        ]);
    }

    public function actions()
    {
        return ['error' => ['class' => \yii\web\ErrorAction::class]];
    }

    /**
     * Главная.
     * @doc    site/index
     * @legacy frmUchet
     */
    public function actionIndex()
    {
        // Пока проверяем, что подключились под нужным логином. Потом здесь будет меню.
        $who = Yii::$app->db->createCommand('SELECT SUSER_SNAME() AS login, CURRENT_USER AS dbUser, DB_NAME() AS db')->queryOne();
        return $this->render('index', ['who' => $who]);
    }

    /**
     * Вход по SQL-логину.
     * @doc    site/login
     * @legacy вход в Access (окно SQL-аутентификации ADP)
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }
        $this->layout = 'login';                  // без шапки и меню
        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();               // туда, куда человек шёл до входа
        }
        $model->password = '';                    // пароль обратно в форму не отдаём
        return $this->render('login', ['model' => $model]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();                // заодно уничтожает сессию вместе с паролем
        return $this->redirect(['site/login']);
    }
}
