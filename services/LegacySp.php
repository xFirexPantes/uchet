<?php
namespace app\services;

use Yii;

/**
 * Перенос общих функций VBA CommonStartSP1/2/4/5/6: вызов процедуры по позициям с OUTPUT-параметром.
 * Параметры передаются в том же порядке, что в VBA (ADO отправляет их позиционно), поэтому имена
 * параметров процедур знать не нужно.
 *
 *   VBA: CommonStartSP1(ID, "dbo.xp_CloseSch", 0)          → LegacySp::sp1($id, 'dbo.xp_CloseSch', 0)
 *   VBA: CommonStartSP6(ID, "dbo.xp_X", "текст", , , 255)   → LegacySp::sp6($id, 'dbo.xp_X', 'текст', 0, 255)
 *
 * Ошибка SQL, как в VBA, даёт Dflt (и запись в лог); WithErr=False в VBA только глушил MsgBox.
 */
class LegacySp
{
    /** CommonStartSP1, prms = 0: (ID int, res OUTPUT). $type: 'int' | 'float'. */
    public static function sp1(int $id, string $proc, $dflt = 0, string $type = 'int')
    {
        $t = $type === 'float' ? 'float' : 'int';
        return self::out("DECLARE @r $t; EXEC $proc ?, @r OUTPUT; SELECT @r", [$id], $proc, $dflt, $type);
    }

    /** CommonStartSP1/SP2/SP4/SP6, prms = 2: (ID int) без результата. */
    public static function sp1NoRes(int $id, string $proc): bool
    {
        self::check($proc);
        try {
            Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc ?", [1 => $id])->execute();
            return true;
        } catch (\yii\db\Exception $e) {
            Yii::error("$proc: " . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    /** CommonStartSP2, prms = 0: (ID int, res OUTPUT nvarchar(ln)). Возвращает строку. */
    public static function sp2(int $id, string $proc, string $dflt = '', int $ln = 50): string
    {
        return (string)self::out("DECLARE @r nvarchar($ln); EXEC $proc ?, @r OUTPUT; SELECT @r", [$id], $proc, $dflt, 'string');
    }

    /** CommonStartSP4, prms = 0: (ID int, Dta, res OUTPUT). $dtaType: SQL-тип второго параметра (int, float). */
    public static function sp4(int $id, string $proc, $dta, $dflt = 0, string $dtaType = 'float', string $resType = 'int')
    {
        $dtaSql = $dtaType === 'int' ? 'int' : 'float';
        $resSql = $resType === 'float' ? 'float' : 'int';
        return self::out("DECLARE @d $dtaSql = ?, @r $resSql; EXEC $proc ?, @d, @r OUTPUT; SELECT @r",
            [$dtaType === 'int' ? (int)$dta : (float)$dta, $id], $proc, $dflt, $resType);
    }

    /**
     * CommonStartSP5, prms = 0: (Dta varchar(ln), res OUTPUT int) — первый параметр строка, не ID.
     *   VBA: CommonStartSP5(ID, "dbo.xp_ProdPlanUpdChkBuf", 0, , , 60) → LegacySp::sp5($id, 'dbo.xp_ProdPlanUpdChkBuf', 0, 60)
     */
    public static function sp5(string $dta, string $proc, int $dflt = 0, int $ln = 50): int
    {
        return (int)self::out("DECLARE @d varchar($ln) = ?, @r int; EXEC $proc @d, @r OUTPUT; SELECT @r",
            [mb_substr($dta, 0, $ln)], $proc, $dflt, 'int');
    }

    /**
     * CommonStartSP6, prms = 0: (ID int, Dta nvarchar(ln), res OUTPUT int). Текст обрезается до ln, как Left$.
     * VBA: CommonStartSP6(ID, Nme, Dta, Dflt = 0, prms = 0, ln = 50, TimeOut = 30, Warn = 0): ln — 6-й параметр, 7-й — таймаут,
     * Warn = 1 — сообщение «Текст будет уменьшен до ln символов» (его показывает экран).
     */
    public static function sp6(int $id, string $proc, string $dta, int $dflt = 0, int $ln = 50): int
    {
        return (int)self::out("DECLARE @r int; EXEC $proc ?, ?, @r OUTPUT; SELECT @r",
            [$id, mb_substr($dta, 0, $ln)], $proc, $dflt, 'int');
    }

    /**
     * CommonStartSP6, prms = 0, когда первый параметр процедуры — не ID, а текст:
     * (Dta nvarchar, Dta nvarchar(ln), res OUTPUT int).
     *   VBA: CommonStartSP6(Me!LstZakasTrack.Column(1), "dbo.xp_SkladTrackingUpdOzn", CStr(Me!LstZakasTrack.Column(0)), , , 50)
     *   → LegacySp::sp6Text($order, 'dbo.xp_SkladTrackingUpdOzn', $ozn, 0, 50)
     */
    public static function sp6Text(string $first, string $proc, string $dta, int $dflt = 0, int $ln = 50): int
    {
        return (int)self::out("DECLARE @r int; EXEC $proc ?, ?, @r OUTPUT; SELECT @r",
            [$first, mb_substr($dta, 0, $ln)], $proc, $dflt, 'int');
    }

    /**
     * CommonStartSP7, prms = 0: (ID int, Dta nvarchar(ln), res OUTPUT nvarchar(lnRes)). Возвращает строку.
     * prms = 2 (без res) — sp7NoRes.
     */
    public static function sp7(int $id, string $proc, string $dta, string $dflt = '', int $ln = 50, int $lnRes = 50): string
    {
        return (string)self::out("DECLARE @r nvarchar($lnRes); EXEC $proc ?, ?, @r OUTPUT; SELECT @r",
            [$id, mb_substr($dta, 0, $ln)], $proc, $dflt, 'string');
    }

    private static function out(string $sql, array $values, string $proc, $dflt, string $type)
    {
        self::check($proc);
        $bind = [];
        foreach (array_values($values) as $i => $v) {
            $bind[$i + 1] = $v;
        }
        try {
            $v = Yii::$app->db->createCommand('SET NOCOUNT ON; ' . $sql, $bind)->queryScalar();
        } catch (\yii\db\Exception $e) {
            Yii::error("$proc: " . $e->getMessage(), __METHOD__);
            return $dflt;
        }
        if ($v === null || $v === false) {
            return $dflt;                                           // Nz(cmd.Parameters("res"), Dflt)
        }
        return match ($type) { 'int' => (int)$v, 'float' => (float)$v, default => (string)$v };
    }

    private static function check(string $proc): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+\.[A-Za-z0-9_]+$/', $proc)) {
            throw new \InvalidArgumentException("Плохое имя процедуры: $proc");
        }
    }
}
