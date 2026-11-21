<?php
namespace app\modules\zakupki\services;

use app\components\LegacyMail;
use app\services\LegacySp;
use Yii;

/**
 * «+» оплат во «Входящих счетах» (frmStat: AddOpl_Click, AddOplToDo_Click, fnAddOplPrc, MailAboutIt, MailAboutBigSum;
 * общий модуль: fnSkladDogsAllowScet) и окно frmGetPrcSch (режимы 0, 2, 4).
 *
 * Вопросы (MsgBox vbYesNo) задаёт экран, сервер только сообщает коды — так же, как в остальных act.
 */
class OplService
{
    /** Поля строки «к оплате», которые окно frmGetPrcSch (режимы 2, 4) сохраняет через acCmdSaveRecord. */
    private const TODO_FIELDS = ['Prc', 'Prim', 'Dta2Opl', 'SrokOb'];

    // ---------------------------------------------------------------- AddOpl

    /**
     * Начало AddOpl_Click: всё, что Access узнаёт до окна frmGetPrcSch.
     *  dog  — сообщение fnSkladDogsAllowScet(ID, 2, True) или null (в Access оно только показывается, работа идёт дальше);
     *  who  — есть строки xp_WhoBuySch (их показывает frmFndPR 11), спрашивается только при пустом lstOplPrc;
     *  corr — CommonStartSP1(ID, "dbo.xp_IsCorrOplPrc", 0);
     *  a173, a174 — GetAdr(173), GetAdr(174) для вопросов -4..-6; unit — подпись кнопки «рублей» (ListSubItems(25)).
     */
    public function prep(int $id): array
    {
        $row = (new VhSchetService())->getOne($id);
        if (!$row) return ['ok' => false, 'msg' => 'Счёт не найден'];
        $who = false;
        if (!$this->exec('dbo.xp_GetOplPrc', [$id])->queryAll()) {
            $who = (bool)$this->whoRows($id);
        }
        return ['ok' => true, 'dog' => $this->dogsMsg($id), 'who' => $who] + $this->corr($id) + ['unit' => (string)($row['InUEtxt'] ?? '')];
    }

    /** Начало AddOplToDo_Click: только xp_IsCorrOplPrc (вопросы -4..-6) и подпись кнопки «рублей». */
    public function todoPrep(int $id): array
    {
        $row = (new VhSchetService())->getOne($id);
        if (!$row) return ['ok' => false, 'msg' => 'Счёт не найден'];
        return ['ok' => true] + $this->corr($id) + ['unit' => (string)($row['InUEtxt'] ?? '')];
    }

    private function corr(int $id): array
    {
        return [
            'corr' => (int)LegacySp::sp1($id, 'dbo.xp_IsCorrOplPrc', 0),
            'a173' => LegacyMail::adr(173),
            'a174' => LegacyMail::adr(174),
        ];
    }

    /**
     * fnSkladDogsAllowScet(ID, 2, True): xp_SkladDogsAllowScetNew ID, Rgm, @res OUT (UB), @res2 OUT (код).
     * 3, 4, 5 — сообщение; результат функции в AddOpl не используется.
     */
    public function dogsMsg(int $id): ?string
    {
        $r = $this->lastRow(
            'SET NOCOUNT ON; DECLARE @ub int, @r int; EXEC dbo.xp_SkladDogsAllowScetNew ?, ?, @ub OUTPUT, @r OUTPUT; SELECT @ub AS ub, @r AS r',
            [1 => $id, 2 => 2]
        );
        $ub = self::vbNum((float)($r['ub'] ?? 0));
        return match ((int)($r['r'] ?? 0)) {
            3 => "Сумма счёта превышает $ub, но срок гарантии менее 12 месяцев",
            4 => "Сумма счёта превышает $ub, но нет договора поставки",
            5 => "Сумма счёта превышает $ub, но нет активного договора поставки",
            default => null,
        };
    }

    /** exec dbo.xp_WhoBuySch ID — строки для окна «Список элементов, требующих внимания» (frmFndPR 11), столбцы по именам. */
    public function whoRows(int $id): array
    {
        return $this->exec('dbo.xp_WhoBuySch', [$id])->queryAll();
    }

    /**
     * Ответ «Да» на вопрос -2 «Ввести корректирующую запись?»: fnAddOplPrc(ID, -1, Rgm, 0).
     * 0, 4, 5, 6 — сообщение и стоп; остальное — продолжаем (в Access списки здесь не перечитываются).
     */
    public function korr(int $id): array
    {
        if (!(new VhSchetService())->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        return match ($this->addOplPrc($id, -1.0, 0)) {
            0 => ['ok' => false, 'msg' => 'Не удалось ввести корректирующую запись'],
            4, 5 => ['ok' => false, 'msg' => 'Нельзя ввести такое значение'],
            6 => ['ok' => false, 'msg' => 'У счёта не указана сумма или НДС'],
            default => ['ok' => true],
        };
    }

    /**
     * После окна frmGetPrcSch (режим 0): fnAddOplPrc(ID, Prc, Rgm, Ovr). Prc > 0 — проценты (целое, до 100), Prc < 0 — сумма.
     * 1 — списки и строка заново, экран спрашивает «Отправить письмо об оплате?» и зовёт mail();
     * 3 — письмо о большой сумме сразу, списки и строка заново.
     */
    public function add(int $id, float $prc, bool $ovr): array
    {
        if (!(new VhSchetService())->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        if ($prc > 100 || ($prc > 0 && $prc != floor($prc))) return ['ok' => false, 'msg' => 'Процентов не может быть более 100'];
        if ($prc > 0) $ovr = false;                     // Prc_Click: AllowOverflow = False
        switch ($this->addOplPrc($id, $prc, $ovr ? 1 : 0)) {
            case 1:
                return ['ok' => true, 'panel' => true, 'row' => true, 'askMail' => true];
            case 2:
                return ['ok' => false, 'msg' => 'У Вас нет прав на данное действие'];
            case 3:
                $m = $this->mailBigSum($id, $prc);
                return ['ok' => true, 'panel' => true, 'row' => true] + ($m ? ['msg' => $m] : []);
            case 4: case 5:
                return ['ok' => false, 'msg' => 'Нельзя ввести такое значение'];
            case 6:
                return ['ok' => false, 'msg' => 'У счёта не указана сумма или НДС'];
            default:
                return ['ok' => false, 'msg' => 'Не удалось указать процент оплаты счёта'];
        }
    }

    /** Ответ на «Отправить письмо об оплате?»: «Да» — MailAboutIt, затем в любом случае MailAboutBigSum (ТЗ 609). */
    public function mail(int $id, float $prc, bool $it): array
    {
        if (!(new VhSchetService())->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        $msgs = [];
        if ($it) $msgs[] = $this->mailAboutIt($id, $prc);
        if ($m = $this->mailBigSum($id, $prc)) $msgs[] = $m;
        $msgs = array_values(array_filter($msgs));
        return ['ok' => true, 'msgs' => $msgs];
    }

    /** fnAddOplPrc: xp_AddOplPrcNew ID, Prc money, Rgm (1 у счёта на детали), Ovr, @res OUTPUT. */
    private function addOplPrc(int $id, float $prc, int $ovr): int
    {
        $r = $this->lastRow(
            'SET NOCOUNT ON; DECLARE @r int; EXEC dbo.xp_AddOplPrcNew ?, ?, ?, ?, @r OUTPUT; SELECT @r AS r',
            [1 => $id, 2 => $prc, 3 => $id < 0 ? 1 : 0, 4 => $ovr]
        );
        return (int)($r['r'] ?? 0);
    }

    /**
     * MailAboutIt: xp_MailAboutItNew ID, Prc, @res nvarchar(700), @email, @emailPost.
     * В Access: SendEMailCDO(emailPost, "", «Сообщение об оплате», res, conf, email) — письмо на emailPost от имени email;
     * здесь email ставится в «Ответить» (Reply-To), отправитель — общий адрес сервера.
     */
    private function mailAboutIt(int $id, float $prc): string
    {
        $r = $this->lastRow(
            'SET NOCOUNT ON; DECLARE @t nvarchar(700), @e nvarchar(100), @p nvarchar(100);'
            . ' EXEC dbo.xp_MailAboutItNew ?, ?, @t OUTPUT, @e OUTPUT, @p OUTPUT; SELECT @t AS t, @e AS e, @p AS p',
            [1 => $id, 2 => $prc]
        );
        if (($r['t'] ?? '') === '' || ($r['e'] ?? '') === '' || ($r['p'] ?? '') === '') {
            return 'Не удалось получить необходимые данные для отправки письма';
        }
        return LegacyMail::send((string)$r['p'], 'Сообщение об оплате', (string)$r['t'], (string)$r['e']);
    }

    /** MailAboutBigSum: xp_MailAboutBigSum ID, Prc, @res, @email, @emailPost, @subj. Сообщение только при ошибке отправки. */
    private function mailBigSum(int $id, float $prc): ?string
    {
        $r = $this->lastRow(
            'SET NOCOUNT ON; DECLARE @t nvarchar(700), @e nvarchar(100), @p nvarchar(100), @s nvarchar(100);'
            . ' EXEC dbo.xp_MailAboutBigSum ?, ?, @t OUTPUT, @e OUTPUT, @p OUTPUT, @s OUTPUT; SELECT @t AS t, @e AS e, @p AS p, @s AS s',
            [1 => $id, 2 => $prc]
        );
        if (($r['t'] ?? '') === '' || ($r['e'] ?? '') === '') return null;
        $res = LegacyMail::send((string)($r['p'] ?? ''), (string)($r['s'] ?? ''), (string)$r['t']);
        return str_starts_with($res, LegacyMail::OK) ? null : $res;
    }

    // ---------------------------------------------------------------- «К оплате» (frmGetPrcSch 2, 4)

    /**
     * Form_Open режимов 2 (счёт на элементы, Tip 0) и 4 (на детали, Tip 1): FillVl4Cmd2Opl(IDSch & Tip & "0", 0) —
     * CommonStartSP7(0, "dbo.xp_OplPrcToDoUpd", Prm, "  ", 0, 255, 255): 'ok<код новой строки>' — заготовка строки создана.
     */
    public function todoNew(int $id, int $tip): array
    {
        if (!(new VhSchetService())->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        $res = LegacySp::sp7(0, 'dbo.xp_OplPrcToDoUpd', $id . $tip . '0', '  ', 255, 255);
        return match (substr($res, 0, 2)) {
            'ok' => (int)substr($res, 2) ? ['ok' => true, 'todo' => (int)substr($res, 2)] : ['ok' => false, 'msg' => 'Не удалось добавить запись'],
            'wr' => ['ok' => false, 'msg' => substr($res, 2)],
            default => ['ok' => false, 'msg' => 'Не удалось добавить запись'],
        };
    }

    /** RecordSource окна: exec dbo.xp_OplPrcToDoRS код, 0. Строка должна относиться к этому счёту (поле IDSch). */
    public function todoRow(int $todo, int $id): ?array
    {
        $r = $this->exec('dbo.xp_OplPrcToDoRS', [$todo, 0])->queryOne();
        if (!$r) return null;
        if (array_key_exists('IDSch', $r) && (int)$r['IDSch'] !== $id) return null;
        return $r;
    }

    /** Dta2Opl_DblClick: CommonStartSP7(ID, "dbo.xp_OplPrcToDoChk", "0" & ггггммдд, "  ", , 255, 255): 'ok' / 'er<текст>'. */
    public function todoChk(int $todo, int $id, string $ymd): array
    {
        if (!$this->todoRow($todo, $id) || !preg_match('/^\d{8}$/', $ymd)) return ['ok' => false, 'msg' => 'Строка не найдена'];
        if ($ymd < date('Ymd')) return ['ok' => false, 'msg' => 'Вы указываете дату, которая уже прошла'];
        $res = LegacySp::sp7($todo, 'dbo.xp_OplPrcToDoChk', '0' . $ymd, '  ', 255, 255);
        return match (substr($res, 0, 2)) {
            'ok' => ['ok' => true],
            'er' => ['ok' => false, 'msg' => substr($res, 2)],
            default => ['ok' => false, 'msg' => 'Не удалось изменить дату оплаты'],
        };
    }

    /**
     * Кнопки «процентов» / «рублей» в режимах 2, 4: acCmdSaveRecord (поля Prc, Prim, Dta2Opl, SrokOb), затем
     * FillVl4Cmd2Opl(IDSch & Tip & unit, ID): даты обязательны; CommonStartSP7(ID, "dbo.xp_OplPrcToDoUpd", Prm, ...):
     * 'ok' — окно закрывается, 'wr…' — сообщение, окно остаётся, иначе «Не удалось изменить запись».
     */
    public function todoSave(int $todo, int $id, int $tip, int $unit, array $v): array
    {
        $row = $this->todoRow($todo, $id);
        if (!$row) return ['ok' => false, 'msg' => 'Строка не найдена'];
        $prc = $v['Prc'] === '' || $v['Prc'] === null ? null : (float)str_replace(',', '.', (string)$v['Prc']);
        if ($prc !== null && $prc < 0) return ['ok' => false, 'msg' => 'Значение должно быть не меньше 0'];   // ValidationRule ">=0"
        if ($unit === 0 && $prc > 100) return ['ok' => false, 'msg' => 'Процентов не может быть более 100', 'prc' => 100];
        $d1 = self::date($v['Dta2Opl'] ?? ''); $d2 = self::date($v['SrokOb'] ?? '');
        // даты выбираются в календаре (Dta2Opl_DblClick, SrokOb_DblClick); здесь те же проверки ещё раз
        if ($d1 && $d1 !== self::date(substr((string)($row['Dta2Opl'] ?? ''), 0, 10))) {
            $c = $this->todoChk($todo, $id, str_replace('-', '', $d1));
            if (!$c['ok']) return $c;
        }
        if ($d1 && $d2 && $d2 < $d1) return ['ok' => false, 'msg' => "Вы указываете дату 'Не позднее', которая меньше, чем 'Дата оплаты'"];
        $this->saveRecord($todo, [
            'Prc' => $prc, 'Prim' => ($p = (string)($v['Prim'] ?? '')) === '' ? null : $p,
            'Dta2Opl' => $d1, 'SrokOb' => $d2,
        ]);
        if (!$d1) return ['ok' => false, 'msg' => 'Не указана дата оплаты'];
        if (!$d2) return ['ok' => false, 'msg' => "Не указана дата 'Не позднее'"];
        $res = LegacySp::sp7($todo, 'dbo.xp_OplPrcToDoUpd', $id . $tip . $unit, '  ', 255, 255);
        return match (substr($res, 0, 2)) {
            'ok' => ['ok' => true, 'panel' => true],
            // 'wr…': процедура обнулила Prc и OplSm; Access перечитывает форму — отдаём новое значение поля
            'wr' => ['ok' => false, 'msg' => substr($res, 2), 'prc' => (float)($this->todoRow($todo, $id)['Prc'] ?? 0)],
            default => ['ok' => false, 'msg' => 'Не удалось изменить запись'],
        };
    }

    /** Cncl_Click (и закрытие окна): FillVl4Cmd2Opl("--del--", ID, False) — заготовка удаляется. */
    public function todoDel(int $todo, int $id): array
    {
        if (!$this->todoRow($todo, $id)) return ['ok' => true];
        $res = LegacySp::sp7($todo, 'dbo.xp_OplPrcToDoUpd', '--del--', '  ', 255, 255);
        return match (substr($res, 0, 2)) {
            'ok' => ['ok' => true, 'panel' => true],
            'wr' => ['ok' => false, 'panel' => true, 'msg' => substr($res, 2)],
            default => ['ok' => false, 'panel' => true, 'msg' => 'Не удалось изменить запись'],
        };
    }

    /**
     * acCmdSaveRecord у формы на процедуре: ADP пишет в таблицу, из которой взяты поля xp_OplPrcToDoRS, —
     * это dbo.SubtblOplToDo (SELECT OplSm, Overflow, Prim, Dta2Opl, SrokOb, Prc, ID, IDSch, BySm … WHERE ID = @ID).
     * Пересчёт OplSm / Prc по BySm делает потом xp_OplPrcToDoUpd.
     */
    private function saveRecord(int $todo, array $vals): void
    {
        $set = []; $bind = []; $i = 1;
        foreach (self::TODO_FIELDS as $f) {
            $set[] = "[$f] = ?";
            $v = $vals[$f] ?? null;
            // даты как 'ггггммдд': у логинов на русском языке '2026-11-20' SQL Server читает как год-день-месяц
            if (($f === 'Dta2Opl' || $f === 'SrokOb') && $v !== null) $v = str_replace('-', '', $v);
            $bind[$i++] = $v;
        }
        $bind[$i] = $todo;
        Yii::$app->db->createCommand('UPDATE dbo.SubtblOplToDo SET ' . implode(', ', $set) . ' WHERE ID = ?', $bind)->execute();
    }

    // ---------------------------------------------------------------- помощники

    /** '2026-11-20' (поле даты) → то же, пусто/мусор → null. */
    private static function date($v): ?string
    {
        $v = (string)$v;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    /** Число, как его печатает VBA при сложении со строкой: без лишних нулей, запятая. */
    public static function vbNum(float $v): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.'));
    }

    /** EXEC с параметрами по порядку и последняя непустая выборка (выходные параметры процедуры идут после её выборок). */
    private function lastRow(string $sql, array $bind): array
    {
        $reader = Yii::$app->db->createCommand($sql, $bind)->query();
        $last = [];
        do {
            try { $rows = $reader->readAll(); } catch (\Throwable $e) { $rows = []; }
            if ($rows) $last = end($rows);
        } while ($reader->nextResult());
        return $last;
    }

    private function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind = [];
        foreach (array_values($values) as $i => $v) $bind[$i + 1] = $v;
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc $marks", $bind);
    }
}
