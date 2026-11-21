<?php
namespace app\components;

use Yii;

/**
 * Пути Windows (\\TSRV\Docs\...) из Access → путь к той же папке на сервере (Linux).
 * Папки \\TSRV подключает админ (cifs) в контейнер, соответствие задаётся в config/local.php:
 *   'uncMap' => ['\\\\TSRV\\Docs\\' => '/mnt/tsrv/docs/', '\\\\TSRV\\Basa\\' => '/mnt/tsrv/basa/'],
 * и в config/web.php одной строкой: $params['uncMap'] = $local['uncMap'] ?? [];
 * Пока соответствия нет — local() возвращает null (файл «не найден»).
 */
class UncPath
{
    /** Локальный путь к существующему файлу или null. Выйти за пределы подключённой папки (..) нельзя. */
    public static function local(string $unc): ?string
    {
        $unc = trim($unc);
        if ($unc === '' || str_contains($unc, '..')) return null;
        foreach ((array)(Yii::$app->params['uncMap'] ?? []) as $from => $to) {
            if (stripos($unc, $from) === 0) {
                $p = rtrim($to, '/') . '/' . str_replace('\\', '/', ltrim(substr($unc, strlen($from)), '\\'));
                return is_file($p) ? $p : null;
            }
        }
        return null;
    }

    /**
     * Локальный путь, куда записать файл с UNC-путём $unc (FileCopy … "\\TSRV\..."). Недостающие папки создаются (MkDir).
     * null — папка не подключена, путь с «..» или папку не создать / нет права записи.
     */
    public static function target(string $unc): ?string
    {
        $unc = trim($unc);
        if ($unc === '' || str_contains($unc, '..')) return null;
        foreach ((array)(Yii::$app->params['uncMap'] ?? []) as $from => $to) {
            if (stripos($unc, $from) !== 0) continue;
            $p = rtrim($to, '/') . '/' . str_replace('\\', '/', ltrim(substr($unc, strlen($from)), '\\'));
            $dir = dirname($p);
            if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return null;
            return is_writable($dir) ? $p : null;
        }
        return null;
    }

    /** Подключена ли папка для пути $unc (есть соответствие в uncMap). */
    public static function mapped(string $unc): bool
    {
        foreach ((array)(Yii::$app->params['uncMap'] ?? []) as $from => $to) {
            if (stripos(trim($unc), $from) === 0) return true;
        }
        return false;
    }

    /**
     * Имя файла или папки, пригодное для Windows: символы \ / : * ? " < > | и управляющие заменяются на «_», точки и пробелы по краям убираются.
     * Аналог Replace_symbols из Access (её кода пока нет — набор символов может отличаться).
     */
    public static function safeName(string $s): string
    {
        $s = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', '_', $s);
        return trim($s, " .");
    }

    /** Сохранить загруженный файл по UNC-пути. Возвращает null или текст ошибки. */
    public static function save(string $tmpFile, string $unc): ?string
    {
        if (!self::mapped($unc)) return 'Папка TSRV не подключена на сервере (настройка uncMap)';
        $p = self::target($unc);
        if ($p === null) return "Нет доступа на запись:\n$unc";
        return @copy($tmpFile, $p) ? null : "Не удалось записать файл:\n$unc";
    }
}
