<?php
namespace app\modules\zakupki\services;

use Yii;

/**
 * Карточка счёта: frmMassInputSch (счёт на элементы, ID > 0) и frmScetaDetal (счёт на детали, ID < 0).
 * Шаг А: только чтение. Все запросы идут под SQL-логином пользователя, процедуры те же, что в Access.
 * Процедуры зовутся позиционно (EXEC proc ?, ?), как в VBA: имена параметров нам пока неизвестны.
 */
class SchetService
{
    /** Счёт на элементы. Аналог frmMassInputSch: OpenSch, UpdateList, SetRS4LstSchRes, SetRS4DogScet, FillListINN. */
    public function getElem(int $id): ?array
    {
        $rec = $this->exec('dbo.xp_frmMassInputSch1', [$id])->queryOne();       // Me.RecordSource
        if (!$rec) {
            return null;
        }
        $pos = $this->exec('dbo.xp_LstMassInputRowS1', [$id])->queryAll();     // подформа LstMassInpt
        $firstSklad = (int)($pos[0]['SkladID'] ?? 0);                           // Form_Load: SetRS4LstSchRes(ID, первая позиция)

        return [
            'rec'    => $rec,
            'pos'    => $pos,
            'res'    => $this->getRes($id, $firstSklad),
            'nakl'   => $this->safe(fn() => $this->exec('dbo.xp_LstNaklRowS1',
                            [(string)($rec['Организация'] ?? ''), (string)($rec['TP'] ?? ''), $id])->queryAll(), []),
            'dogs'   => $this->dogs($id),
            'firms'  => $this->firms(),
            'combos' => [
                // в счёте TP хранит название покупателя (колонка Name), поэтому значение списка = название, а не IspID
                'TP'  => array_combine(array_values($tp = $this->combo('dbo.xp_GetWhatFirm11')), array_values($tp)),
                'INN' => $this->combo('dbo.xp_GetINN'),          // ColumnWidths 0;2268
            ],
            'calc'   => [
                'SumSchPerPos' => $this->scalar('DECLARE @r float; EXEC dbo.xp_CountSumSc ?, @r OUTPUT; SELECT @r', [$id]),  // CountSumSch
                'KontrName'    => $this->scalar('DECLARE @f varchar(100); EXEC dbo.xp_IsExistFirma ?, @f OUTPUT; SELECT @f',
                                    [trim((string)($rec['INN'] ?? ''))]),                                                    // IsExistFirma
                'Kurs'         => $this->kurs($rec),
                'TakeMe'       => $this->takeMe($id) === 2,      // Me!TakeMe = CheckPosTakeMe(ID) = 2
            ],
        ];
    }

    /** Счёт на детали. Аналог frmScetaDetal: SetRS4SchDetal, UpdateListDetal. */
    public function getDetal(int $id): ?array
    {
        $rec = $this->exec('dbo.xp_ProdPlanGetSch', [$id, 0, 0])->queryOne();
        if (!$rec) {
            return null;
        }
        $tp = $this->combo('dbo.xp_GetWhatFirm11');
        return [
            'rec'    => $rec,
            'pos'    => $this->exec('dbo.xp_ProdPlanGetPosSch1', [$id])->queryAll(),
            // в Access второй параметр: IspFrm.Column(1), то есть название покупателя, а не код
            'nakl'   => $this->safe(fn() => $this->exec('dbo.xp_LstNaklRowSDetal',
                            [(string)($rec['NmeOrg'] ?? ''), (string)($tp[(string)($rec['IspFrm'] ?? '')] ?? ''), $id])->queryAll(), []),
            'dogs'   => $this->dogs($id),
            'firms'  => $this->firms(),
            'combos' => ['IspFrm' => $tp],
            'calc'   => [
                // CountSumSchDetal = dhRound(xp_CountSumScDetal, 2)
                'SumSchPerPos' => ($v = $this->scalar('DECLARE @r float; EXEC dbo.xp_CountSumScDetal ?, @r OUTPUT; SELECT @r', [$id])) === null ? null : round((float)$v, 2),
                'TakeMe'       => $this->takeMe($id) === 2,
                'Sost'         => \app\modules\zakupki\services\SchetDetalService::sost($id),          // GetSost("ScetDetal")
                'Closed'       => \app\services\LegacySp::sp1($id, 'dbo.xp_IsClosedSch', 0) === 0,        // CheckClosed
            ],
        ];
    }

    /** Резервы по позиции (подформа lstSchRes). Перезапрашивается при выборе позиции, как Form_Current. */
    public function getRes(int $id, int $skladId): array
    {
        return $this->safe(fn() => $this->exec('dbo.xp_frmMassInputSchGetRes', [$id, $skladId])->queryAll(), []);
    }

    private function dogs(int $id): array
    {
        return $this->safe(fn() => $this->exec('dbo.xp_SkladDogsAllowScetNew', [$id, 1, null, null])->queryAll(), []);
    }

    /** xListINN: FillListINN(vbNullString) → xp_GetFirms4Sch '%'. Поля Naim, INN, ID. */
    private function firms(): array
    {
        return $this->safe(fn() => $this->exec('dbo.xp_GetFirms4Sch', ['%'])->queryAll(), []);
    }

    /** Список для поля со списком: [значение => видимый текст] (колонка 0 → колонка 1). Кешируем на 5 минут. */
    private function combo(string $proc): array
    {
        return Yii::$app->cache->getOrSet(['combo', $proc, Yii::$app->user->id], function () use ($proc) {
            $map = [];
            foreach ($this->safe(fn() => $this->exec($proc, [])->queryAll(\PDO::FETCH_NUM), []) as $r) {
                $map[(string)$r[0]] = (string)($r[1] ?? $r[0]);
            }
            return $map;
        }, 300);
    }

    /** CheckPosTakeMe: 0 не забирать ничего, 1 часть позиций, 2 все. */
    private function takeMe(int $id): int
    {
        $r = $this->safe(fn() => $this->exec('dbo.xp_CheckPosTakeMe', [$id])->queryOne(), null);
        if (!$r) {
            return 0;
        }
        $all = (float)$r['AllKol'];
        $rest = $all - (float)$r['TakeKol'];
        if ($rest == $all) return 0;
        if ($rest == 0) return 2;
        return 1;
    }

    /** Kurs = IIf([BankKurs]<>[KursPost] And [KursPost]>0, [KursPost], [BankKurs]); синий, если курс поставщика. */
    private function kurs(array $rec): array
    {
        $bank = $rec['BankKurs'] ?? null;
        $post = $rec['KursPost'] ?? null;
        $isPost = $bank != $post && (float)$post > 0;
        return ['value' => $isPost ? $post : $bank, 'post' => $isPost];
    }

    private function scalar(string $sql, array $values)
    {
        return $this->safe(function () use ($sql, $values) {
            $bind = [];
            foreach (array_values($values) as $i => $v) {
                $bind[$i + 1] = $v;
            }
            return Yii::$app->db->createCommand('SET NOCOUNT ON; ' . $sql, $bind)->queryScalar();
        }, null);
    }

    /**
     * Второстепенные запросы не должны ронять всю карточку (в Access это On Error + MsgBox).
     * Ошибку пишем в лог, на экране поле просто пустое.
     */
    private function safe(callable $fn, $default)
    {
        try {
            return $fn();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return $default;
        }
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
