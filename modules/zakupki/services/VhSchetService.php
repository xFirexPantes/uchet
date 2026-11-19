<?php
namespace app\modules\zakupki\services;

use app\modules\zakupki\models\VhSchetFilter;
use Yii;

/**
 * Процедуры формы frmStat «Входящие счета» (аналог кода в модуле формы).
 *
 * ⚠ Параметры передаются ПО ПОРЯДКУ (знаки ?), а не по именам.
 * В VBA имена в CreateParameter("Firma", ...) ADO не отправляет, значит, настоящих имён
 * параметров процедур мы пока не знаем. Когда пришлёшь тексты процедур, перейдём на ProcRunner с именами.
 */
class VhSchetService
{
    /**
     * Список счетов: как FillListSch.
     * xp_GetAllSch Firma, NSch, IsClosed, DtaB, DtaE, Rgm, TypeSch
     */
    public function getList(VhSchetFilter $f): array
    {
        if ($f->id > 0) {
            // Режим «открыта на один счёт» (OpenArgs = ID): в Access Firma и даты остаются пустыми,
            // IsClosed = 0, тип сырья = 3 («не важно»), а № счета = ID & "^"
            $p = ['', $f->id . '^', 0, '', '', 3, (int)$f->typeSch];
        } else {
            $p = [
                '%',                                            // cboFirma на форме скрыт, всегда «все продавцы»
                $f->nsch === '' ? '%' : '%' . $f->nsch . '%',
                $f->allSch ? 2 : 1,
                $this->ymd($f->dateS, '20000101'),
                $this->ymd($f->datePo, date('Ymd')),
                (int)$f->typeSel,
                (int)$f->typeSch,
            ];
        }
        return $this->exec('dbo.xp_GetAllSch', $p)->queryAll();
    }

    /**
     * Итог «Сумма,$» над списком: как butF_Click.
     * xp_frmStat_butFNew IsClosed, stFind, dataS, dataPo, @Sm OUTPUT
     * В Access сюда передаётся IsClosed 2/0 и stFind всегда '%' (фирма и № счета не учитываются).
     */
    public function getTotal(VhSchetFilter $f): ?float
    {
        $sql = 'SET NOCOUNT ON; DECLARE @Sm money; EXEC dbo.xp_frmStat_butFNew ?, ?, ?, ?, @Sm OUTPUT; SELECT @Sm';
        $v = Yii::$app->db->createCommand($sql, [
            1 => $f->allSch ? 2 : 0,
            2 => '%',
            3 => $this->ymd($f->dateS, '20000101'),
            4 => $this->ymd($f->datePo, date('Ymd')),
        ])->queryScalar();
        return $v === null || $v === false ? null : (float)$v;
    }

    /** Панель выбранного счёта: как SetRowSOplPrc. Строки по номерам колонок, как RowSource у ListBox. */
    public function getPanel(int $id, int $typeSch): array
    {
        return [
            'opl'     => $this->exec('dbo.xp_GetOplPrc', [$id])->queryAll(\PDO::FETCH_NUM),
            'oplToDo' => $this->exec('dbo.xp_OplPrcToDoLst', [$id, $typeSch])->queryAll(\PDO::FETCH_NUM),
            'plat'    => $this->exec('dbo.xp_SubFrmPlatFrom1C', [$id, 2])->queryAll(\PDO::FETCH_NUM),
            // Подформа lstDogs (SetRS4DogScet): договоры счёта, колонки по именам (NumDog, DataDog, ...)
            'dogs'    => $this->exec('dbo.xp_SkladDogsAllowScetNew', [$id, 1, null, null])->queryAll(),
            'answ'    => $this->lastAnswer($id),
        ];
    }

    /**
     * Последний ответ: CommonStartSP2(ID, "dbo.xp_SchGetLastAnsw", "", , , 255).
     * Процедура: 1-й параметр ID (int), 2-й OUTPUT nvarchar(255). Строка вида "дд.мм.гггг текст":
     * в Access LastAnswDta = Left(st, 10), LastAnsw = Mid(st, 12).
     */
    private function lastAnswer(int $id): array
    {
        $st = (string)Yii::$app->db->createCommand(
            'SET NOCOUNT ON; DECLARE @res nvarchar(255); EXEC dbo.xp_SchGetLastAnsw ?, @res OUTPUT; SELECT @res',
            [1 => $id]
        )->queryScalar();
        return ['dta' => mb_substr($st, 0, 10), 'txt' => mb_substr($st, 11)];
    }

    /** Одна строка после закрытия карточки: как ChangeDataInList. */
    public function getOne(int $id): ?array
    {
        $r = $this->exec('dbo.xp_GetSchByID', [$id])->queryOne();
        return $r ?: null;
    }

    /** EXEC процедура ?, ?, ... с параметрами по порядку. */
    private function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind  = [];
        foreach (array_values($values) as $i => $v) {
            $bind[$i + 1] = $v;                         // у Yii позиционные параметры считаются с 1
        }
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc $marks", $bind);
    }

    /** '2026-11-19' (из поля даты) → '20261119', как Format$(..., "yyyymmdd"). Пусто → $default. */
    private function ymd(?string $d, string $default): string
    {
        return $d ? str_replace('-', '', $d) : $default;
    }
}
