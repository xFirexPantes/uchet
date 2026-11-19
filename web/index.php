<?php
// Точка входа. Режим берётся из переменной YII_ENV контейнера (docker-compose.yml):
// dev-контейнер = 'dev' (подробные ошибки, панель отладки), prod = 'prod'.
// Если переменной нет, считаем, что это бой: безопаснее показать короткую ошибку, чем лишнее.
define('YII_ENV', getenv('YII_ENV') ?: 'prod');
define('YII_DEBUG', YII_ENV === 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
