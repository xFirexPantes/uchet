<?php
namespace app\modules\zakupki\services;

use app\services\LegacySp;
use Yii;

/**
 * Окно ProdPlanSnd2Buf «Составляющие детали на буферный склад» (OpenArgs 0) / «… в накладную» (OpenArgs 1).
 * Строки — временная таблица dbo.tmpProdPlan2Buf текущего пользователя (Who = CURRENT_USER). Заполняет её
 * xp_ProdPlanIsReadySostDetal при «На склад» в накладной счёта на детали (IsReadySostDetal), показывает xp_ProdPlanSnd2BufRS.
 * Веб подключается к SQL под логином пользователя, поэтому CURRENT_USER у каждого свой, как в Access.
 */
class BufService
{
    public const CAPTION = [0 => 'Составляющие детали на буферный склад', 1 => 'Составляющие детали в накладную'];

    /** Form_Open: RecordSource = "exec xp_ProdPlanSnd2BufRS". */
    public function rows(): array
    {
        try {
            return Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_ProdPlanSnd2BufRS')->queryAll();
        } catch (\yii\db\Exception $e) {
            Yii::error('xp_ProdPlanSnd2BufRS: ' . $e->getMessage(), __METHOD__);
            return [];
        }
    }

    public function row(string $idStr): ?array
    {
        foreach ($this->rows() as $r) if ((string)$r['IDStr'] === $idStr) return $r;
        return null;
    }

    /** Chk_MouseDown: AllID > 0 — уже отправлена; CommonStartSP5(IDStr, xp_ProdPlanUpdChkBuf, 0, , , 60) = 1 — переключили. */
    public function chk(string $idStr): array
    {
        $r = $this->row($idStr);
        if ($r === null) return ['ok' => false, 'msg' => 'Строка не найдена', 'reload' => true];
        if ((int)($r['AllID'] ?? 0) > 0) return ['ok' => false, 'msg' => 'Данная строка уже отправлена на буферный склад'];
        return LegacySp::sp5($idStr, 'dbo.xp_ProdPlanUpdChkBuf', 0, 60) === 1
            ? ['ok' => true]
            : ['ok' => false, 'msg' => 'Не удалось изменить состояние флажка'];
    }

    /**
     * btnSend2Buf_Click: вопрос задаёт клиент; xp_ProdPlanAddFakeNaklNew (@fake = OpenArgs, @res OUT).
     * Результат по строкам процедура пишет в столбец «Состояние», окно просто перечитывает список.
     * Ошибка SQL в Access — MsgBox Err.Description, здесь так же.
     */
    public function send(int $fake): array
    {
        try {
            Yii::$app->db->createCommand('SET NOCOUNT ON; DECLARE @r int; EXEC dbo.xp_ProdPlanAddFakeNaklNew ?, @r OUTPUT; SELECT @r', [1 => $fake])->queryScalar();
            return ['ok' => true];
        } catch (\yii\db\Exception $e) {
            Yii::error('xp_ProdPlanAddFakeNaklNew: ' . $e->getMessage(), __METHOD__);
            return ['ok' => false, 'msg' => 'Ошибка при переносе: ' . ($e->errorInfo[2] ?? $e->getMessage())];
        }
    }

    /** MnlKol_DblClick → UpdKolDetal4Buf(0, 0, IDStr, vl): 1 — ок, 3 — больше, чем в техкарте. Текста функции пока нет. */
    public function kol(string $idStr, int $vl): array
    {
        return ['ok' => false, 'msg' => 'Изменение количества ещё не перенесено (нужна функция UpdKolDetal4Buf)'];
    }

    /** MnlSm_DblClick → UpdSmDetal4Buf(0, 0, IDStr, Sm) > 0 — ок. Текста функции пока нет. */
    public function sm(string $idStr, float $sm): array
    {
        return ['ok' => false, 'msg' => 'Изменение суммы ещё не перенесено (нужна функция UpdSmDetal4Buf)'];
    }
}
