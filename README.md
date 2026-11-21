# Каркас: вход по SQL-логину

Файлы для шагов 11 и 13 из [pilot-zakupki-struktura.md](../pilot-zakupki-struktura.md). Кладутся поверх чистого yii2-app-basic в `/srv/uchet/dev` с теми же путями, что здесь: перетащить мышкой в VS Code, согласиться на замену.

| Файл | Что делает | Аналог в Access |
|---|---|---|
| `config/web.php` | Настройки: два подключения (`db` под человеком, `dbSys` техлогин), сессия на 10 часов, красивые адреса | Свойства проекта ADP + строка подключения |
| `config/local.php.example` | Образец файла с паролями и ключами, **копировать в `config/local.php`**, в git не класть | — |
| `web/index.php` | Режим dev/prod берётся из контейнера, а не из файла | — |
| `services/ProcRunner.php` | Вызов процедур, даты в `YYYYMMDD` | Общий модуль с `Execute` |
| `controllers/BaseController.php` | Без входа никуда; если пароль сменили, выкидывает на вход | Проверка в `Form_Open` |
| `controllers/SiteController.php` | Вход, выход, главная | Окно входа ADP, frmUchet |
| `models/LoginForm.php` | Проверка логина: пробуем подключиться к SQL Server | — |
| `models/User.php` | Пользователь = SQL-логин, без своей таблицы | `CurrentUser()` |
| `views/site/login.php`, `views/layouts/login.php` | Экран входа | — |
| `views/site/index.php` | Временная главная: показывает, под кем подключились | — |

## Как это работает

1. Человек вводит логин и пароль от Access.
2. `LoginForm` пробует подключиться к SQL Server с ними. Получилось, значит, пароль верный. Неверный пароль и «нет связи с сервером» показываются разными сообщениями.
3. Пароль шифруется ключом `sessionKey` и кладётся в сессию. Он не хранится открытым ни в cookie, ни в файлах сессий.
4. На каждом запросе `config/web.php` расшифровывает пароль и отдаёт его компоненту `db`. Все процедуры идут под логином человека, поэтому `CURRENT_USER`, роли и аудит работают как в Access.
5. Через 10 часов без действий сессия умирает, и нужно войти заново.

## Установка (после шага 10)

**1. Положить файлы** из этой папки в `/srv/uchet/dev` с заменой.

**2. Удалить ненужное из шаблона** (страницы-примеры, они ссылаются на старый `User`):
```bash
cd /srv/uchet/dev
rm -f models/ContactForm.php views/site/about.php views/site/contact.php
rm -rf tests/unit/models tests/functional tests/acceptance
```
В `views/layouts/main.php` (шапка шаблона) убрать пункты меню `About` и `Contact`. Кнопка «Logout» там уже есть и будет работать.

**3. Создать `config/local.php`:**
```bash
cp config/local.php.example config/local.php
sudo chgrp www-data config/local.php   # читать может Apache (группа www-data)
chmod 640 config/local.php
cd /srv/uchet/docker
docker compose exec dev php -r 'echo bin2hex(random_bytes(32)), PHP_EOL, bin2hex(random_bytes(32)), PHP_EOL;'
```
Две строки, что напечатаются, вставить в `cookieValidationKey` и `sessionKey`. Заполнить имя сервера и базу. В `debugIps` вписать IP своего компьютера (в cmd: `ipconfig`). Пароль техлогина оставить пустым до шага 12, для входа он не нужен.

**4. Режим dev/prod в `docker-compose.yml`.** В `/srv/uchet/docker/docker-compose.yml` добавить каждому контейнеру строки `environment`:
```yaml
  prod:
    ...
    environment:
      YII_ENV: prod
  dev:
    ...
    environment:
      YII_ENV: dev
```
Затем пересоздать контейнеры: `docker compose up -d`.

**5. Проверить.** `http://IP:8081`, должна открыться форма входа.
- Вход своим логином: главная пишет «Вы вошли как ...», и там тот же логин, что `SELECT SUSER_SNAME()` в SSMS.
- Неверный пароль: «Неверный логин или пароль».
- Кнопка Logout возвращает на форму входа.

Если что-то не так, пришли вывод `docker compose logs --tail 50 dev` и `tail -50 /srv/uchet/dev/runtime/logs/app.log`.

**6. Коммит:**
```bash
cd /srv/uchet/dev
git add .
git status          # config/local.php в списке быть НЕ должно
git commit -m "Вход по SQL-логину"
```

## Права

`components/PermChecker.php` проверяет права теми же процедурами, что `GetPermission` в Access (`xp_GetPermissions2/3/4/4Detail`). Веб работает под логином человека, поэтому процедуры сами видят, кто спрашивает. VIEW и таблицы RBAC не нужны.

| Access | Веб |
|---|---|
| `GetPermission("EditIzgEl", False, 1) > 0` | `Yii::$app->user->can('EditIzgEl')` |
| `GetPermission("frmVhSchet")` (Rgm 0) | `can('form:frmVhSchet')` |
| `GetPermission("X", , 2)` | `can('p3:X')` |
| `WithMe:=False` | `can('X', ['withMe' => false])` |
| `MsgBox "Отсутствуют разрешения..."` | `AccessControl` в контроллере сам отдаёт страницу 403 |

Ответ кешируется на 5 минут на пользователя (`ttl` в `config/web.php`).

Проверка после установки: временно добавить в `views/site/index.php`
```php
<p>EditIzgEl: <?= Yii::$app->user->can('EditIzgEl') ? 'да' : 'нет' ?></p>
```
и сравнить с тем, что даёт Access тому же человеку.

## Файлы на \\TSRV (UncPath)

Access открывал файлы по UNC-путям (`\\TSRV\...`). Сервер Linux читает их через подключённую папку.
`app\components\UncPath::local($unc)` заменяет начало пути по настройке `uncMap` и возвращает локальный путь (или null, если файла нет).

1. Админ монтирует общую папку на сервере (cifs, только чтение), например в `/mnt/tsrv`, и пробрасывает её в контейнер.
2. В `config/local.php`:
```php
'uncMap' => ['\\\\TSRV\\' => '/mnt/tsrv/'],
```
3. В `config/web.php` (одна строка рядом с остальными `$params`):
```php
$params['uncMap'] = $local['uncMap'] ?? [];
```
Пока настройки нет, «Открыть» отвечает «Не найден файл».
