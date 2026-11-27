<?php
namespace app\modules\zakupki\services;

use app\components\LegacyMail;
use app\services\LegacySp;
use Yii;

/**
 * Значения, которые в Access форма frmSt брала из Forms!frmUchet.
 *
 * В Access это поля главной формы учёта:
 *   SkladManagers — список менеджеров склада (exec dbo.xp_GetSkladManagersNew), ColumnWidths "2268;0;0;0".
 *                   Значение по умолчанию — "%" (все менеджеры). Column(2) → ответственный (Otv),
 *                   Column(3) → «Раскрашивать долг» (NeedPaintDolg).
 *   KursUSD       — курс доллара на экране учёта, Locked, заполняется FillKurs → xp_GetKursByVal 840.
 *
 * В вебе главной формы учёта пока нет, поэтому менеджер берётся по значению "%" — ровно то,
 * что Access подставляет по умолчанию. Когда появится выбор менеджера в интерфейсе,
 * достаточно передать его значение в setManager().
 */
class StatContext
{
    /** Значение по умолчанию поля SkladManagers в frmSt: DefaultValue = "%". */
    private const DEFAULT_MANAGER = '%';

    /** Выбранный менеджер (значение первой колонки xp_GetSkladManagersNew). */
    private static string $manager = self::DEFAULT_MANAGER;

    /** Вид списка: в виде b менеджеры берутся из xp_SkladTrackingSprLst 1. */
    private static string $vid = '';

    /** Ответы на запрос списка менеджеров в пределах одного запроса (по процедуре). */
    private static array $managers = [];

    /**
     * Выбрать менеджера: значение из первой колонки списка (по умолчанию "%").
     *
     * @param string $vid вид списка: для «b» список менеджеров другой (xp_SkladTrackingSprLst 1)
     */
    public static function setManager(string $value, string $vid = ''): void
    {
        self::$manager = $value !== '' ? $value : self::DEFAULT_MANAGER;
        self::$vid = $vid;
    }

    /** Ответственный (Otv). В Access — SkladManagers.Column(2). Для «%» это 0, как и было. */
    public static function otv(): int
    {
        return (int)(self::managerRow()[2] ?? 0);
    }

    /**
     * FlgPaintDolg: SkladManagers.Column(3) > 0.
     * От него зависит строка поиска: при False к fnd добавляется «&» (так в Access).
     */
    public static function needPaintDolg(): bool
    {
        return (int)(self::managerRow()[3] ?? 0) > 0;
    }

    /**
     * Курс USD: как FillKurs в frmUchet — xp_GetKursByVal 840 (код валюты доллара).
     * Нужен только виду 7_0; при ошибке 0, как Dflt в CommonStartSP.
     */
    public static function kursUsd(): float
    {
        try {
            return (float)str_replace(',', '.', LegacySp::sp2(840, 'dbo.xp_GetKursByVal', '0', 50));
        } catch (\Throwable $e) {
            Yii::warning('xp_GetKursByVal 840: ' . $e->getMessage(), __METHOD__);
            return 0.0;
        }
    }

    /**
     * GetAdr(60) — граница цены по умолчанию для футера «Статистики».
     *
     * В Access fnRepaintFormStat: PriceBound = GetAdr(60) * KursUSD, а для вида 7 (dl = True)
     * граница переводится в доллары. GetAdr — это xp_GetAdr (Tip, Adr OUTPUT).
     */
    public static function priceBound(): float
    {
        $v = LegacyMail::adr(60);
        return (float)str_replace(',', '.', $v);
    }

    /**
     * Список менеджеров для выпадающего списка.
     *
     * Обычные виды: exec dbo.xp_GetSkladManagersNew, ColumnWidths "2268;0;0;0" — видно имя,
     * значение первой колонки. Вид b: exec dbo.xp_SkladTrackingSprLst 1, где первая колонка —
     * текст (fld1), вторая — тот же текст, третья — ID, четвёртая — пусто.
     *
     * @return array<int, array{value:string, name:string}>
     */
    public static function managerList(string $vid = ''): array
    {
        $out = [];
        foreach (self::managerRows($vid) as $r) {
            $val = (string)($r[0] ?? '');
            $out[] = ['value' => $val, 'name' => $val];
        }
        return $out;
    }

    /** Строка выбранного менеджера из списка менеджеров (по номерам колонок). */
    private static function managerRow(): array
    {
        foreach (self::managerRows(self::$vid) as $row) {
            if ((string)($row[0] ?? '') === self::$manager) return $row;
        }
        return [];
    }

    /** Все строки списка менеджеров; запрос — один раз на вид в пределах запроса страницы. */
    private static function managerRows(string $vid = ''): array
    {
        $key = $vid === 'b' ? 'b' : 'main';
        if (!array_key_exists($key, self::$managers)) {
            $sql = $key === 'b'
                ? 'EXEC dbo.xp_SkladTrackingSprLst 1'      // Case "b" fnRepaintFormStat
                : 'EXEC dbo.xp_GetSkladManagersNew';
            try {
                self::$managers[$key] = Yii::$app->db
                    ->createCommand('SET NOCOUNT ON; ' . $sql)
                    ->queryAll(\PDO::FETCH_NUM);
            } catch (\Throwable $e) {
                Yii::warning($sql . ': ' . $e->getMessage(), __METHOD__);
                self::$managers[$key] = [];
            }
        }
        return self::$managers[$key];
    }
}
