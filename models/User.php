<?php
namespace app\models;

use Yii;
use yii\base\BaseObject;
use yii\web\IdentityInterface;

/**
 * Пользователь = SQL-логин. Своей таблицы пользователей у веба нет, ActiveRecord не нужен.
 * id пользователя в Yii = его SQL-логин (тот же, что SUSER_SNAME() в процедурах).
 */
class User extends BaseObject implements IdentityInterface
{
    public string $login = '';

    /** Yii вызывает на каждом запросе: «кто этот человек по id из сессии». */
    public static function findIdentity($id)
    {
        // Человек «вошёл», только пока в сессии лежит его пароль (см. LoginForm).
        if ($id && Yii::$app->session->get('sqlLogin') === $id) {
            return new static(['login' => $id]);
        }
        return null;
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return null;                 // вход по токену (API) не используется
    }

    public function getId()
    {
        return $this->login;
    }

    /** Шаблонный layouts/main.php пишет «Выход (username)», отдаём ему логин. */
    public function getUsername(): string
    {
        return $this->login;
    }

    public function getAuthKey()
    {
        return null;                 // нужно только для «запомнить меня», оно выключено
    }

    public function validateAuthKey($authKey)
    {
        return false;
    }
}
