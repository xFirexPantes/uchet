<?php
namespace app\modules\zakupki\services;

use app\components\AccessFormat as F;
use app\services\LegacySp;
use Yii;

/**
 * Действия карточки счёта на элементы (frmMassInputSch), шаг Б2: флажки, «Ставка НДС», закрытие счёта.
 * Каждый метод повторяет обработчик VBA; ссылка на него в комментарии.
 *
 * Ответ для экрана: ok, msg (MsgBox), reload (перечитать карточку), close (закрыть вкладку),
 * ask = ['key' => ..., 'text' => ...] — нужен ответ Да/Нет; экран повторяет запрос с answers[key] = 1|0.
 */
class SchetActionService
{
    private SchetSaveService $save;

    public function __construct()
    {
        $this->save = new SchetSaveService();
    }

    /** NotUsed_MouseDown: CommonStartSP1(ID, "dbo.xp_frmMassInputSetNotUsed", 0, , , , False), потом перечитать. */
    public function notUsed(int $id): array
    {
        $this->save->rec($id);
        LegacySp::sp1($id, 'dbo.xp_frmMassInputSetNotUsed', 0);
        return ['ok' => true, 'reload' => true];
    }

    /** IsTo1C_MouseDown → SwitchSost("InputScet", ID, IsTo1C): xp_SwitchSostRepl (ID, "InputScet0|1", res). */
    public function to1C(int $id): array
    {
        $rec = $this->save->rec($id);
        if ((int)($rec['Repl'] ?? 0) > 1) {                         // Flag1C: IsTo1C.Enabled = Not Repl > 1
            return ['ok' => false, 'msg' => 'Счёт уже выгружен, состояние менять нельзя'];
        }
        $tmp = (int)($rec['Repl'] ?? 0) !== 0 ? 0 : 1;               // IsTo1C = Repl <> 0, переключаем
        if (LegacySp::sp6($id, 'dbo.xp_SwitchSostRepl', 'InputScet' . $tmp, 0, 100) > 0) {
            return ['ok' => true, 'reload' => true];
        }
        return ['ok' => false, 'msg' => 'Не удалось переключить состояние'];
    }

    /**
     * TakeMe_MouseDown → SwitchTakeMeAll(ID, TakeMe, 0): xp_UpdateTakeMeDateNew (Rgm, ID, dta, SndMsg OUTPUT).
     * Ставим флажок: нужна дата и время (ЧЧММ), снимаем: дата 01.01.2000.
     * Если процедура вернула текст SndMsg, Access отправлял письмо «Неоплаченный счёт»; здесь пока показываем текст.
     */
    public function takeMe(int $id, bool $on, ?string $date, ?string $time): array
    {
        $this->save->rec($id);
        return $this->takeMeRun($id, $on, $date, $time, 'dbo.xp_UpdateTakeMeDateNew', 'Неоплаченный счёт');
    }

    /**
     * Общая часть SwitchTakeMeAll / SwitchTakeMeAllDetal: процедура (Rgm 0, ID, dta, SndMsg OUTPUT) в транзакции.
     * Права и запись счёта проверяет вызывающий.
     */
    public function takeMeRun(int $id, bool $on, ?string $date, ?string $time, string $proc, string $subject): array
    {
        if ($on) {
            if (!$date || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
                return ['ok' => false, 'msg' => 'Не указана дата'];
            }
            $dta = "$m[1]$m[2]$m[3]";
            $msg = null;
            if ($time !== null && $time !== '') {
                if (!preg_match('/^\d{4}$/', $time)) {
                    $msg = 'Неверно указано время. Будет записана только дата';
                } else {
                    $dta .= ' ' . substr($time, 0, 2) . ':' . substr($time, 2, 2);
                }
            }
        } else {
            $dta = '20000101';
            $msg = null;
        }
        $t = Yii::$app->db->beginTransaction();
        try {
            $snd = Yii::$app->db->createCommand(
                "SET NOCOUNT ON; DECLARE @m nvarchar(500); EXEC $proc 0, ?, ?, @m OUTPUT; SELECT @m",
                [1 => $id, 2 => $dta]
            )->queryScalar();
            $t->commit();
        } catch (\yii\db\Exception $e) {
            $t->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            return ['ok' => false, 'msg' => 'Не удалось изменить состояние'];
        }
        if ($snd) {   // SendEMail(GetAdr(17), "", тема, SndMsg): при ошибке — MsgBox с её текстом
            $r = \app\components\LegacyMail::send(\app\components\LegacyMail::adr(17), $subject, $snd);
            if (!str_starts_with($r, \app\components\LegacyMail::OK)) {
                $msg = trim(($msg ? "$msg\n\n" : '') . $r);
            }
        }
        return ['ok' => true, 'msg' => $msg, 'reload' => true];
    }

    /**
     * flgFZ275_MouseUp: CommonStartSP4(ID, "dbo.xp_frmMassInputSchFZ275", Num, , , adInteger, adInteger).
     * Ставим флажок: Num = код заказа, выбранный в frmfndFirm (режим 50, xp_FZ275GetZakas). Снимаем: Num = 0.
     */
    public function fz275(int $id, ?int $num): array
    {
        $rec = $this->save->rec($id);
        if (F::bool($rec['flgFZ275'] ?? 0)) {
            $num = 0;
        } elseif (!$num) {
            return ['ok' => false, 'msg' => 'Не выбран заказ'];
        }
        if (LegacySp::sp4($id, 'dbo.xp_frmMassInputSchFZ275', $num, 0, 'int', 'int') > 0) {
            return ['ok' => true, 'reload' => true];
        }
        return ['ok' => false, 'msg' => 'Не удалось изменить значение флажка'];
    }

    /** SetNDS2Str_Click (ТЗ 670): ставка НДС счёта всем строкам → CommonStartSP1(ID, "dbo.xp_frmMassInputUpdNDSPos", 0). */
    public function ndsAll(int $id, array $answers): array
    {
        $rec = $this->save->rec($id);
        $hasPos = (bool)$this->save->exec('dbo.xp_LstMassInputRowS1', [$id])->queryOne();
        if (!$hasPos || (float)($rec['NDS'] ?? -1) < 0) {
            return ['ok' => true];
        }
        if (!isset($answers['nds'])) {
            return ['ask' => ['key' => 'nds', 'text' => 'Установить указанную ставку всем строкам счёта?']];
        }
        if (!$answers['nds']) {
            return ['ok' => true];
        }
        LegacySp::sp1($id, 'dbo.xp_frmMassInputUpdNDSPos', 0);
        return ['ok' => true, 'reload' => true];
    }

    /**
     * butExit_Click: проверки и закрытие счёта. Повторяет порядок VBA. Вопросы (MsgBox Да/Нет) уходят на экран,
     * экран повторяет вызов с ответами; шаги до вопроса безопасно выполнять повторно.
     * Только для того, кто может править счёт (у остальных ✕ просто закрывает вкладку, как Form_Close без правок).
     */
    public function close(int $id, array $answers): array
    {
        $rec  = $this->save->rec($id);
        $info = [];                                                  // информационные MsgBox, показываем в конце
        $done = function (array $extra = []) use (&$info) {
            return ['ok' => true, 'close' => true, 'msg' => $info ? implode("\n\n", $info) : null] + $extra;
        };

        if ((int)($rec['wrongNDS'] ?? 0) === 1) {
            return ['ok' => false, 'msg' => 'Ошибочное сочетание ставки НДС в счете'];
        }
        // CheckSimilarSch(True): в Access номер очищается и форма не закрывается
        if ($this->save->similar($id)) {
            return ['ok' => false, 'msg' => 'Уже существует подобный счёт. Исправьте номер счёта'];
        }
        if (!F::bool($rec['IsClosed'] ?? 0)) {
            if (trim((string)($rec['INN'] ?? '')) === '' && (int)($rec['Repl'] ?? 0) !== 0) {
                return ['ok' => false, 'msg' => 'Укажите ИНН!'];
            }
            if ((int)($rec['Dst'] ?? 255) === 255) {
                return ['ok' => false, 'msg' => 'Укажите склад'];
            }
        }
        $closeErr = $this->closeSch($id);
        if ($closeErr) $info[] = $closeErr;

        $res = LegacySp::sp2($id, 'dbo.xp_frmMassInputSchChkNew', '  ', 1024);
        switch (substr($res, 0, 2)) {
            case 'w1':
                if (!isset($answers['w1'])) {
                    return ['ask' => ['key' => 'w1', 'text' => 'Счёт не имеет позиций. Удалить?']];
                }
                if ($answers['w1']) {
                    $del = LegacySp::sp1($id, 'dbo.xp_frmMassInputSchDel', 0);
                    if ($del === 3) {
                        if (!isset($answers['w1nu'])) {
                            return ['ask' => ['key' => 'w1nu', 'text' => 'Счёт уже в 1С. Удаление невозможно. Отметить его как неиспользуемый?']];
                        }
                        if ($answers['w1nu']) LegacySp::sp1($id, 'dbo.xp_frmMassInputSetNotUsed', 0);
                    }
                    return $done();
                }
                break;
            case 'w3':
                if (!F::bool($rec['NotUsed'] ?? 0)) {
                    if (!isset($answers['w3'])) {
                        return ['ask' => ['key' => 'w3', 'text' => 'Счёт уже в 1С. Удаление невозможно. Отметить его как неиспользуемый?']];
                    }
                    if ($answers['w3']) LegacySp::sp1($id, 'dbo.xp_frmMassInputSetNotUsed', 0);
                }
                return $done();
            case 'w5':
                $info[] = 'В счёте есть незаполненные ID поставщика';
                break;
            case 'w6':
                $nakl = $this->save->exec('dbo.xp_LstNaklRowS1', [(string)($rec['Организация'] ?? ''), (string)($rec['TP'] ?? ''), $id])->queryAll();
                if (!$nakl) {
                    return ['ok' => false, 'msg' => 'Необходимо заменить контрагента в счёте'];
                }
                break;
            case 'er':
                if (!isset($answers['er'])) {
                    return ['ask' => ['key' => 'er', 'text' => 'Компоненты ' . substr($res, 2) . "не соответствуют покупателю по принадлежности\nВсё равно выйти?"]];
                }
                if (!$answers['er']) return ['ok' => false];
                break;
        }

        if ($t = $this->tstSum($id, $rec)) $info[] = $t;

        // CheckNumSch: без продавца, покупателя, номера или даты счёт сохранять нельзя.
        // В Access при ответе «Нет» на «Сохранить изменения?» счёт УДАЛЯЕТСЯ (xp_frmMassInputSchDel).
        if ($txt = $this->checkNumSch($rec)) {
            if (!isset($answers['save'])) {
                return ['ask' => ['key' => 'save', 'text' => "$txt.\nСохранить изменения?\n(«Отмена» удалит счёт, как в Access)"]];
            }
            if (!$answers['save']) {
                $del = LegacySp::sp1($id, 'dbo.xp_frmMassInputSchDel', 0);
                if ($del === 1) return $done();
                if ($del === 3) { $info[] = 'Счёт уже в 1С. Удаление невозможно'; return $done(); }
            }
            return ['ok' => false, 'msg' => $txt];
        }

        // если дата изменения пустая — проставить, как в Access (Дата, Время, UserCur, DateSosd)
        Yii::$app->db->createCommand(
            'UPDATE dbo.tblPredShceta SET Дата = ISNULL(Дата, GETDATE()), Время = CASE WHEN Дата IS NULL THEN GETDATE() ELSE Время END,
                UserCur = CASE WHEN Дата IS NULL THEN CURRENT_USER ELSE UserCur END, DateSosd = ISNULL(DateSosd, GETDATE())
             WHERE PredShcID = :id', [':id' => $id]
        )->execute();

        $sm = (float)($rec['Sm'] ?? 0);
        $smNds = $rec['SmNDS'] ?? $rec['smNDS'] ?? null;
        if ($sm > 0 && $smNds !== null && (float)$smNds >= 0) {
            return $done();
        }
        // по письму Реннике от 11.10.21
        if ((int)($rec['InUE'] ?? 0) > 0 && $sm > 0 && trim((string)($rec['INN'] ?? '')) === '7825492099') {
            return $done();
        }
        if (!isset($answers['nosum'])) {
            return ['ask' => ['key' => 'nosum', 'text' => 'Не указана сумма счёта или сумма НДС. Всё равно выйти?'], 'msg' => $info ? implode("\n\n", $info) : null];
        }
        return $answers['nosum'] ? $done() : ['ok' => false];
    }

    /**
     * pth_KeyDown / AdditPth_KeyDown (Delete): CommonStartSP6(ID, "dbo.xp_UpdatePthSch[2]", "", 0).
     * (В Access у доп. счёта проверяется пустота основного пути — опечатка; здесь проверяем свой путь.)
     */
    public function pathDel(int $id, bool $addit, array $answers): array
    {
        $rec = $this->save->rec($id);
        if (trim((string)($rec[$addit ? 'AdditPth' : 'Pth'] ?? '')) === '') {
            return ['ok' => true];
        }
        if (!isset($answers['del'])) {
            return ['ask' => ['key' => 'del', 'text' => $addit ? 'Удалить путь к доп.счёту?' : 'Удалить путь к счёту?']];
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
     * DelRec_Click: удалить позицию счёта.
     *   ExistNakl (xp_fn_ExistNakl > 0) → нельзя; «Удалить позицию из счёта?»;
     *   не давальческий элемент (xp_IsDavEl = 0) → причина из frmfndZac, режим 19 (ТЗ 522), иначе причина "0";
     *   CommonStartSP7(AllID, "dbo.xp_DelPosSch", причина, , , , 255).
     * Ответ pick = открыть окно выбора на экране и повторить запрос с выбранным значением.
     */
    public function delPos(int $id, int $allId, ?string $reason, array $answers): array
    {
        $rec = $this->save->rec($id);
        if (F::bool($rec['IsClosed'] ?? 0)) {
            return ['ok' => false, 'msg' => 'Счёт закрыт'];
        }
        $row = null;
        foreach ($this->save->exec('dbo.xp_LstMassInputRowS1', [$id])->queryAll() as $r) {
            if ((int)$r['AllID'] === $allId) { $row = $r; break; }
        }
        if ($row === null) {
            return ['ok' => false, 'msg' => 'Нет позиций для удаления'];
        }
        if (LegacySp::sp1($allId, 'dbo.xp_fn_ExistNakl', 0) > 0) {
            return ['ok' => false, 'msg' => 'По данному элементу уже есть накладная. Удаление невозможно'];
        }
        if (!isset($answers['del'])) {
            return ['ask' => ['key' => 'del', 'text' => 'Удалить позицию из счёта?']];
        }
        if (!$answers['del']) {
            return ['ok' => true];
        }
        if (LegacySp::sp1((int)$row['SkladID'], 'dbo.xp_IsDavEl', 0) === 0) {
            if ($reason === null) {
                return ['pick' => ['form' => 'frmfndZac', 'mode' => 19, 'field' => 'reason', 'empty' => 'Не указана причина']];
            }
            if (trim($reason) === '') {
                return ['ok' => false, 'msg' => 'Не указана причина'];
            }
        } else {
            $reason = '0';
        }
        LegacySp::sp7($allId, 'dbo.xp_DelPosSch', $reason, '', 255);
        return ['ok' => true, 'reload' => true];
    }

    /** OpnSch_Click: IsClosed = Not IsClosed, Дата/Время/UserCur = сейчас и текущий пользователь. */
    public function reopen(int $id): array
    {
        $this->save->rec($id);
        Yii::$app->db->createCommand(
            'UPDATE dbo.tblPredShceta SET IsClosed = CASE WHEN IsClosed <> 0 THEN 0 ELSE 1 END,
                Дата = GETDATE(), Время = GETDATE(), UserCur = CURRENT_USER WHERE PredShcID = :id', [':id' => $id]
        )->execute();
        return ['ok' => true, 'reload' => true];
    }

    /** butSaveAs_Click: CheckNumSch; если всё указано — Дата/Время/UserCur, DateSosd если пусто; потом CheckSimilarSch. */
    public function saveAs(int $id): array
    {
        $rec = $this->save->rec($id);
        $msgs = [];
        if ($txt = $this->checkNumSch($rec)) {
            $msgs[] = $txt;
        } else {
            Yii::$app->db->createCommand(
                'UPDATE dbo.tblPredShceta SET Дата = GETDATE(), Время = GETDATE(), UserCur = CURRENT_USER,
                    DateSosd = ISNULL(DateSosd, GETDATE()) WHERE PredShcID = :id', [':id' => $id]
            )->execute();
        }
        if ($this->save->similar($id)) $msgs[] = 'Уже существует подобный счёт';
        return ['ok' => !$msgs || !$txt, 'msg' => $msgs ? implode("\n", $msgs) : null, 'reload' => !$txt];
    }

    /** CloseSch: CheckNakl = 0 или 2 → xp_CloseSch. Возвращает текст ошибки или null. */
    private function closeSch(int $id): ?string
    {
        $r = $this->checkNakl($id);
        if ($r === 0 || $r === 2) {                                   // 2 — по письму Семёнова от 28.04.22
            if (LegacySp::sp1($id, 'dbo.xp_CloseSch', 0) === 0) {
                return 'Не удалось закрыть счёт';
            }
        }
        return null;
    }

    /**
     * CheckNakl для счёта на элементы (ID > 0), одним запросом вместо FillTmpEl + #TmptblEl:
     *   3 — счёт уже закрыт; 2 — в счёте нет позиций; 1 — по какой-то позиции на склад пришло не всё количество;
     *   0 — всё пришло; 4 — есть накладная с номером «-...» при Repl > 0 (перекрывает 0/1/2).
     */
    public function checkNakl(int $id): int
    {
        if ($id < 0) {
            return LegacySp::sp1($id, 'dbo.xp_GetSostSch', -1);
        }
        $db = Yii::$app->db;
        if ($db->createCommand('SELECT COUNT(*) FROM dbo.tblPredShceta WHERE PredShcID = :id AND IsClosed <> 0', [':id' => $id])->queryScalar() > 0) {
            return 3;
        }
        // по каждому SkladID: заказано в счёте (tblSchEl) и пришло на склад по накладным счёта (tblAll, InSklad = 1)
        $rows = $db->createCommand(
            'SELECT e.SkladID, e.SumKol, ISNULL(n.Kol, -1) AS Kol
               FROM (SELECT SkladID, SUM(Количество) AS SumKol FROM dbo.tblSchEl WHERE ShcID = :id GROUP BY SkladID) e
               LEFT JOIN (SELECT a.SkladID, SUM(a.Количество) AS Kol
                            FROM dbo.tblAll a INNER JOIN dbo.tblShceta s ON s.ShcID = a.ShcID
                           WHERE s.PrSchID = :id2 AND a.InSklad = 1
                           GROUP BY a.SkladID) n ON n.SkladID = e.SkladID',
            [':id' => $id, ':id2' => $id]
        )->queryAll();
        $res = 0;
        if (!$rows) {
            $res = 2;
        } else {
            foreach ($rows as $r) {
                if ((int)$r['Kol'] === -1 || (float)$r['SumKol'] != (float)$r['Kol']) { $res = 1; break; }
            }
        }
        $minus = $db->createCommand(
            "SELECT COUNT(*) FROM dbo.tblShceta INNER JOIN dbo.tblPredShceta ON dbo.tblPredShceta.PredShcID = dbo.tblShceta.PrSchID
              WHERE dbo.tblShceta.[НомерНакладной] LIKE '-%' AND dbo.tblPredShceta.Repl > 0 AND dbo.tblShceta.PrSchID = :id",
            [':id' => $id]
        )->queryScalar();
        return $minus > 0 ? 4 : $res;
    }

    /**
     * TstSum: сумма по позициям + доставка + прочие должна совпасть с суммой счёта, иначе сообщение.
     * (Проверка лимита договора GetAdr(70) / fnSkladDogsAllowScet — позже, их кода ещё нет.)
     */
    private function tstSum(int $id, array $rec): ?string
    {
        $pos = (float)LegacySp::sp1($id, 'dbo.xp_CountSumSc', 0, 'float');
        return self::sumDiff($pos, (float)($rec['SumDost'] ?? 0), (float)($rec['DopSum'] ?? 0), (float)($rec['Sm'] ?? 0));
    }

    /** Текст MsgBox из TstSum: сумма по позициям + доставка + прочие против суммы счёта. null — совпало. */
    public static function sumDiff(float $pos, float $dost, float $dop, float $sm): ?string
    {
        $sm1  = round($pos + $dost + $dop, 2);
        $sm2  = round($sm, 2);
        if ($sm1 - $sm2 == 0) {
            return null;
        }
        $f = fn($v) => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
        return 'Указанная сумма счёта: ' . $f($sm2) . "\n"
            . ($dost == 0 ? 'Сумма по позициям + сумма прочих расходов: ' : 'Сумма по позициям + сумма доставки + сумма прочих расходов: ')
            . $f($sm1) . "\nРазница составляет " . $f($sm2 - $sm1);
    }

    /**
     * butDob_Click, до выбора элемента: CheckNumSch, затем вопрос «Открыть облегченную версию поиска компонента?».
     * Да — облегчённый поиск frmFndElem (ответ light: true), Нет — полный поиск fObnov (ответ full: true).
     */
    public function addPos(int $id, array $answers): array
    {
        $rec = $this->save->rec($id);
        if (\app\components\AccessFormat::bool($rec['IsClosed'] ?? 0)) return ['ok' => false, 'msg' => 'Счёт закрыт'];
        if ($txt = $this->checkNumSch($rec)) return ['ok' => false, 'msg' => $txt];
        if (!isset($answers['light'])) {
            return ['ask' => ['key' => 'light', 'text' => 'Открыть облегченную версию поиска компонента?']];
        }
        return $answers['light'] ? ['ok' => true, 'light' => true] : ['ok' => true, 'full' => true];
    }

    /** CheckNumSch для счёта по коду — текст ошибки или null. */
    public function numSchError(int $id): ?string
    {
        return $this->checkNumSch($this->save->rec($id));
    }

    /** CheckNumSch(True) для frmMassInputSch. */
    private function checkNumSch(array $rec): ?string
    {
        if (($rec['Организация'] ?? null) === null) return 'Не указан продавец';
        if (($rec['TP'] ?? null) === null) return 'Не указан покупатель';
        if (($rec['НомерСчета'] ?? null) === null) return 'Не указан номер счёта';
        if (($rec['ДатаСчета'] ?? null) === null) return 'Не указана дата счёта';
        return null;
    }
}
