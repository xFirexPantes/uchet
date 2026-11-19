<?php
namespace app\services;

use DateTimeInterface;
use Yii;
use yii\db\Connection;

/**
 * Один способ вызывать хранимые процедуры (аналог общего модуля VBA с CurrentProject.Connection.Execute).
 *
 *   ProcRunner::rows('dbo.xp_GetManagers1');                    // набор строк
 *   ProcRunner::row('dbo.xp_GetSchet', ['ID' => 5]);           // одна строка или null
 *   ProcRunner::exec('dbo.xp_SaveSchet', ['ID' => 5, 'Dta' => '2026-11-19']);
 *
 * Даты: у логинов TrxSQL русский язык, поэтому '2026-11-19' SQL Server читает как год-ДЕНЬ-месяц.
 * Здесь все даты переводятся в 'YYYYMMDD' (этот формат понимается при любом языке).
 */
class ProcRunner
{
    public static function rows(string $proc, array $params = [], ?Connection $db = null): array
    {
        return self::cmd($proc, $params, $db)->queryAll();
    }

    public static function row(string $proc, array $params = [], ?Connection $db = null): ?array
    {
        $r = self::cmd($proc, $params, $db)->queryOne();
        return $r ?: null;
    }

    public static function exec(string $proc, array $params = [], ?Connection $db = null): void
    {
        self::cmd($proc, $params, $db)->execute();
    }

    private static function cmd(string $proc, array $params, ?Connection $db)
    {
        // Имя процедуры подставляется в текст запроса, поэтому пускаем только «схема.имя».
        if (!preg_match('/^[A-Za-z0-9_]+\.[A-Za-z0-9_]+$/', $proc)) {
            throw new \InvalidArgumentException("Плохое имя процедуры: $proc");
        }
        $args = [];
        $bind = [];
        foreach ($params as $name => $value) {
            if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                throw new \InvalidArgumentException("Плохое имя параметра: $name");
            }
            $args[]           = "@$name = :$name";
            $bind[":$name"]   = self::normalize($value);
        }
        // SET NOCOUNT ON: без него драйвер иногда отдаёт пустой результат вместо данных.
        $sql = 'SET NOCOUNT ON; EXEC ' . $proc . ($args ? ' ' . implode(', ', $args) : '');
        return ($db ?? Yii::$app->db)->createCommand($sql, $bind);
    }

    /** Даты в YYYYMMDD, bool в 1/0, остальное как есть. */
    private static function normalize($value)
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00' ? $value->format('Ymd') : $value->format('Ymd H:i:s');
        }
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})( \d{2}:\d{2}(:\d{2})?)?$/', $value, $m)) {
            return $m[1] . $m[2] . $m[3] . ($m[4] ?? '');
        }
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        return $value;
    }
}
