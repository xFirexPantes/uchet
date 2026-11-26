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

    /** Ответы на запрос списка менеджеров в пределах одного запроса. */
    private static ?array $managers = null;

    /** Выбрать менеджера: значение из первой колонки списка (по умолчанию "%"). */
    public static function setManager(string $value): void
    {
        self::$manager = $value !== '' ? $value : self::DEFAULT_MANAGER;
        self::$managers = null;
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

    /** Строка выбранного менеджера из exec dbo.xp_GetSkladManagersNew (по номерам колонок). */
    private static function managerRow(): array
    {
        if (self::$managers === null) {
            try {
                self::$managers = Yii::$app->db
                    ->createCommand('SET NOCOUNT ON; EXEC dbo.xp_GetSkladManagersNew')
                    ->queryAll(\PDO::FETCH_NUM);
            } catch (\Throwable $e) {
                Yii::warning('xp_GetSkladManagersNew: ' . $e->getMessage(), __METHOD__);
                self::$managers = [];
            }
        }
        foreach (self::$managers as $row) {
            if ((string)($row[0] ?? '') === self::$manager) return $row;
        }
        return [];
    }
}
