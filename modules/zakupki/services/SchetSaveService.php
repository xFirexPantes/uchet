<?php
namespace app\modules\zakupki\services;

use app\services\LegacySp;
use Yii;

/**
 * Сохранение шапки счёта на элементы (frmMassInputSch), шаг Б1.
 *
 * В Access форма привязана к xp_frmMassInputSch1, а ADP пишет правки прямо в dbo.tblPredShceta
 * (ключ PredShcID) после каждого поля (AfterUpdate → acCmdSaveRecord). Здесь то же самое:
 * одно поле = один UPDATE под логином пользователя, плюс правила из обработчиков VBA.
 * Вычисляемые поля процедуры (Holding, NumFZ275, NotUsed, InUEtxt, IsDav, BankKurs...) не пишем.
 */
class SchetSaveService
{
    /** Поля, которые можно менять с экрана: [поле формы => тип]. */
    public const FIELDS = [
        'НомерСчета' => 'text',
        'ДатаСчета'  => 'date',
        'NDS'        => 'num',
        'Sm'         => 'num',
        'AdditSum'   => 'num',
        'SumDost'    => 'num',   // AddNDS: спрашиваем «Указанная сумма с НДС?»
        'DopSum'     => 'num',   // AddNDS
        'Dst'        => 'int',
        'TP'         => 'text',  // в tblPredShceta.TP хранится НАЗВАНИЕ покупателя (столбец Name xp_GetWhatFirm11), код — в Isp
        'Dta2Opl'    => 'date',
    ];

    /**
     * Можно ли править шапку (OpenSch): право FullSch, у давальческого счёта ещё EdtDavSch.
     * В Access закрытый счёт тоже правится (AllowEdits = AllowEd), закрытие блокирует только кнопки.
     */
    public static function allowEd(array $rec): bool
    {
        $u = Yii::$app->user;
        return $u->can('FullSch') || ((int)($rec['IsDav'] ?? 0) > 0 && $u->can('EdtDavSch'));
    }

    /**
     * Сохранить одно поле. Ответ для экрана:
     *   ok   — сохранено ли; msg — сообщение (как MsgBox);
     *   set  — какие поля экрана обновить [поле => значение]; lockDst — запереть склад.
     */
    public function saveField(int $id, string $field, ?string $value, bool $withNds = true): array
    {
        $rec = $this->rec($id);
        if (!isset(self::FIELDS[$field])) {
            return ['ok' => false, 'msg' => 'Это поле нельзя менять'];
        }
        $v = self::normalize(self::FIELDS[$field], $value);
        if ($v === false) {
            return ['ok' => false, 'msg' => 'Неверное значение'];
        }

        switch ($field) {
            case 'Sm':          // Sm_AfterUpdate: SmNDS = Round(Sm * NDS / (100 + NDS), 2)
                $nds = (float)($rec['NDS'] ?? 0);
                $smNds = $v === null ? null : round($v * $nds / (100 + $nds), 2);
                $this->update($id, ['Sm' => $v, 'SmNDS' => $smNds]);
                return ['ok' => true, 'set' => ['SmNDS' => $smNds]];

            case 'SumDost':     // SumDost_AfterUpdate / DopSum_AfterUpdate: AddNDS (ставка по умолчанию 22)
            case 'DopSum':
                if ($v !== null && $v != 0 && !$withNds) {
                    $v = round($v * (100 + 22) / 100, 2);
                }
                $this->update($id, [$field => $v]);
                return ['ok' => true, 'set' => [$field => $v === null ? '' : str_replace('.', ',', (string)$v)]];

            case 'Dst':
                if (!in_array($v, [0, 1, 255], true)) return ['ok' => false, 'msg' => 'Неверный склад'];
                break;

            case 'TP':          // TP_AfterUpdate
                $tp = $this->tpRowByName($v);                  // TP.Column(0) = IspID, Column(2) = Dst
                if ($tp === null || !in_array((int)$tp[0], [1, 8], true)) {
                    $this->update($id, ['TP' => null, 'Isp' => null, 'Dst' => 255]);
                    return ['ok' => false, 'msg' => 'Нельзя выбрать этого покупателя', 'set' => ['TP' => '', 'Dst' => 255], 'lockDst' => false];
                }
                if ((string)($rec['Организация'] ?? '') === (string)$v) {
                    return ['ok' => false, 'msg' => 'Покупатель и продавец не могут иметь одинаковые названия'];
                }
                $dst = (int)($tp[2] ?? 255);
                $this->update($id, ['TP' => (string)$tp[1], 'Isp' => (int)$tp[0], 'Dst' => $dst]);
                // по ТЗ списание по складам (13.03.23): без права ChgDstSklad склад после выбора покупателя не меняют
                return ['ok' => true, 'set' => ['Dst' => $dst], 'lockDst' => !Yii::$app->user->can('ChgDstSklad')];
        }

        $this->update($id, [$field => $v]);
        $res = ['ok' => true];
        // НомерСчета_AfterUpdate / ДатаСчета_GotFocus: если есть и номер, и дата — CheckSchIn1C и CheckSimilarSch
        if ($field === 'НомерСчета' || $field === 'ДатаСчета') {
            $msgs = [];
            if ($this->numDate($id) !== null) {
                if ($this->in1C($id)) $msgs[] = 'Такой счёт уже существует в 1С';
                if ($this->similar($id)) $msgs[] = 'Уже существует подобный счёт';
            }
            $res['msg'] = $msgs ? implode("\n", $msgs) : null;
        }
        return $res;
    }

    /**
     * Выбор контрагента двойным щелчком в списке фирм (xListINN_DblClick):
     * INN = ID фирмы (третья колонка списка), Организация = её название.
     */
    public function setFirm(int $id, int $firmId): array
    {
        $this->rec($id);
        $firm = null;
        foreach ($this->exec('dbo.xp_GetFirms4Sch', ['%'])->queryAll() as $r) {
            if ((int)$r['ID'] === $firmId) { $firm = $r; break; }
        }
        if ($firm === null) {
            return ['ok' => false, 'msg' => 'Фирма не найдена'];
        }
        $this->update($id, ['INN' => trim((string)$firm['ID']), 'Организация' => $firm['Naim']]);
        return ['ok' => true, 'reload' => true];   // в Access: Me.RecordSource = ... (перечитать форму)
    }

    /** Список фирм для поиска в поле Fnd (fnd_Change → FillListINN). */
    public function firms(string $st): array
    {
        return $this->exec('dbo.xp_GetFirms4Sch', [mb_substr($st, 0, 99) . '%'])->queryAll();
    }

    /** Номер и дата счёта для проверок: «номер;дд.мм.гггг» или null, если чего-то нет. */
    private function numDate(int $id): ?string
    {
        $r = $this->exec('dbo.xp_frmMassInputSch1', [$id])->queryOne();
        if (!$r || $r['НомерСчета'] === null || $r['ДатаСчета'] === null) {
            return null;
        }
        $d = substr((string)$r['ДатаСчета'], 0, 10);                       // 2026-11-19
        return trim($r['НомерСчета']) . ';' . substr($d, 8, 2) . '.' . substr($d, 5, 2) . '.' . substr($d, 0, 4);
    }

    /** CheckSimilarSch: CommonStartSP6(ID, "dbo.xp_IsExistSimilarSch", "номер;дата", , , 255) > 0. */
    public function similar(int $id): bool
    {
        $st = $this->numDate($id);
        return $st !== null && LegacySp::sp6($id, 'dbo.xp_IsExistSimilarSch', $st, 0, 255) > 0;
    }

    /** CheckSchIn1C: xp_IsExistSchIn1CNew (ID, Rgm = 0, res OUTPUT). */
    private function in1C(int $id): bool
    {
        try {
            return (bool)Yii::$app->db->createCommand(
                'SET NOCOUNT ON; DECLARE @r int; EXEC dbo.xp_IsExistSchIn1CNew ?, 0, @r OUTPUT; SELECT @r', [1 => $id]
            )->queryScalar();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return false;
        }
    }

    /** Запись счёта + проверка прав. */
    public function rec(int $id): array
    {
        $rec = $this->exec('dbo.xp_frmMassInputSch1', [$id])->queryOne();
        if (!$rec) {
            throw new \yii\web\NotFoundHttpException("Счёт $id не найден");
        }
        if (!self::allowEd($rec)) {
            throw new \yii\web\ForbiddenHttpException('У Вас нет прав на данное действие');
        }
        return $rec;
    }

    /** Строка покупателя из xp_GetWhatFirm11: [0] код, [1] название, [2] склад. */
    /** Строка xp_GetWhatFirm11 (IspID, Name, Dst) по названию покупателя — так TP хранится в счёте на элементы. */
    public function tpRowByName(?string $name): ?array
    {
        $name = trim((string)$name);
        if ($name === '') return null;
        foreach ($this->exec('dbo.xp_GetWhatFirm11', [])->queryAll(\PDO::FETCH_NUM) as $r) {
            if (trim((string)$r[1]) === $name) return $r;
        }
        return null;
    }

    /** TP.Column(0): код покупателя (IspID) по названию из счёта, 0 — не найден. */
    public function ispOf(?string $name): int
    {
        return (int)($this->tpRowByName($name)[0] ?? 0);
    }

    /** Строка xp_GetWhatFirm11 по коду IspID (у счёта на детали IspFrm хранит код). */
    public function tpRow(?int $tp): ?array
    {
        if ($tp === null) return null;
        foreach ($this->exec('dbo.xp_GetWhatFirm11', [])->queryAll(\PDO::FETCH_NUM) as $r) {
            if ((int)$r[0] === $tp) return $r;
        }
        return null;
    }

    /** Значение с экрана → значение для SQL. false = неверное. */
    public static function normalize(string $type, ?string $v)
    {
        $v = $v === null ? null : trim($v);
        if ($v === null || $v === '') return null;
        switch ($type) {
            case 'num':
                $v = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], $v);
                return is_numeric($v) ? (float)$v : false;
            case 'int':
                return preg_match('/^-?\d+$/', $v) ? (int)$v : false;
            case 'date':   // с экрана приходит 2026-11-19; SQL-логины русские, поэтому отдаём 20261119
                return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate($m[2], $m[3], $m[1]) ? "$m[1]$m[2]$m[3]" : false;
            default:
                return mb_substr($v, 0, 50);
        }
    }

    /** UPDATE dbo.tblPredShceta SET [поле] = ... WHERE PredShcID = id. Имена полей только из своего списка. */
    private function update(int $id, array $values): void
    {
        $allowed = ['НомерСчета', 'ДатаСчета', 'NDS', 'Sm', 'SmNDS', 'AdditSum', 'SumDost', 'DopSum', 'Dst', 'TP', 'Isp', 'Dta2Opl', 'INN', 'Организация'];
        $set = [];
        $bind = [':id' => $id];
        $i = 0;
        foreach ($values as $f => $v) {
            if (!in_array($f, $allowed, true)) {
                throw new \InvalidArgumentException("Поле $f не разрешено");
            }
            $set[] = "[$f] = :p$i";
            $bind[":p$i"] = $v;
            $i++;
        }
        Yii::$app->db->createCommand('UPDATE dbo.tblPredShceta SET ' . implode(', ', $set) . ' WHERE PredShcID = :id', $bind)->execute();
    }

    public function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind  = [];
        foreach (array_values($values) as $i => $v) {
            $bind[$i + 1] = $v;
        }
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc $marks", $bind);
    }
}
