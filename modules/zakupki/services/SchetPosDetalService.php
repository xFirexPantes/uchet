<?php
namespace app\modules\zakupki\services;

use app\services\LegacySp;
use Yii;

/**
 * Окно «Позиция счёта для деталей» (frmScetaDetalAdd).
 *
 * Как в Access: правка идёт во временной записи dbo.subtblSchDetaltmp (одна строка на пользователя, Who = CURRENT_USER).
 * Открытие: xp_ProdPlanUpdPosSch (ID счёта, IDPos) — очищает и заполняет временную запись (IDPos = 0 — новая позиция).
 * Перечитать: xp_ProdPlanUpdPosSch 0, 0 (только SELECT). Каждое поле — UPDATE временной записи (форма привязана к ней).
 * Кол-во и цена пересчитывают суммы: xp_ProdPlanSchRecountTmp (ScID). ✓: xp_ProdPlanSavePosSch (IDPos) < 0 — сохранено.
 */
class SchetPosDetalService
{
    /** Поля формы, которые можно менять: [поле => тип]. Nme (номенклатура) выбирается в fObnov — ещё не перенесена. */
    public const FIELDS = ['FullNme' => 'text', 'Kol' => 'num', 'Prc' => 'num', 'NDS' => 'num', 'Sm' => 'num', 'SmNDS' => 'num', 'Vsego' => 'num'];

    /** Form_Open → FillPosSchDetal(ID, IDPos). Проверяем, что счёт открыт и позиция из этого счёта. */
    public function open(int $id, int $idPos): array
    {
        $rec = (new SchetDetalService())->rec($id);
        if (!SchetDetalService::allowEdits($rec)) {
            throw new \yii\web\ForbiddenHttpException('Счёт закрыт');
        }
        if ($idPos > 0) {
            $ids = array_map(fn($r) => (int)$r['IDPos'], $this->exec('dbo.xp_ProdPlanGetPosSch1', [$id])->queryAll());
            if (!in_array($idPos, $ids, true)) {
                throw new \yii\web\NotFoundHttpException('Позиция не найдена в этом счёте');
            }
        }
        $row = $this->exec('dbo.xp_ProdPlanUpdPosSch', [$id, $idPos])->queryOne();
        if (!$row) {
            throw new \yii\web\NotFoundHttpException('Не найден счёт');
        }
        return $row;
    }

    /** Сохранить поле (AfterUpdate → acCmdSaveRecord); для Kol и Prc ещё пересчёт сумм. Ответ: ok, msg, set (все поля заново). */
    public function saveField(int $id, string $field, ?string $value): array
    {
        if (!isset(self::FIELDS[$field])) {
            return ['ok' => false, 'msg' => 'Это поле нельзя менять'];
        }
        $v = SchetSaveService::normalize(self::FIELDS[$field], $value);
        if ($v === false || (self::FIELDS[$field] === 'num' && $v !== null && $v < 0)) {   // ValidationRule: >=0 Or Is Null
            return ['ok' => false, 'msg' => 'Неверное значение'];
        }
        if ($field === 'FullNme' && $v !== null) $v = mb_substr(trim((string)$value), 0, 255);
        $n = Yii::$app->db->createCommand(
            "UPDATE dbo.subtblSchDetaltmp SET [$field] = :v WHERE Who = CURRENT_USER AND ScID = :id", [':v' => $v, ':id' => $id]
        )->execute();
        if ($n === 0) {
            return ['ok' => false, 'msg' => 'Позиция уже закрыта в другом окне. Откройте её заново'];
        }
        $res = ['ok' => true];
        if ($field === 'Kol' || $field === 'Prc') {
            if (LegacySp::sp1($id, 'dbo.xp_ProdPlanSchRecountTmp', 0) > 0) {
                $res['set'] = $this->values();
            } else {
                $res['msg'] = 'Не удалось рассчитать сумму';
            }
        }
        return $res;
    }

    /** AccptRec_Click: CommonStartSP1(IDPos, "dbo.xp_ProdPlanSavePosSch", 0) < 0 — сохранено, окно закрывается. */
    public function accept(int $id, int $idPos): array
    {
        (new SchetDetalService())->rec($id);
        if (LegacySp::sp1($idPos, 'dbo.xp_ProdPlanSavePosSch', 0) < 0) {
            return ['ok' => true, 'close' => true];
        }
        return ['ok' => false, 'msg' => 'Не удалось сохранить позицию счёта'];
    }

    /**
     * Nme_DblClick после выбора в fObnov: CommonStartSP6(0, "dbo.xp_ProdPlanUpdPosNomenkl", SkladID, , , 50) > 0 → FillPosSchDetal(0, 0).
     * Ответ: set — все поля временной записи заново, включая «Номенклатуру» (Nme).
     */
    public function nomenkl(int $id, int $sklad): array
    {
        $rec = (new SchetDetalService())->rec($id);
        if (!SchetDetalService::allowEdits($rec)) return ['ok' => false, 'msg' => 'Счёт закрыт'];
        if ($sklad <= 0) return ['ok' => false];
        if (LegacySp::sp6(0, 'dbo.xp_ProdPlanUpdPosNomenkl', (string)$sklad, 0, 50) <= 0) return ['ok' => false];
        $r = $this->exec('dbo.xp_ProdPlanUpdPosSch', [0, 0])->queryOne() ?: [];
        return ['ok' => true, 'set' => $this->values() + ['Nme' => (string)($r['Nme'] ?? '')]];
    }

    /** Текущие значения временной записи для экрана. */
    private function values(): array
    {
        $r = $this->exec('dbo.xp_ProdPlanUpdPosSch', [0, 0])->queryOne() ?: [];
        $out = [];
        foreach (array_keys(self::FIELDS) as $f) {
            $v = $r[$f] ?? null;
            $out[$f] = $v === null ? '' : (is_numeric($v) && $f !== 'FullNme' ? str_replace('.', ',', (string)(float)$v) : (string)$v);
        }
        return $out;
    }

    private function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind  = [];
        foreach (array_values($values) as $i => $v) {
            $bind[$i + 1] = $v;
        }
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc $marks", $bind);
    }
}
