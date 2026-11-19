<?php
namespace app\controllers;

use Yii;
use yii\db\Exception as DbException;
use yii\filters\AccessControl;
use yii\web\Controller;

/**
 * Предок всех контроллеров, в том числе в модулях (modules/zakupki/controllers/...).
 * Аналог общего модуля VBA, который вызывает каждая форма.
 *
 * Что делает:
 *  1) без входа никуда не пускает (кроме страниц, перечисленных в $guestActions);
 *  2) если пароль в SQL Server сменили или логин заблокировали, выкидывает на вход,
 *     а не показывает страшную ошибку.
 *
 * SESSION_CONTEXT ставить не нужно: веб работает под логином самого человека,
 * поэтому CURRENT_USER, IS_MEMBER и триггеры аудита видят его, как в Access.
 */
class BaseController extends Controller
{
    /** Экшены, доступные без входа. В SiteController это login и error. */
    protected array $guestActions = [];

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => array_merge(
                    $this->guestActions ? [['allow' => true, 'actions' => $this->guestActions]] : [],
                    [['allow' => true, 'roles' => ['@']]]   // '@' = любой вошедший
                ),
            ],
        ];
    }

    public function beforeAction($action)
    {
        if (!Yii::$app->user->isGuest) {
            try {
                Yii::$app->db->open();
            } catch (DbException $e) {
                Yii::warning('Подключение ' . Yii::$app->user->id . ': ' . $e->getMessage(), __METHOD__);
                Yii::$app->user->logout();
                Yii::$app->session->setFlash('error', 'Сеанс завершён: не удалось подключиться к базе под вашим логином. Войдите заново.');
                $this->redirect(Yii::$app->user->loginUrl);
                return false;
            }
        }
        return parent::beforeAction($action);
    }
}
