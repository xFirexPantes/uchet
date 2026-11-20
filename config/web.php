<?php
// Настройки веб-приложения. Заменяет config/web.php из шаблона yii2-app-basic.
$local  = require __DIR__ . '/local.php';
$params = require __DIR__ . '/params.php';
$params['isTest'] = $local['isTest'];
// Письма (components/LegacyMail.php). Как в Access: с не боевой базы письма идут только на тестовый адрес.
$params['mailIsProdDb'] = strcasecmp($local['database'], 'TRXSQL') === 0;
$params['mailTestTo']   = $local['mailTestTo'] ?? '';
$params['mailFrom']     = $local['smtp']['from'] ?? 'uchet@localhost';
$smtp = $local['smtp'] ?? [];

// Строка подключения одна на оба логина. LoginTimeout = 5 секунд, чтобы при
// недоступном сервере вход не висел полминуты.
$dsn = "sqlsrv:Server={$local['sqlServer']};Database={$local['database']};"
     . 'TrustServerCertificate=yes;LoginTimeout=5';

$config = [
    'id'         => 'uchet',
    'name'       => 'Учёт',
    'basePath'   => dirname(__DIR__),
    'language'   => 'ru-RU',
    'timeZone'   => 'Europe/Moscow',
    'bootstrap'  => ['log'],
    'aliases'    => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => $local['cookieValidationKey'],
        ],
        'cache' => [
            'class' => yii\caching\ApcCache::class,   // apcu уже есть в образе
            'useApcu' => true,
        ],
        'session' => [
            'timeout' => 36000,                        // 10 часов, как в uchet-php.ini
        ],
        'user' => [
            'identityClass'   => app\models\User::class,
            'enableAutoLogin' => false,                // «запомнить меня» не нужно: пароль живёт только в сессии
            'loginUrl'        => ['site/login'],
            'accessChecker'   => 'perm',               // can() спрашивает PermChecker, а не таблицы RBAC
        ],
        // Права теми же процедурами, что GetPermission в Access (components/PermChecker.php)
        'perm' => [
            'class' => app\components\PermChecker::class,
            'ttl'   => 300,                            // изменения прав в Access видны через 5 минут
        ],
        // Подключение ПОЛЬЗОВАТЕЛЯ. Логин и пароль подставляются из сессии
        // в 'on beforeRequest' ниже. Как в Access: каждый работает под своим SQL-логином.
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn'   => $dsn,
        ],
        // Технический логин: только чтение прав и консольные задачи (раздел 3 документа пилота).
        'dbSys' => [
            'class'    => yii\db\Connection::class,
            'dsn'      => $dsn,
            'username' => $local['sysLogin'],
            'password' => $local['sysPassword'],
        ],
        // Пока в local.php нет smtp.host, письма не уходят, а пишутся файлами в runtime/mail.
        'mailer' => array_filter([
            'class'            => yii\symfonymailer\Mailer::class,
            'useFileTransport' => empty($smtp['host']),
            'transport'        => empty($smtp['host']) ? null : [
                'scheme'   => $smtp['scheme'] ?? 'smtp',
                'host'     => $smtp['host'],
                'port'     => $smtp['port'] ?? 25,
                'username' => $smtp['user'] ?? '',
                'password' => $smtp['pass'] ?? '',
            ],
        ], fn($v) => $v !== null),
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets'    => [
                ['class' => yii\log\FileTarget::class, 'levels' => ['error', 'warning']],
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,                 // /site/login вместо index.php?r=site/login
            'showScriptName'  => false,
        ],
    ],
    // Перенесённые разделы: адреса /zakupki/... (modules/zakupki)
    'modules' => [
        'zakupki' => ['class' => app\modules\zakupki\Module::class],
    ],
    'params' => $params,

    // Перед каждым запросом: если человек вошёл, берём его логин/пароль из сессии
    // и отдаём компоненту db. Само подключение откроется только при первом запросе к базе.
    'on beforeRequest' => function () use ($local) {
        $session = Yii::$app->session;
        $login   = $session->get('sqlLogin');
        $enc     = $session->get('sqlPass');
        if ($login && $enc) {
            $pass = Yii::$app->security->decryptByKey(base64_decode($enc), $local['sessionKey']);
            if ($pass !== false) {
                Yii::$app->db->username = $login;
                Yii::$app->db->password = $pass;
            }
        }
    },
];

// Ключ шифрования пароля нужен и при входе (LoginForm), кладём его в params.
$config['params']['sessionKey'] = $local['sessionKey'];

// Панель отладки и Gii только в dev и только с твоего компьютера:
// панель показывает данные запросов, в том числе введённые на форме входа.
if (YII_ENV_DEV) {
    $myIps = $local['debugIps'] ?? ['127.0.0.1', '::1'];
    $config['bootstrap'][]      = 'debug';
    $config['modules']['debug'] = ['class' => yii\debug\Module::class, 'allowedIPs' => $myIps];
    $config['bootstrap'][]      = 'gii';
    $config['modules']['gii']   = ['class' => yii\gii\Module::class, 'allowedIPs' => $myIps];
}

return $config;
