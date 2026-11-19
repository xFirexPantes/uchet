<?php
namespace app\components;

use PDO;
use Yii;
use yii\base\Component;
use yii\rbac\CheckAccessInterface;

/**
 * Проверка прав ТЕМИ ЖЕ процедурами, что GetPermission в Access.
 *
 * Веб подключается под логином человека, поэтому процедуры сами видят, кто спрашивает
 * (CURRENT_USER), как в Access. Своих таблиц и VIEW прав у веба нет: поменяли права
 * в Access, веб увидит это через $ttl секунд.
 *
 * Подключается в config/web.php:
 *     'components' => [
 *         'perm' => ['class' => app\components\PermChecker::class],
 *         'user' => [..., 'accessChecker' => 'perm'],
 *     ]
 * После этого работают стандартные проверки Yii:
 *     Yii::$app->user->can('EditIzgEl')            // = GetPermission("EditIzgEl", , 1)
 *     Yii::$app->user->can('form:frmVhSchet')      // = GetPermission("frmVhSchet")      (Rgm 0)
 *     Yii::$app->user->can('p3:Что-то')            // = GetPermission("Что-то", , 2)
 *     AccessControl: 'roles' => ['form:frmVhSchet']
 *
 * Имена:  'form:X' → Rgm 0 (открыть форму)
 *         'p3:X'   → Rgm 2
 *         'X'      → Rgm 1 (действие; так в Access вызывается чаще всего)
 */
class PermChecker extends Component implements CheckAccessInterface
{
    /**
     * Кому можно всё (в Access: If CrUs = "leonid" And WithMe ... Or CrUs = "litvinov").
     * Список лучше перенести в config/params.php, а не держать в коде.
     * 'withMe' = обходится параметром WithMe:=False, как у leonid.
     */
    public array $superUsers = [
        'litvinov' => ['withMe' => false],
        'leonid'   => ['withMe' => true],
    ];

    /** Сколько секунд помнить ответ (кеш APCu на пользователя). 0 = спрашивать базу каждый раз. */
    public int $ttl = 300;

    /** Ответы в пределах одного запроса: одна кнопка не спрашивает базу дважды. */
    private array $local = [];

    /**
     * @param string|int $userId   SQL-логин (User::getId())
     * @param string     $name     имя права, см. «Имена» выше
     * @param array      $params   ['withMe' => false] = аналог WithMe:=False
     */
    public function checkAccess($userId, $name, $params = []): bool
    {
        if (!$userId) {
            return false;
        }
        $login  = strtolower((string)$userId);
        $withMe = $params['withMe'] ?? true;
        if (isset($this->superUsers[$login]) && (!$this->superUsers[$login]['withMe'] || $withMe)) {
            return true;
        }

        $key = $login . '|' . $name;
        if (array_key_exists($key, $this->local)) {
            return $this->local[$key];
        }
        $cacheKey = ['perm', $key];
        $ok = $this->ttl > 0 ? Yii::$app->cache->get($cacheKey) : false;
        if ($ok === false) {
            $ok = $this->ask($name) ? 1 : 0;           // в кеш кладём 1/0: false у кеша значит «нет записи»
            if ($this->ttl > 0) {
                Yii::$app->cache->set($cacheKey, $ok, $this->ttl);
            }
        }
        return $this->local[$key] = (bool)$ok;
    }

    private function ask(string $name): bool
    {
        if (strncmp($name, 'form:', 5) === 0) {
            return $this->formPermission(substr($name, 5));
        }
        if (strncmp($name, 'p3:', 3) === 0) {
            return $this->simple('dbo.xp_GetPermissions3', substr($name, 3));
        }
        return $this->simple('dbo.xp_GetPermissions2', $name);
    }

    /**
     * Rgm 0. Как в Access: xp_GetPermissions4 даёт номера колонок (column_id),
     * xp_GetPermissions4Detail даёт строку; право есть, если хоть в одной из этих колонок > 0.
     */
    private function formPermission(string $form): bool
    {
        $db   = Yii::$app->db;
        $cols = $db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_GetPermissions4 :f', [':f' => $form])->queryAll();
        if (!$cols) {
            return false;
        }
        $row = $db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_GetPermissions4Detail :f', [':f' => $form])
                  ->queryOne(PDO::FETCH_NUM);  // по номерам колонок, как Prm.Fields(fld)
        if (!$row) {
            return false;
        }
        foreach ($cols as $c) {
            $i = (int)$c['column_id'] - 1;
            if (isset($row[$i]) && (int)$row[$i] > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rgm 1 и 2: в Access это CommonStartSP5(имя, процедура, 0, adVarChar, , 20), режим prms = 0:
     * первый параметр процедуры = имя права (varchar(20)), второй = OUTPUT int с результатом.
     * Параметры ADO передаёт по порядку, поэтому здесь тоже по порядку, без имён.
     * NULL в ответе или ошибка = 0 (Dflt), то есть «нет права», как в Access.
     */
    private function simple(string $proc, string $name): bool
    {
        $sql = "SET NOCOUNT ON; DECLARE @res int; EXEC $proc :n, @res OUTPUT; SELECT @res";
        try {
            $r = Yii::$app->db->createCommand($sql, [':n' => mb_substr($name, 0, 20)])->queryScalar();
        } catch (\yii\db\Exception $e) {
            // В Access здесь MsgBox с ошибкой. В вебе пишем в лог и считаем, что права нет.
            Yii::error("$proc('$name'): " . $e->getMessage(), __METHOD__);
            return false;
        }
        return (int)$r > 0;
    }
}
