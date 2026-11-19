<?php
namespace app\models;

use PDO;
use PDOException;
use Yii;
use yii\base\Model;

/**
 * Вход теми же SQL-логином и паролем, что в Access.
 * Проверка простая: пробуем подключиться к SQL Server. Получилось, значит, пароль верный.
 * Блокировку после N неудачных попыток делает сам SQL Server (политика паролей логина).
 */
class LoginForm extends Model
{
    public $login;
    public $password;

    public function rules()
    {
        return [
            [['login', 'password'], 'required', 'message' => 'Заполните поле'],
            ['login', 'trim'],
            ['login', 'string', 'max' => 128],
            ['password', 'validateSqlLogin'],
        ];
    }

    public function attributeLabels()
    {
        return ['login' => 'Логин', 'password' => 'Пароль'];
    }

    /** Аналог ValidationRule: если подключиться не вышло, пишем ошибку под полем пароля. */
    public function validateSqlLogin($attribute)
    {
        if ($this->hasErrors()) {
            return;
        }
        try {
            $pdo = new PDO(Yii::$app->db->dsn, $this->login, $this->password);
            // Берём имя так, как его видит SQL Server (регистр букв, домен и т.п.)
            $this->login = $pdo->query('SELECT SUSER_SNAME()')->fetchColumn();
        } catch (PDOException $e) {
            // SQLSTATE 28000 / ошибка 18456 = неверный логин или пароль.
            // Остальное (сеть, сервер не отвечает) пишем в лог и говорим человеку честно.
            $badPassword = $e->getCode() === '28000' || (int)($e->errorInfo[1] ?? 0) === 18456;
            if (!$badPassword) {
                Yii::error('Вход ' . $this->login . ': ' . $e->getMessage(), __METHOD__);
                $this->addError($attribute, 'Нет связи с сервером базы. Попробуйте позже или сообщите в IT.');
            } else {
                $this->addError($attribute, 'Неверный логин или пароль');
            }
        }
    }

    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        $session = Yii::$app->session;
        $enc     = Yii::$app->security->encryptByKey($this->password, Yii::$app->params['sessionKey']);

        // Сначала login(): Yii меняет id сессии (защита от подмены), потом кладём данные.
        $ok = Yii::$app->user->login(new User(['login' => $this->login]));
        $session->set('sqlLogin', $this->login);
        $session->set('sqlPass', base64_encode($enc));
        return $ok;
    }
}
