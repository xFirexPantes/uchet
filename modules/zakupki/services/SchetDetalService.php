<?php
namespace app\modules\zakupki\services;

use app\components\AccessFormat as F;
use app\services\LegacySp;
use Yii;

/**
 * Действия карточки «Счёт на детали» (frmScetaDetal, ID < 0). Каждый метод повторяет обработчик VBA формы.
 * Ответ для экрана тот же, что у SchetActionService: ok, msg, reload, close, ask.
 *
 * В Access у этой формы AllowEd = True всегда (проверка GetPermission("FullSch") закомментирована),
 * поэтому править может каждый, кто открыл «Входящие счета». Поля шапки правятся, только пока счёт не закрыт.
 *
 * Шапка хранится в dbo.tblSchDetal (ключ IDSc), её читает xp_ProdPlanGetSch. Правка поля = один UPDATE, как в ADP.
 * Не сделано: «Тип счёта» (CurType), выбор файла счёта двойным щелчком по пути (UpdPthSch копирует файл с диска пользователя на \\TSRV\Docs).
 */
class SchetDetalService
{
    /** Запись счёта (SetRS4SchDetal: exec dbo.xp_ProdPlanGetSch ID, 0, 0). */
    public function rec(int $id): array
    {
        if ($id >= 0) {
            throw new \yii\web\BadRequestHttpException('Это не счёт на детали');
        }
        $rec = $this->exec('dbo.xp_ProdPlanGetSch', [$id, 0, 0])->queryOne();
        if (!$rec) {
            throw new \yii\web\NotFoundHttpException("Счёт $id не найден");
        }
        return $rec;
    }

    /** Поля шапки, которые можно менять с экрана: [поле => тип]. */
    public const FIELDS = [
        'NumSc' => 'text', 'DtaSc' => 'date', 'IspFrm' => 'int', 'Dst' => 'int',
        'NDS' => 'num', 'Sm' => 'num', 'SmNDS' => 'num', 'SumDost' => 'num', 'Dta2Opl' => 'date',
        'TpeScet' => 'int',   // «Тип счёта»: в процедуре это CurType AS TpeScet; обработчика в VBA нет, просто сохраняется
    ];
    /** Поле формы → столбец dbo.tblSchDetal, где они отличаются. */
    private const COLS = ['TpeScet' => 'CurType'];

    /**
     * Сохранить одно поле (AfterUpdate → acCmdSaveRecord). Ответ как у SchetSaveService::saveField: ok, msg, set, lockDst.
     */
    public function saveField(int $id, string $field, ?string $value, bool $withNds = true): array
    {
        $rec = $this->rec($id);
        if (!self::allowEdits($rec)) {
            return ['ok' => false, 'msg' => 'Счёт закрыт'];
        }
        if (!isset(self::FIELDS[$field])) {
            return ['ok' => false, 'msg' => 'Это поле нельзя менять'];
        }
        $v = SchetSaveService::normalize(self::FIELDS[$field], $value);
        if ($v === false) {
            return ['ok' => false, 'msg' => 'Неверное значение'];
        }
        switch ($field) {
            case 'Sm':          // Sm_AfterUpdate: SmNDS = dhRound(Sm * nds / (100 + nds), 2)
                $nds = (float)($rec['NDS'] ?? 0);
                $smNds = $v === null ? null : round($v * $nds / (100 + $nds), 2);
                $this->update($id, ['Sm' => $v, 'SmNDS' => $smNds]);
                return ['ok' => true, 'set' => ['SmNDS' => $smNds === null ? '' : str_replace('.', ',', (string)$smNds)]];

            case 'SumDost':     // SumDost_AfterUpdate: AddNDS(Nz(SumDost, 0)) — «Указанная сумма с НДС?», ставка 22
                $v = $v ?? 0;
                if ($v != 0 && !$withNds) $v = round($v * 122 / 100, 2);
                $this->update($id, ['SumDost' => $v]);
                return ['ok' => true, 'set' => ['SumDost' => str_replace('.', ',', (string)$v)]];

            case 'Dst':
                if (!in_array($v, [0, 1, 255], true)) return ['ok' => false, 'msg' => 'Неверный склад'];
                break;

            case 'TpeScet':     // Стандартный 1, Услуги 2, Не сопоставлен 0
                if (!in_array($v, [0, 1, 2], true)) return ['ok' => false, 'msg' => 'Неверный тип счёта'];
                break;

            case 'IspFrm':      // IspFrm_AfterUpdate: покупатель 1 или 8 → склад из списка и запереть, иначе сброс
                $tp = (new SchetSaveService())->tpRow($v);
                if ($tp === null || !in_array((int)$tp[0], [1, 8], true)) {
                    $this->update($id, ['IspFrm' => null, 'Dst' => 255]);
                    return ['ok' => false, 'msg' => 'Нельзя выбрать этого покупателя', 'set' => ['IspFrm' => '', 'Dst' => 255], 'lockDst' => false];
                }
                $dst = (int)($tp[2] ?? 255);
                $this->update($id, ['IspFrm' => $v, 'Dst' => $dst]);
                return ['ok' => true, 'set' => ['Dst' => $dst], 'lockDst' => true, 'reload' => true];   // накладные зависят от покупателя
        }
        $this->update($id, [$field => $v]);
        $res = ['ok' => true];
        // NumSc_AfterUpdate: CheckSchIn1C(ID, 1); DtaSc_GotFocus: CheckSchIn1C(ID). Оба — если есть и номер, и дата.
        if ($field === 'NumSc' || $field === 'DtaSc') {
            $rec = $this->rec($id);
            if ($rec['NumSc'] !== null && $rec['DtaSc'] !== null) {
                $msgs = [];
                if ($this->in1C($id, $field === 'NumSc' ? 1 : 0)) $msgs[] = 'Такой счёт уже существует в 1С';
                if ($this->similar($id, $rec)) $msgs[] = 'Уже существует подобный счёт';
                $res['msg'] = $msgs ? implode("\n", $msgs) : null;
            }
        }
        return $res;
    }

    /** butDob_Click до окна: CheckNumSch(False) — сообщение; иначе окно frmScetaDetalAdd с IDPos 0 (ответ det: true). */
    public function addPos(int $id): array
    {
        $rec = $this->rec($id);
        if (!self::allowEdits($rec)) return ['ok' => false, 'msg' => 'Счёт закрыт'];
        if ($txt = $this->checkNumSch($rec)) return ['ok' => false, 'msg' => $txt];
        return ['ok' => true, 'det' => true];
    }

    /** butSaveAs_Click: CheckNumSch(False) → сообщение; затем CheckSimilarSchDetal (сообщение). Запись уже сохранена. */
    public function saveAs(int $id): array
    {
        $rec = $this->rec($id);
        $msgs = [];
        if ($txt = $this->checkNumSch($rec)) $msgs[] = $txt;
        if ($this->similar($id, $rec)) $msgs[] = 'Уже существует подобный счёт';
        return ['ok' => !$msgs, 'msg' => $msgs ? implode("\n", $msgs) : null];
    }

    /**
     * DelRec_Click: «Удалить позицию из счёта?» → CommonStartSP1(IDPos, "dbo.xp_ProdPlanDelPosSch", 0).
     * Проверка ExistNaklDetal в Access пустая (всегда 0), поэтому накладные не проверяются.
     */
    public function delPos(int $id, int $idPos, array $answers): array
    {
        $rec = $this->rec($id);
        if (!self::allowEdits($rec)) {
            return ['ok' => false, 'msg' => 'Счёт закрыт'];
        }
        $ids = array_map(fn($r) => (int)$r['IDPos'], $this->exec('dbo.xp_ProdPlanGetPosSch1', [$id])->queryAll());
        if (!$ids) {
            return ['ok' => false, 'msg' => 'Нет позиций для удаления'];
        }
        if (!in_array($idPos, $ids, true)) {
            return ['ok' => false, 'msg' => 'Выберите позицию'];
        }
        if (!isset($answers['del'])) {
            return ['ask' => ['key' => 'del', 'text' => 'Удалить позицию из счёта?']];
        }
        if (!$answers['del']) {
            return ['ok' => true];
        }
        if (LegacySp::sp1($idPos, 'dbo.xp_ProdPlanDelPosSch', 0) > 0) {
            return ['ok' => true, 'reload' => true];
        }
        return ['ok' => false, 'msg' => 'Не удалось удалить позицию из счёта'];
    }

    /** OpnSch_Click: IsClosed = Not IsClosed, сохранить. */
    public function reopen(int $id): array
    {
        $rec = $this->rec($id);
        $this->update($id, ['IsClosed' => F::bool($rec['IsClosed'] ?? 0) ? 0 : 1]);
        return ['ok' => true, 'reload' => true];
    }

    /** CheckSchIn1C: xp_IsExistSchIn1CNew (ID, Rgm, res OUTPUT). */
    private function in1C(int $id, int $rgm): bool
    {
        try {
            return (bool)Yii::$app->db->createCommand(
                'SET NOCOUNT ON; DECLARE @r int; EXEC dbo.xp_IsExistSchIn1CNew ?, ?, @r OUTPUT; SELECT @r', [1 => $id, 2 => $rgm]
            )->queryScalar();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return false;
        }
    }

    /** UPDATE dbo.tblSchDetal SET [поле] = ... WHERE IDSc = id. Имена полей только из своего списка. */
    private function update(int $id, array $values): void
    {
        $allowed = array_merge(array_keys(self::FIELDS), ['IsClosed']);
        $set = [];
        $bind = [':id' => $id];
        $i = 0;
        foreach ($values as $f => $v) {
            if (!in_array($f, $allowed, true)) {
                throw new \InvalidArgumentException("Поле $f не разрешено");
            }
            $set[] = '[' . (self::COLS[$f] ?? $f) . "] = :p$i";
            $bind[":p$i"] = $v;
            $i++;
        }
        Yii::$app->db->createCommand('UPDATE dbo.tblSchDetal SET ' . implode(', ', $set) . ' WHERE IDSc = :id', $bind)->execute();
    }

    /** Можно ли править поля шапки: Me.AllowEdits = AllowEd And IsClosed = False. */
    public static function allowEdits(array $rec): bool
    {
        return !F::bool($rec['IsClosed'] ?? 0);
    }

    /** GetSost("ScetDetal", ID) = CommonStartSP6(ID, "dbo.xp_GetSostDoc", "ScetDetal", -1). */
    public static function sost(int $id): int
    {
        return LegacySp::sp6($id, 'dbo.xp_GetSostDoc', 'ScetDetal', -1);
    }

    /** Flag1C: IsTo1C = (Sost <> 0 Or OnEdit = 2), IsTo1C.Enabled = Not Sost > 1, NotUsed = (Sost = 5). */
    public static function to1CState(int $sost, array $rec): array
    {
        return ['on' => $sost !== 0 || (int)($rec['OnEdit'] ?? 0) === 2, 'enabled' => $sost <= 1, 'notUsed' => $sost === 5];
    }

    /** NotUsed_MouseDown: CommonStartSP1(ID, "dbo.xp_frmMassInputSetNotUsed", 0) — та же процедура, что у элементов. */
    public function notUsed(int $id): array
    {
        $this->rec($id);
        LegacySp::sp1($id, 'dbo.xp_frmMassInputSetNotUsed', 0);
        return ['ok' => true, 'reload' => true];
    }

    /**
     * IsTo1C_MouseDown → SwitchSost("ScetDetal", ID, IsTo1C): xp_SwitchSostRepl (ID, "ScetDetal0|1", res).
     * Проверка CheckOpl в Access не срабатывает (функция объявлена As Boolean, Case 1/2 не совпадают), поэтому её нет и здесь.
     */
    public function to1C(int $id): array
    {
        $st = self::to1CState(self::sost($id), $this->rec($id));
        if (!$st['enabled']) {
            return ['ok' => false, 'msg' => 'Счёт уже выгружен, состояние менять нельзя'];
        }
        $tmp = $st['on'] ? 0 : 1;
        if (LegacySp::sp6($id, 'dbo.xp_SwitchSostRepl', 'ScetDetal' . $tmp, 0, 100) > 0) {
            return ['ok' => true, 'reload' => true];
        }
        return ['ok' => false, 'msg' => 'Не удалось переключить состояние'];
    }

    /**
     * PrcWithNDS_MouseDown / SchWithNDS_MouseDown: CommonStartSP4(ID, "dbo.xp_ProdPlanSchUpdNDS", 1|0, 0, , adInteger, adInteger).
     * $what = 1 — «Цена с НДС», 0 — «Счёт с НДС».
     */
    public function withNds(int $id, int $what): array
    {
        $this->rec($id);
        if (LegacySp::sp4($id, 'dbo.xp_ProdPlanSchUpdNDS', $what, 0, 'int', 'int') > 0) {
            return ['ok' => true, 'reload' => true];
        }
        return ['ok' => false, 'msg' => 'Не удалось изменить состояние флажка'];
    }

    /**
     * MassSetDTPost_Click / MassSetDTDP_Click → DetalMassDate(Dta, Rgm): xp_DetalMassDate (IDSch, Dta varchar(10), rgm).
     * Rgm 0 — «Поставка» (DTPost), 1 — «ДП» (DPDT). Дата уходит текстом дд.мм.гггг, как из поля Access.
     */
    public function massDate(int $id, int $rgm, ?string $date, array $answers): array
    {
        $this->rec($id);
        if (!$date || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate($m[2], $m[3], $m[1])) {
            return ['ok' => true];                                   // If IsDate(...) = False Then Exit Sub
        }
        if (!isset($answers['all'])) {
            return ['ask' => ['key' => 'all', 'text' => 'Установить дату для всех строк?']];
        }
        if (!$answers['all']) {
            return ['ok' => true];
        }
        try {
            $this->exec('dbo.xp_DetalMassDate', [$id, "$m[3].$m[2].$m[1]", $rgm])->execute();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return ['ok' => false, 'msg' => 'Не удалось установить дату'];
        }
        return ['ok' => true, 'reload' => true];
    }

    /**
     * pth_KeyDown / AdditPth_KeyDown (Delete): «Удалить путь к счёту?» → CommonStartSP6(ID, "dbo.xp_UpdatePthSch[2]", "", 0).
     * $addit = false — Pth (xp_UpdatePthSch), true — AdditPth (xp_UpdatePthSch2).
     */
    public function pathDel(int $id, bool $addit, array $answers): array
    {
        $rec = $this->rec($id);
        if (trim((string)($rec[$addit ? 'AdditPth' : 'Pth'] ?? '')) === '') {
            return ['ok' => true];
        }
        if (!isset($answers['del'])) {
            return ['ask' => ['key' => 'del', 'text' => 'Удалить путь к счёту?']];
        }
        if (!$answers['del']) {
            return ['ok' => true];
        }
        if (LegacySp::sp6($id, $addit ? 'dbo.xp_UpdatePthSch2' : 'dbo.xp_UpdatePthSch', '', 0) > 0) {
            return ['ok' => true, 'reload' => true];
        }
        return ['ok' => false, 'msg' => 'Не удалось удалить путь к счёту'];
    }

    /**
     * xListINN_DblClick: CommonStartSP6(ID, "dbo.xp_ProdPlanSchUpdFirma", Trim(ID фирмы), 0, , 12).
     * В отличие от счёта на элементы, фирму записывает процедура, а не форма.
     */
    public function setFirm(int $id, int $firmId): array
    {
        $this->rec($id);
        if ($firmId <= 0) {
            return ['ok' => false, 'msg' => 'Фирма не выбрана'];
        }
        if (LegacySp::sp6($id, 'dbo.xp_ProdPlanSchUpdFirma', (string)$firmId, 0, 12) > 0) {
            return ['ok' => true, 'reload' => true];
        }
        return ['ok' => false, 'msg' => 'Не удалось установить контрагента'];
    }

    /**
     * TakeMe_MouseDown → SwitchTakeMeAllDetal(ID, TakeMe, 0): dbo.xp_UpdateTakeMeDateDetal (Rgm, ID, dta, SndMsg OUTPUT).
     * Ставим: дата и время (ЧЧММ), снимаем: 01.01.2000. SndMsg в Access уходил письмом «Неоплаченный счёт на детали» на GetAdr(17).
     */
    public function takeMe(int $id, bool $on, ?string $date, ?string $time): array
    {
        $this->rec($id);
        return (new SchetActionService())->takeMeRun($id, $on, $date, $time, 'dbo.xp_UpdateTakeMeDateDetal', 'Неоплаченный счёт на детали');
    }

    /**
     * butExit_Click формы frmScetaDetal. Порядок как в VBA; вопросы уходят на экран (ask), экран повторяет вызов с ответами.
     */
    public function close(int $id, array $answers): array
    {
        $rec  = $this->rec($id);
        $info = [];
        $done = function () use (&$info) {
            return ['ok' => true, 'close' => true, 'msg' => $info ? implode("\n\n", $info) : null];
        };
        $notUsed = self::sost($id) === 5;

        // CheckDt: xp_ProdPlanScetCheckDT (IDSc, FixDT OUTPUT)
        $fx = LegacySp::sp1($id, 'dbo.xp_ProdPlanScetCheckDT', 0);
        if ($fx === 1 || $fx === 2) {
            if (!isset($answers['dt'])) {
                return ['ask' => ['key' => 'dt', 'text' => $fx === 1
                    ? "Поле 'Поставка' и/или поле 'ДП' не заполнено. Все равно выйти?"
                    : "Дата в поле 'Поставка' и/или в поле 'ДП' превышает дату ЖД детали. Все равно выйти?"]];
            }
            if (!$answers['dt']) return ['ok' => false];
        }
        // CheckSimilarSchDetal(True): номер очищается, форма не закрывается
        if ($this->similar($id, $rec)) {
            $this->update($id, ['NumSc' => '']);
            return ['ok' => false, 'msg' => 'Уже существует подобный счёт. Номер счёта очищен', 'reload' => true];
        }
        if (!F::bool($rec['IsClosed'] ?? 0)) {
            if (trim((string)($rec['UchetID'] ?? '')) === '' && self::to1CState(self::sost($id), $rec)['on']) {
                return ['ok' => false, 'msg' => 'Укажите ИНН!'];
            }
            if ((int)($rec['Dst'] ?? 255) === 255) $info[] = 'Укажите склад';   // в Access только сообщение, выход продолжается
        }
        // CloseSchDetal: CheckNaklDetal = xp_ProdPlanGetSostSch (default -1); 0 → xp_CloseSchDetal
        if (LegacySp::sp1($id, 'dbo.xp_ProdPlanGetSostSch', -1) === 0 && LegacySp::sp1($id, 'dbo.xp_CloseSchDetal', 0) === 0) {
            $info[] = 'Не удалось закрыть счёт';
        }
        // xp_frmMassInputSchChk: 1 — нет позиций, 3 — счёт уже в 1С
        $chk = LegacySp::sp1($id, 'dbo.xp_frmMassInputSchChk', 0);
        $markNotUsed = function (string $key) use ($id, $answers) {
            if (!isset($answers[$key])) {
                return ['ask' => ['key' => $key, 'text' => 'Счёт уже в 1С. Удаление невозможно. Отметить его как неиспользуемый?']];
            }
            if ($answers[$key]) LegacySp::sp6($id, 'dbo.xp_SwitchSostRepl', 'ScetDetal5', 0, 100);
            return null;
        };
        if ($chk === 1) {
            if ($notUsed) return $done();
            if (!isset($answers['nopos'])) {
                return ['ask' => ['key' => 'nopos', 'text' => 'Счёт не имеет позиций. Удалить?']];
            }
            if (!$answers['nopos']) return $done();
            $del = LegacySp::sp1($id, 'dbo.xp_ProdPlanSchDel', 0);
            if ($del === 1) return $done();
            if ($del === 3) { if ($a = $markNotUsed('nopos1c')) return $a; return $done(); }
            // другой ответ процедуры: в Access выход продолжается дальше
        } elseif ($chk === 3) {
            if (!$notUsed && ($a = $markNotUsed('in1c'))) return $a;
            return $done();
        }
        // TstSum(..., Detal:=True): сумма по позициям (CountSumSchDetal) + доставка; прочих расходов у деталей нет
        $pos = round((float)LegacySp::sp1($id, 'dbo.xp_CountSumScDetal', 0, 'float'), 2);
        if ($t = SchetActionService::sumDiff($pos, (float)($rec['SumDost'] ?? 0), 0, (float)($rec['Sm'] ?? 0))) $info[] = $t;

        // CheckNumSch(False). В Access ответ «Нет» на «Сохранить изменения?» УДАЛЯЕТ счёт (xp_ProdPlanSchDel).
        if ($txt = $this->checkNumSch($rec)) {
            if (!isset($answers['save'])) {
                return ['ask' => ['key' => 'save', 'text' => "$txt.\nСохранить изменения?\n(«Отмена» удалит счёт, как в Access)"]];
            }
            if (!$answers['save']) {
                $del = LegacySp::sp1($id, 'dbo.xp_ProdPlanSchDel', 0);
                if ($del === 1) return $done();
                if ($del === 3) { $info[] = 'Счёт уже в 1С. Удаление невозможно'; return $done(); }
            }
            return ['ok' => false, 'msg' => $txt];
        }

        $sm = (float)($rec['Sm'] ?? 0);
        $smNds = $rec['SmNDS'] ?? null;
        if ($sm > 0 && $smNds !== null && (float)$smNds >= 0) {
            return $done();
        }
        if (!isset($answers['nosum'])) {
            return ['ask' => ['key' => 'nosum', 'text' => 'Не указана сумма счёта или сумма НДС. Всё равно выйти?'], 'msg' => $info ? implode("\n\n", $info) : null];
        }
        return $answers['nosum'] ? $done() : ['ok' => false];
    }

    /** CheckSimilarSchDetal: xp_IsExistSimilarSch (ID, "номер;дд.мм.гггг", res), ln 255. */
    private function similar(int $id, array $rec): bool
    {
        $num = $rec['NumSc'] ?? null;
        if ($num === null) return false;
        $dt = $rec['DtaSc'] ?? null;
        $d = is_string($dt) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $dt, $m) ? "$m[3].$m[2].$m[1]" : '';
        return LegacySp::sp6($id, 'dbo.xp_IsExistSimilarSch', trim((string)$num) . ';' . $d, 0, 255) > 0;
    }

    /** CheckNumSch(False) для счёта на детали по коду — текст ошибки или null. */
    public function numSchError(int $id): ?string
    {
        return $this->checkNumSch($this->rec($id));
    }

    /** CheckNumSch(False) для frmScetaDetal. */
    private function checkNumSch(array $rec): ?string
    {
        if (($rec['UchetID'] ?? null) === null) return 'Не указан продавец';
        if (($rec['IspFrm'] ?? null) === null) return 'Не указан покупатель';
        if (($rec['NumSc'] ?? null) === null) return 'Не указан номер счёта';
        if (($rec['DtaSc'] ?? null) === null) return 'Не указана дата счёта';
        return null;
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
