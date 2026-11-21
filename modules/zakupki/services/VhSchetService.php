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
                (string)$f->firma === '' ? '%' : (string)$f->firma,   // cboFirma: задаётся окном «фильтр по счетам» (frmfltSch)
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

    /**
     * WhoPick_AfterUpdate: CommonStartSP4(ID, "dbo.xp_UpdateWhoPick", WhoPick, , , adInteger, adInteger) > 0 — сохранено.
     * Счёт должен быть в списке экрана (xp_GetSchByID находит его).
     */
    public function whoPick(int $id, int $who): array
    {
        if (!$this->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        return (int)\app\services\LegacySp::sp4($id, 'dbo.xp_UpdateWhoPick', $who, 0, 'int', 'int') > 0
            ? ['ok' => true] : ['ok' => false];
    }

    /**
     * PthSch_KeyDown (Delete): «Удалить путь к счёту?» → CommonStartSP6(ID, "dbo.xp_UpdatePthSch", "", 0) > 0.
     * В Access сюда передаётся ListSubItems(11) — это столбец TP, а не код счёта (ошибка формы); здесь код счёта, как в PthSch_DblClick.
     */
    public function pathDel(int $id): array
    {
        if (!$this->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        return \app\services\LegacySp::sp6($id, 'dbo.xp_UpdatePthSch', '', 0, 500) > 0
            ? ['ok' => true] : ['ok' => false, 'msg' => 'Не удалось удалить путь к счёту'];
    }

    /** PthSch_DblClick (путь есть): WShell.Run — здесь файл счёта отдаётся браузеру. */
    public function pathFile(int $id): array
    {
        $unc = trim((string)($this->getOne($id)['pth'] ?? ''));
        if ($unc === '') return ['ok' => false, 'msg' => 'Путь к счёту не указан'];
        if (!\app\components\UncPath::mapped($unc)) return ['ok' => false, 'msg' => "Папка не подключена на сервере (настройка uncMap)\n$unc"];
        $p = \app\components\UncPath::local($unc);
        return $p ? ['ok' => true, 'path' => $p, 'unc' => $unc] : ['ok' => false, 'msg' => "Не найден файл\n$unc"];
    }

    /**
     * PthSch_DblClick (пути нет): файл копируется в GetAdr(20) + фирма (RemoveSpecSym), такой файл уже есть — к имени
     * спереди yymmdd-hhnnss_; путь пишется CommonStartSP6(ID, "dbo.xp_UpdatePthSch", pth, 0, , 500) > 0.
     */
    public function pathSet(int $id, ?\yii\web\UploadedFile $file): array
    {
        $one = $this->getOne($id);
        if (!$one) return ['ok' => false, 'msg' => 'Счёт не найден'];
        if (!$file || $file->hasError) return ['ok' => false, 'msg' => 'Файл не получен' . ($file ? ' (ошибка ' . $file->error . ')' : '')];
        $U = \app\components\UncPath::class;
        $zac = $U::safeName(rtrim(trim((string)($one['Hld'] ?? '')), '.'));
        if ($zac === '') return ['ok' => false, 'msg' => 'Не удалось определить фирму счёта'];
        $dir = \app\components\LegacyMail::adr(20) . $zac . '\\';
        $name = $U::safeName(basename(str_replace('\\', '/', $file->name)));
        $unc = $dir . $name;
        if ($U::local($unc)) $unc = $dir . date('ymd-His') . '_' . $name;
        if ($err = $U::save($file->tempName, $unc)) return ['ok' => false, 'msg' => $err, 'icon' => 'warn'];
        if (\app\services\LegacySp::sp6($id, 'dbo.xp_UpdatePthSch', $unc, 0, 500) > 0) return ['ok' => true, 'pth' => $unc];
        return ['ok' => false, 'msg' => 'Не удалось указать путь к счёту'];
    }

    /**
     * frmShowPrim, режим 44 (EditPrim): NzMyStr(txt) → CommonStartSP6(ID, "dbo.xp_UpdatePrimSch", txt, 0, , 500) > 0 — сохранено.
     * Неудача в Access молча закрывает окно; здесь так же, без сообщения.
     */
    public function prim(int $id, string $text): array
    {
        if (!$this->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        $text = mb_substr($text, 0, 500);
        return \app\services\LegacySp::sp6($id, 'dbo.xp_UpdatePrimSch', $text, 0, 500) > 0 ? ['ok' => true, 'text' => $text] : ['ok' => false];
    }

    /**
     * txtComm_DblClick → frmShowPrim 83 → CommonStartSP6(ID, "dbo.xp_UpdateCommSch", OldLine, , , 1024, , 1):
     * 1 — сохранено, 2 — «У Вас нет прав на данное действие», иначе «Не удалось изменить примечание».
     */
    public function comm(int $id, string $text): array
    {
        if (!$this->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        $text = mb_substr($text, 0, 1024);
        return match (\app\services\LegacySp::sp6($id, 'dbo.xp_UpdateCommSch', $text, 0, 1024)) {
            1 => ['ok' => true, 'text' => $text],
            2 => ['ok' => false, 'msg' => 'У Вас нет прав на данное действие'],
            default => ['ok' => false, 'msg' => 'Не удалось изменить примечание'],
        };
    }

    /**
     * ClsByPlat_Click («Закрыть к оплате»): вопрос задаёт экран; CommonStartSP1(ID, "dbo.xp_UpdateClsByPlat", 0):
     * 1 — строка списка заново (ChangeDataInList), 2 — нет прав, иначе «Не удалось изменить состояние счёта».
     */
    public function clsByPlat(int $id): array
    {
        if (!$this->getOne($id)) return ['ok' => false, 'msg' => 'Счёт не найден'];
        return match ((int)\app\services\LegacySp::sp1($id, 'dbo.xp_UpdateClsByPlat', 0)) {
            1 => ['ok' => true, 'row' => true],
            2 => ['ok' => false, 'msg' => 'У Вас нет прав на данное действие'],
            default => ['ok' => false, 'msg' => 'Не удалось изменить состояние счёта'],
        };
    }

    /**
     * DelOplPrc_Click: строка «Проценты/суммы оплат» (BoundColumn 2 = третий столбец xp_GetOplPrc) → CommonStartSP1(её код, "dbo.xp_DelOplPrc", 0):
     * 1 — списки панели заново, 3 — ещё и строка счёта, 4 — нет прав, 0 — «Не удалось удалить запись». Вопроса в Access нет.
     */
    public function delOpl(int $id, int $opl): array
    {
        $ids = array_map(fn($r) => (int)($r[2] ?? 0), $this->exec('dbo.xp_GetOplPrc', [$id])->queryAll(\PDO::FETCH_NUM));
        if (!$opl || !in_array($opl, $ids, true)) return ['ok' => false, 'msg' => 'Не выбрана строка для удаления'];
        return match ((int)\app\services\LegacySp::sp1($opl, 'dbo.xp_DelOplPrc', 0)) {
            1 => ['ok' => true, 'panel' => true],
            3 => ['ok' => true, 'panel' => true, 'row' => true],
            4 => ['ok' => false, 'msg' => 'У Вас нет прав на данное действие'],
            default => ['ok' => false, 'msg' => 'Не удалось удалить запись'],
        };
    }

    /**
     * DelOplToDo_Click: вопрос «Удалить строку 'К оплате'?» задаёт экран; CommonStartSP7(код строки, "dbo.xp_OplPrcToDoUpd", "--del--", "  ", , 255, 255):
     * 'wr…' — список заново и сообщение, 'ok' — список заново, иначе список заново и «Не удалось удалить запись».
     */
    public function delToDo(int $id, int $typeSch, int $todo): array
    {
        $ids = array_map(fn($r) => (int)($r[0] ?? 0), $this->exec('dbo.xp_OplPrcToDoLst', [$id, $typeSch])->queryAll(\PDO::FETCH_NUM));
        if (!$todo || !in_array($todo, $ids, true)) return ['ok' => false, 'msg' => 'Не выбрана строка для удаления'];
        $res = \app\services\LegacySp::sp7($todo, 'dbo.xp_OplPrcToDoUpd', '--del--', '  ', 255, 255);
        return match (substr($res, 0, 2)) {
            'wr' => ['ok' => true, 'panel' => true, 'msg' => substr($res, 2)],
            'ok' => ['ok' => true, 'panel' => true],
            default => ['ok' => false, 'panel' => true, 'msg' => 'Не удалось удалить запись'],
        };
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
