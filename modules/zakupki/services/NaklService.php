<?php
namespace app\modules\zakupki\services;

use app\components\AccessFormat as F;
use app\services\LegacySp;
use Yii;

/**
 * Накладная (frmMassInput): просмотр (Rgm = 1), создание из карточки (AddNakl_Click → OpenArgs 7), режим правки
 * (красный фон: открыта после создания или ✎ Кнопка58), шапка, удаление строки, «Сохранить», «На склад», закрытие.
 *
 * Как в Access (Form_Load):
 *   запись            — xp_frmMassInputRSNew 0, ShcID (накладная по коду; у деталей — SELECT tblShceta.*, шапка счёта с карточки);
 *   строки (подформа)  — xp_SubFrmqrytblAllRS1 ShcID, IsDetal (SetRS4PosShc);
 *   «Где купил»        — xp_frmWhereByRS SkladID текущей строки (SetRS4WhereBy);
 *   суммы (SumSch)     — exec xp_CountSumSch ShcID (элементы) / xp_CountSumScDetal ShcID (детали);
 *   RO у деталей       — xp_ProdPlanIsSostInSklad (ShcID).
 * Открывать можно, только если накладную никто не правит: xp_IsEditNakl (ShcID) → имя пользователя или ''.
 */
class NaklService
{
    /**
     * Открыть накладную из карточки счёта $sch. $btn — кнопка «Посмотреть» (OpnFrm_Click), иначе двойной щелчок по списку.
     * Счёт на элементы, кнопка: CheckNumSch, xp_frmMassInputSch_OpnFrm (ID) > 0, иначе «Нет накладных»; не выбрана — «Нет накладных для просмотра».
     * Счёт на детали, кнопка: если не выбрана — первая в списке (это делает экран). Затем xp_IsEditNakl.
     */
    public function open(int $sch, int $nakl, bool $btn): array
    {
        if ($btn && $sch > 0) {
            $act = new SchetActionService();
            if ($txt = $act->numSchError($sch)) return ['ok' => false, 'msg' => $txt];
            if (LegacySp::sp1($sch, 'dbo.xp_frmMassInputSch_OpnFrm', 0) <= 0) return ['ok' => false, 'msg' => 'Нет накладных'];
            if ($nakl <= 0) return ['ok' => false, 'msg' => 'Нет накладных для просмотра'];
        }
        if ($nakl <= 0) return ['ok' => false];
        $who = $this->whoEdits($nakl);
        if ($who !== '') return ['ok' => false, 'msg' => "Данная накладная редактируется пользователем '$who'.\nЕё открытие невозможно"];
        return ['ok' => true, 'nakl' => $nakl];
    }

    /** lstNakl_DblClick / OpnFrm_Click: xp_IsEditNakl — кто правит накладную ('' — никто). */
    public function whoEdits(int $naklId): string
    {
        return LegacySp::sp2($naklId, 'dbo.xp_IsEditNakl', '', 50);
    }

    /**
     * Данные окна. $sch — счёт, из карточки которого открыли (у деталей отрицательный: шапка счёта берётся с карточки).
     * Накладная должна быть в списке накладных этого счёта (lstNakl) — так пользователь не откроет чужую по адресу.
     */
    public function view(int $naklId, int $sch, bool $edit = false, bool $new = false): array
    {
        $det = $sch < 0;
        $card = $det ? (new SchetService())->getDetal($sch) : (new SchetService())->getElem($sch);
        if (!$card) throw new \yii\web\NotFoundHttpException('Не найден счёт');
        $ids = array_map(fn($r) => (int)reset($r), $card['nakl']);
        if (!in_array($naklId, $ids, true)) throw new \yii\web\NotFoundHttpException('Накладная не найдена в этом счёте');
        $ro = $det && LegacySp::sp1($naklId, 'dbo.xp_ProdPlanIsSostInSklad', 0) !== 0;
        $edit = $edit && !$ro;                                         // WhoOpen 2: OpenArgs = 7 And RO = False
        if ($edit) $this->lock($naklId, true);                         // OpenArgs 7: Add2ChgNakl(ID, False)

        $rec = $this->exec('dbo.xp_frmMassInputRSNew', [0, $naklId])->queryOne();
        if (!$rec) throw new \yii\web\NotFoundHttpException('Не найдена накладная');
        if ($det) {   // WhoOpen = 2: продавец, покупатель, номер и дата — с карточки счёта на детали
            $c = $card['rec'];
            $rec['Org'] = $c['NmeOrg'] ?? null;
            $isp = $c['IspFrm'] ?? ''; $isp = is_numeric($isp) ? (string)(0 + $isp) : (string)$isp;
            $rec['TP'] = null;
            foreach ($card['combos']['IspFrm'] as $k => $t) if ((is_numeric($k) ? (string)(0 + $k) : (string)$k) === $isp) $rec['TP'] = $t;
            $rec['NumSch'] = $c['NumSc'] ?? null;
            $rec['DtaSch'] = $c['DtaSc'] ?? null;
        }
        // xp_SubFrmqrytblAllRS1 (ID, IsDetal) как SetRS4PosShc: строки накладной на детали лежат в tblAll с ShcID = -ID,
        // их отдаёт первый набор (второй — ветка ППМ, для деталей пустая).
        $pos = $this->safe(fn() => $this->exec('dbo.xp_SubFrmqrytblAllRS1', [$naklId, $det ? 1 : 0])->queryAll(), []);
        $first = (int)($pos[0]['SkladID'] ?? 0);
        return [
            'rec' => $rec, 'det' => $det, 'sch' => $sch, 'pos' => $pos,
            'whereBy' => $first ? $this->whereBy($first) : [],
            'sumSch' => $this->safe(fn() => $this->exec($det ? 'dbo.xp_CountSumScDetal' : 'dbo.xp_CountSumSch', [$naklId])->queryAll(\PDO::FETCH_NUM), []),
            'isAkt' => $det && (int)($rec['IsAkt'] ?? 0) === 1,
            'ro' => $ro,
            // Leg01 «Комплект…»: у первой строки IDPcb > -1
            'leg01' => $pos && (int)($pos[0]['IDPcb'] ?? -1) > -1,
            'edit' => $edit, 'cardRec' => $card['rec'],
            // Rgm 2 (новая накладная к счёту на элементы): внизу позиции счёта (lstSch, FillListSchetPos(0))
            'lstSch' => ($edit && $new && !$det) ? $this->safe(fn() => $this->exec('dbo.xp_LstMassInputRowS1', [$sch, 0])->queryAll(), []) : null,
        ];
    }

    // ------------------------------------------------------------------ создание

    /**
     * AddNakl_Click карточки. Элементы: CheckNumSch; CheckNakl: 0, 3 — «Все позиции счёта закрыты накладными», 2 — «Нет элементов в счёте»;
     * 1, 4 — xp_frmMassInputSchExistFile ≥ 0 → «Есть excel-файл?» (Да — ID со знаком минус); #tmptblEl как FillTmpEl(ID, True);
     * xp_frmMassInputSch_AddNaklNew (ID, res OUT) → код новой накладной. Детали: CheckNumSch(False); xp_ProdPlanGetSostSch
     * (те же коды); xp_ProdPlanAddNakl (ID, res OUT). Потом накладная открывается в режиме правки.
     */
    public function create(int $sch, array $answers): array
    {
        $db = Yii::$app->db;
        if ($sch < 0) {
            if ($txt = (new SchetDetalService())->numSchError($sch)) return ['ok' => false, 'msg' => $txt];
            $st = LegacySp::sp1($sch, 'dbo.xp_ProdPlanGetSostSch', -1);
            if ($st === 0 || $st === 3) return ['ok' => false, 'msg' => 'Все позиции счёта закрыты накладными'];
            if ($st === 2) return ['ok' => false, 'msg' => 'Нет элементов в счёте'];
            $kod = ($st === 1 || $st === 4) ? (int)$this->scalar('DECLARE @r int; EXEC dbo.xp_ProdPlanAddNakl ?, @r OUTPUT; SELECT @r', [$sch]) : 0;
            return $kod > 0 ? ['ok' => true, 'nakl' => $kod] : ['ok' => false, 'msg' => 'Не удалось создать документ'];
        }
        $act = new SchetActionService();
        if ($txt = $act->numSchError($sch)) return ['ok' => false, 'msg' => $txt];
        $st = $act->checkNakl($sch);
        if ($st === 0 || $st === 3) return ['ok' => false, 'msg' => 'Все позиции счёта закрыты накладными'];
        if ($st === 2) return ['ok' => false, 'msg' => 'Нет элементов в счёте'];
        $id = $sch;
        $res = LegacySp::sp1($sch, 'dbo.xp_frmMassInputSchExistFile', -1);
        if ($res >= 0) {
            if (!isset($answers['xls'])) return ['ask' => ['key' => 'xls', 'text' => 'Есть excel-файл?']];
            if ($answers['xls']) {
                // в Access при Res = 2 после создания накладной сразу идёт загрузка УПД (LoadUPD) — она ещё не перенесена
                if ($res === 2) return ['ok' => false, 'msg' => "Загрузка УПД из файла ещё не перенесена.\nОтветьте «Нет», чтобы создать накладную вручную."];
                $id = -$sch;
            }
        }
        // xp_frmMassInputSch_AddNaklNew (текст от 20.11): сама создаёт tblShceta, строки (недополученное, если ID > 0)
        // и отметку правки tblChangeNakl; #tmptblEl ей не нужна. res = код накладной или 0.
        $kod = (int)$this->scalar('DECLARE @r int; EXEC dbo.xp_frmMassInputSch_AddNaklNew ?, @r OUTPUT; SELECT @r', [$id]);
        return $kod > 0 ? ['ok' => true, 'nakl' => $kod] : ['ok' => false, 'msg' => 'Не удалось создать накладную'];
    }

    // ------------------------------------------------------------------ правка

    /** Add2ChgNakl(ID, ClearOnly): xp_ChangeNaklNew (ClearOnly bit, ID) — отметка «накладную правит пользователь» / снять. */
    public function lock(int $nakl, bool $on): void
    {
        $this->safe(fn() => Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_ChangeNaklNew ?, ?', [1 => $on ? 0 : 1, 2 => $nakl])->execute(), null);
    }

    /** Окно закрыто не кнопкой выхода: только снять отметку правки (без проверок butExit). */
    public function release(int $nakl, int $sch): array
    {
        $this->own($nakl, $sch);
        $this->lock($nakl, false);
        return ['ok' => true];
    }

    /** Шапка накладной: поля tblShceta, которые правятся на форме. */
    public const HEAD = ['НомерНакладной' => 'text', 'ДатаНакладной' => 'date', 'Dst' => 'int'];

    public function saveHead(int $nakl, int $sch, string $field, ?string $value): array
    {
        $this->own($nakl, $sch);
        if (!isset(self::HEAD[$field])) return ['ok' => false, 'msg' => 'Это поле нельзя менять'];
        $value = trim((string)$value);
        $v = match (self::HEAD[$field]) {
            'date' => $value === '' ? null : (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) ? "$m[1]$m[2]$m[3]" : false),
            'int'  => $value === '' ? null : (ctype_digit($value) ? (int)$value : false),
            default => $value === '' ? null : mb_substr($value, 0, 50),
        };
        if ($v === false) return ['ok' => false, 'msg' => 'Неверное значение'];
        Yii::$app->db->createCommand("UPDATE dbo.tblShceta SET [$field] = :v WHERE ShcID = :id", [':v' => $v, ':id' => $nakl])->execute();
        return ['ok' => true];
    }

    /**
     * CheckNumNakl: номер и дата указаны и такой накладной ещё нет (xp_IsExistNakl Yarlik, Dta, ShcID, res OUT > 0).
     * Если есть — номер и дата очищаются. Возвращает текст ошибки или null.
     */
    public function numNaklError(int $nakl): ?string
    {
        $r = Yii::$app->db->createCommand('SELECT НомерНакладной AS n, CONVERT(char(8), ДатаНакладной, 112) AS d FROM dbo.tblShceta WHERE ShcID = :id', [':id' => $nakl])->queryOne();
        if (!$r || trim((string)$r['n']) === '') return 'Не указан номер накладной';
        if (empty($r['d'])) return 'Не указана дата накладной';
        $ex = (int)$this->scalar('DECLARE @r int; EXEC dbo.xp_IsExistNakl ?, ?, ?, @r OUTPUT; SELECT @r', [mb_substr((string)$r['n'], 0, 50), $r['d'], abs($nakl)]);
        if ($ex > 0) {
            Yii::$app->db->createCommand('UPDATE dbo.tblShceta SET НомерНакладной = NULL, ДатаНакладной = NULL WHERE ShcID = :id', [':id' => $nakl])->execute();
            return 'Такая накладная уже существует';
        }
        return null;
    }

    /** butSaveAs_Click: CheckNumNakl; xp_UpdateDtaShc (ID, Rgm = DateSosd пусто ? 0 : 1); форма выходит из режима правки. */
    public function saveAs(int $nakl, int $sch): array
    {
        $rec = $this->own($nakl, $sch);
        if ($txt = $this->numNaklError($nakl)) return ['ok' => false, 'msg' => $txt, 'reload' => true];
        Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_UpdateDtaShc ?, ?', [1 => $nakl, 2 => empty($rec['DateSosd']) ? 0 : 1])->execute();
        return ['ok' => true, 'view' => true];
    }

    /** fnAllowEditNakl: RO (у деталей составляющая уже на складе) — у деталей только предупреждение, у элементов запрет. */
    private function allowEdit(int $nakl, bool $det): array
    {
        $ro = $det && LegacySp::sp1($nakl, 'dbo.xp_ProdPlanIsSostInSklad', 0) !== 0;
        if (!$ro) return [true, null];
        return $det
            ? [true, 'Составляющая детали уже отправлена на склад. Корректировка данной накладной возможна только при отмене отправки составляющей детали на склад']
            : [false, 'Редактирование накладной запрещено'];
    }

    /** DelRec_Click: строка на складе — нельзя; вопрос; DELETE tblAll по AllID (только строку этой накладной). */
    public function delRow(int $nakl, int $sch, int $allId, array $answers): array
    {
        $this->own($nakl, $sch);
        [$ok, $warn] = $this->allowEdit($nakl, $sch < 0);
        if (!$ok) return ['ok' => false, 'msg' => $warn];
        $shc = self::rowShc($nakl, $sch < 0);
        $r = Yii::$app->db->createCommand('SELECT InSklad FROM dbo.tblAll WHERE AllID = :a AND ShcID = :n', [':a' => $allId, ':n' => $shc])->queryOne();
        if (!$r) return ['ok' => false, 'msg' => 'Выберите строку накладной'];
        if (F::bool($r['InSklad'])) return ['ok' => false, 'msg' => 'Невозможно удалить элемент из накладной, так как он числится на складе'];
        if (!isset($answers['del'])) return ['ask' => ['key' => 'del', 'text' => 'Удалить элемент из накладной?'], 'msg' => $warn];
        if ($answers['del']) {
            Yii::$app->db->createCommand('DELETE FROM dbo.tblAll WHERE AllID = :a AND ShcID = :n AND InSklad = 0', [':a' => $allId, ':n' => $shc])->execute();
        }
        return ['ok' => true, 'reload' => true, 'chg' => (bool)$answers['del']];
    }

    /**
     * Кнопка58_Click (✎ «Корректировка позиции в накладной»): fnAllowEditNakl; если счёт закрыт — открыть его
     * (CloseOrOpenSch: xp_UpdateIsClosedSch ID, 0); режим правки (красный фон), Add2ChgNakl(ID, False).
     */
    public function startEdit(int $nakl, int $sch): array
    {
        $rec = $this->own($nakl, $sch);
        [$ok, $warn] = $this->allowEdit($nakl, $sch < 0);
        if (!$ok) return ['ok' => false, 'msg' => $warn];
        $pr = (int)($rec['PrSchID'] ?? 0);
        if ($pr > 0 && LegacySp::sp1($pr, 'dbo.xp_IsClosedSch', 0) === 0) {          // CheckClosed = xp_IsClosedSch = 0
            $t = Yii::$app->db->beginTransaction();
            try {
                Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_UpdateIsClosedSch ?, ?', [1 => $pr, 2 => 0])->execute();
                $t->commit();
            } catch (\Throwable $e) {
                $t->rollBack();
                return ['ok' => false, 'msg' => $e->getMessage() . "\nОткат транзакции"];
            }
        }
        $this->lock($nakl, true);
        return ['ok' => true, 'edit' => true, 'msg' => $warn];
    }

    // ------------------------------------------------------------------ на склад

    /** ElNotInSklad: xp_CountElNotInSklad (ShcID, Org nvarchar(100), IsDetal, kol OUT). */
    private function notInSklad(int $nakl, string $org, bool $det): int
    {
        return (int)$this->scalar('DECLARE @k int; EXEC dbo.xp_CountElNotInSklad ?, ?, ?, @k OUTPUT; SELECT @k', [$nakl, mb_substr($org, 0, 100), $det ? 1 : 0]);
    }

    /** PutInTmpSklad: xp_fn_PutInTmpSklad1 (ID); затем отметка входного контроля xp_InControlMarkMailByAllIDs (письмо отключено с 27.05.19). */
    private function putInTmpSklad(int $nakl): bool
    {
        if (LegacySp::sp1($nakl, 'dbo.xp_fn_PutInTmpSklad1', 0) === 0) return false;
        $ids = $this->safe(fn() => $this->exec('dbo.xp_fn_PutInTmpSkladChkMail', [$nakl])->queryColumn(), []);
        if ($ids) {
            $rows = $this->safe(fn() => $this->exec('dbo.xp_fn_PutInTmpSkladChkMail', [$nakl])->queryAll(), []);
            $all = implode(',', array_map(fn($r) => $r['AllID'], $rows)) . ',';
            $this->safe(fn() => Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_InControlMarkMailByAllIDs ?', [1 => mb_substr($all, 0, 500)])->execute(), null);
        }
        return true;
    }

    /**
     * ToSklad_Click, счёт на элементы (WhoOpen 0): CheckNumNakl; xp_frmMassInputExistMark (ID, 0) > 0 — «не заполнено поле Маркировка»;
     * разные поставщики (ElNotInSklad(True) > 0) — вопрос; PutInTmpSklad → «отправлены на буферный склад».
     * Счёт на детали (WhoOpen 2, PutInSkladByNakl) — следующим шагом.
     */
    public function toSklad(int $nakl, int $sch, array $answers): array
    {
        $rec = $this->own($nakl, $sch);
        if ($sch < 0) return $this->toSkladDet($nakl, $rec);
        if ($txt = $this->numNaklError($nakl)) return ['ok' => false, 'msg' => $txt, 'reload' => true];
        if ((int)LegacySp::sp4($nakl, 'dbo.xp_frmMassInputExistMark', 0, 0, 'int') > 0) return ['ok' => false, 'msg' => "У некоторых позиций не заполнено поле 'Маркировка'"];
        if ($this->notInSklad($nakl, (string)($rec['Org'] ?? ''), false) > 0) {
            if (!isset($answers['diff'])) return ['ask' => ['key' => 'diff', 'text' => 'В накладной есть позиции с разными поставщиками. Занести на буферный склад?']];
            if (!$answers['diff']) return ['ok' => true];
        }
        return $this->putInTmpSklad($nakl)
            ? ['ok' => true, 'msg' => 'Элементы по данной накладной отправлены на буферный склад', 'reload' => true]
            : ['ok' => false, 'msg' => 'Не удалось поместить элементы на склад'];
    }

    /**
     * ToSklad_Click, счёт на детали (WhoOpen 2): CheckNumNakl; xp_frmMassInputExistMark (ID, 2); если не всё на складе
     * (ElNotInSklad(False, True)) — PutInSkladByNakl(ID, True, , True); потом CloseSchDetal(PrSchID) и IsReadySostDetal(ID).
     * Вопросы «Установить состояние в 'Изготовлено' у …?» задаются после записи (в Access Try2SwitchSost тоже шёл
     * отдельным вызовом): ответ sost [{id, text}], клиент спрашивает по одному и вызывает действие Sost.
     */
    private function toSkladDet(int $nakl, array $rec): array
    {
        if ($txt = $this->numNaklError($nakl)) return ['ok' => false, 'msg' => $txt, 'reload' => true];
        if ((int)LegacySp::sp4($nakl, 'dbo.xp_frmMassInputExistMark', 2, 0, 'int') > 0) return ['ok' => false, 'msg' => "У некоторых позиций не заполнено поле 'Маркировка'"];
        if ($this->notInSklad($nakl, '', true) <= 0) return ['ok' => true];
        $r = $this->putInSkladByNakl($nakl, true);
        if (!$r['ok']) return $r + ['reload' => true];
        $notes = [];
        // CloseSchDetal(PrSchID): CheckNaklDetal = xp_ProdPlanGetSostSch; 0 — xp_CloseSchDetal, вернул 0 — «Не удалось закрыть счёт»
        $pr = (int)($rec['PrSchID'] ?? 0);
        if ($pr !== 0 && LegacySp::sp1($pr, 'dbo.xp_ProdPlanGetSostSch', -1) === 0 && LegacySp::sp1($pr, 'dbo.xp_CloseSchDetal', 0) === 0) {
            $notes[] = 'Не удалось закрыть счёт';
        }
        // IsReadySostDetal: процедура заполняет tmpProdPlan2Buf; > 0 — окно ProdPlanSnd2Buf (OpenArgs 0) после вопросов о состоянии
        $buf = LegacySp::sp1($nakl, 'dbo.xp_ProdPlanIsReadySostDetal', 0) > 0;
        Yii::$app->session->set('naklSost', array_column($r['sost'], 'id'));
        return ['ok' => true, 'msg' => implode("\n\n", array_filter([$r['msg'], ...$notes])), 'sost' => $r['sost'], 'reload' => true]
            + ($buf ? ['buf' => 0] : []);
    }

    /**
     * PutInSkladByNakl(NaklID, chng = True, AllID = 0, IsDetal): одна транзакция.
     * xp_fn_PutInSklad1ByNakl (NaklID, AllID, IsDetal, WasBuf OUT) → строки; по каждой новая средняя цена,
     * xp_fn_PutInSklad4 → res 1: xp_fn_PutInSklad3New ('0' — откат, '1' — дальше, иначе вопрос о состоянии), 2 — пропуск;
     * в конце xp_StatReservInfAboutAction '-'.
     */
    private function putInSkladByNakl(int $nakl, bool $det): array
    {
        $db = Yii::$app->db;
        $pdo = $db->getMasterPdo();
        $t = $db->beginTransaction();
        try {
            $st = $pdo->prepare('SET NOCOUNT ON; DECLARE @w int; EXEC dbo.xp_fn_PutInSklad1ByNakl ?, ?, ?, @w OUTPUT; SELECT @w AS WasBuf');
            $st->execute([$nakl, 0, $det ? 1 : 0]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC);
            $wasBuf = 0;
            if ($st->nextRowset()) $wasBuf = (int)$st->fetchColumn();
            $st->closeCursor();
            if (!$rows) {
                if ($wasBuf === 0) { $t->rollBack(); return ['ok' => false, 'msg' => 'По данной накладной все элементы уже занесены на склад']; }
                $t->commit();
                return ['ok' => true, 'msg' => 'По данной накладной все элементы занесены на буферный склад', 'sost' => []];
            }
            $sost = []; $buf = false;
            foreach ($rows as $el) {
                $kol = (float)$el['Количество']; $oldKol = (float)$el['KolInSklad']; $oldPrc = (float)$el['PrcInSklad'];
                $sm = ($oldKol + $kol) != 0 ? round(($kol * (float)$el['ЦенаОптовая'] + $oldKol * $oldPrc) / ($oldKol + $kol), 4) : 0;
                $res = (int)$this->scalar('DECLARE @r int; EXEC dbo.xp_fn_PutInSklad4 ?, ?, ?, ?, ?, ?, @r OUTPUT; SELECT @r',
                    [mb_substr((string)($el['ChangeTo'] ?? ''), 0, 100), (int)$kol, $sm, (int)$el['SkladID'], (int)$el['AllID'], (int)($el['IDPosProcess'] ?? 0)]);
                if ($res === 2) continue;                              // в счёте 1С составляющая детали
                if ($res !== 1) { $t->rollBack(); return ['ok' => false, 'msg' => 'Не удалось поместить элемент на склад']; }
                $r3 = (string)$this->scalar("DECLARE @r nvarchar(255), @e nvarchar(10); EXEC dbo.xp_fn_PutInSklad3New ?, ?, ?, ?, ?, ?, @r OUTPUT, @e OUTPUT; SELECT @r",
                    [(int)$el['AllID'], (int)$oldKol, $oldPrc, (int)$el['SkladID'], (int)$kol, $nakl]);
                if ($r3 === '0') { $t->rollBack(); return ['ok' => false, 'msg' => 'Не удалось поместить элемент на склад']; }
                if ($r3 !== '1') $sost[] = ['id' => (int)($el['IDPosProcess'] ?? 0), 'text' => "Установить состояние в 'Изготовлено' у $r3?"];
                if ((int)($el['WasBuf'] ?? 0) > 0) $buf = true;
            }
            $db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_StatReservInfAboutAction ?', [1 => '-'])->execute();
            $t->commit();
        } catch (\Throwable $e) {
            if ($t->isActive) $t->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
        return ['ok' => true, 'sost' => array_values(array_filter($sost, fn($x) => $x['id'] > 0)),
                'msg' => 'Элементы по данной накладной отправлены на склад' . ($buf ? "\nНекоторые позиции занесены на буферный склад" : '')];
    }

    /** Try2SwitchSost(IDPosProcess): только для позиций, о которых спросили после «На склад». */
    public function switchSost(int $idProcess): array
    {
        if (!in_array($idProcess, (array)Yii::$app->session->get('naklSost', []), true)) return ['ok' => false, 'msg' => 'Нет такого вопроса'];
        $i = LegacySp::sp1($idProcess, 'dbo.xp_Try2SwitchSost', 0);
        if ($i > 0) return ['ok' => true, 'msg' => "Количество изменённых записей - $i"];
        if ($i === -1) return ['ok' => false, 'msg' => 'Не найдена карточка исполнителя'];
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ правка строк (SubFrmqrytblAll)

    /** Столбцы строки, которые меняются в режиме правки: поле таблицы → поле tblAll. */
    public const ROW = [
        'Kol' => 'Количество', 'KolByNakl' => 'KolByNakl', 'ТочноеНазвание' => 'ТочноеНазвание', 'GTD' => 'GTD',
        'OrigCountry' => 'OrigCountry', 'PriceWNDS' => 'PriceWNDS', 'SumNDS' => 'SumNDS', 'Vsego' => 'Vsego',
    ];

    /**
     * AfterUpdate полей подформы SubFrmqrytblAll (в режиме правки). Ответ: ok, msg, row (строка заново из xp_SubFrmqrytblAllRS1), sum.
     * - ТочноеНазвание, GTD, KolByNakl — просто сохранить (acCmdSaveRecord);
     * - OrigCountry — только из справочника xp_lstCountry (NotInList: «Указанной страны нет в справочнике»);
     * - Количество: строка не на складе (InSklad 0, 2) — сохранить; на складе — UpdKolInNakl_Sch (updKolInNakl);
     * - SumNDS: в рублях Vsego = SumNDS + SumWNDS; в валюте — xp_frmMassInputUpdPrc (AllID, '1' & сумма);
     * - Vsego: в рублях сохранить; в валюте — xp_frmMassInputUpdPrc '2' & сумма;
     * - PriceWNDS: в валюте — xp_frmMassInputUpdPrc '0' & цена; в рублях — CorrectPrice (correctPrice).
     */
    public function rowSave(int $nakl, int $sch, int $allId, string $field, ?string $value): array
    {
        $rec = $this->own($nakl, $sch);
        $det = $sch < 0;
        [$ok, $warn] = $this->allowEdit($nakl, $det);
        if (!$ok) return ['ok' => false, 'msg' => $warn];
        $col = self::ROW[$field] ?? null;
        if ($col === null) return ['ok' => false, 'msg' => 'Это поле нельзя менять'];
        $db = Yii::$app->db;
        $row = $db->createCommand('SELECT AllID, InSklad, Количество AS Kol, SumWNDS, SkladID, ЦенаОптовая AS Prc, PriceWNDS FROM dbo.tblAll WHERE AllID = :a AND ShcID = :n',
            [':a' => $allId, ':n' => self::rowShc($nakl, $det)])->queryOne();
        if (!$row) return ['ok' => false, 'msg' => 'Строка не найдена в этой накладной'];
        $inUE = (int)($rec['InUE'] ?? 0) > 0;
        $value = trim((string)$value);
        $num = fn($x) => is_numeric($n = str_replace([' ', ','], ['', '.'], $x)) ? (float)$n : null;
        $upd = fn(string $c, $v) => $db->createCommand("UPDATE dbo.tblAll SET [$c] = :v WHERE AllID = :a", [':v' => $v, ':a' => $allId])->execute();
        $prc = fn(string $rgm, float $v) => LegacySp::sp6($allId, 'dbo.xp_frmMassInputUpdPrc', $rgm . str_replace('.', ',', (string)$v), 0, 255);

        switch ($field) {
            case 'ТочноеНазвание':
            case 'GTD':
                $upd($col, $value === '' ? null : mb_substr($value, 0, 255));
                break;
            case 'OrigCountry':
                if ($value !== '') {
                    $list = $this->countries();
                    if (!in_array($value, $list, true)) return ['ok' => false, 'msg' => 'Указанной страны нет в справочнике', 'row' => $this->row($nakl, $det, $allId)];
                }
                $upd($col, $value === '' ? null : $value);
                break;
            case 'KolByNakl':
            case 'Kol':
                if ($value === '' || !ctype_digit($value)) return ['ok' => false, 'msg' => 'Укажите целое количество', 'row' => $this->row($nakl, $det, $allId)];
                $k = (int)$value;
                if ($field === 'Kol' && (int)$row['InSklad'] === 1) {
                    if ($k === (int)$row['Kol']) break;
                    $r = $this->updKolInNakl($nakl, $det, $rec, $row, $k);
                    return $r + ['row' => $this->row($nakl, $det, $allId), 'sum' => $this->sums($nakl, $det), 'chg' => true];
                }
                $upd($col, $k);
                break;
            case 'SumNDS':
            case 'Vsego':
            case 'PriceWNDS':
                $v = $num($value);
                if ($v === null) return ['ok' => false, 'msg' => 'Неверное число', 'row' => $this->row($nakl, $det, $allId)];
                if ($field === 'PriceWNDS' && !$inUE) {
                    // PriceWNDS_AfterUpdate: запись сохранена, затем CorrectPrice; Res = 0 — вернуть старую цену
                    $upd($col, $v);
                    $r = $this->correctPrice((int)($rec['PrSchID'] ?? 0), (int)$row['SkladID'], $v, $det);
                    if (!$r['ok']) $upd($col, $row['PriceWNDS']);
                    return $r + ['row' => $this->row($nakl, $det, $allId), 'sum' => $this->sums($nakl, $det), 'chg' => true];
                }
                if ($field === 'Vsego' && !$inUE) return ['ok' => false, 'msg' => 'Это поле нельзя менять'];   // Vsego открыто только в валюте
                $upd($col, $v);
                if ($field === 'SumNDS' && !$inUE) {
                    $db->createCommand('UPDATE dbo.tblAll SET Vsego = SumNDS + ISNULL(SumWNDS, 0) WHERE AllID = :a', [':a' => $allId])->execute();
                } elseif ($inUE && $prc(['PriceWNDS' => '0', 'SumNDS' => '1', 'Vsego' => '2'][$field], $v) <= 0) {
                    return ['ok' => false, 'msg' => $field === 'PriceWNDS' ? 'Не удалось изменить цену' : 'Не удалось изменить сумму',
                            'row' => $this->row($nakl, $det, $allId), 'sum' => $this->sums($nakl, $det)];
                }
                break;
        }
        return ['ok' => true, 'row' => $this->row($nakl, $det, $allId), 'sum' => $this->sums($nakl, $det), 'chg' => true];
    }

    /**
     * UpdKolInNakl_Sch (Rgm 0): смена количества у строки, уже занесённой на склад (InSklad = 1).
     * Как в Access запись сначала сохраняется (acCmdSaveRecord), затем:
     * 1) KolN = xp_GetKolInSch[Detal] (PrSchID, SkladID); сумма по всем накладным счёта (xp_ShcetaGetIDs → xp_ShcetaGetAllIDs)
     *    больше KolN (только SkladID > 0) — «Кол-во по накладной больше, чем в счёте», вернуть старое;
     * 2) не детали: Kl = старое − новое; Sklad.Количество < Kl — «Невозможно уменьшить запасы…», вернуть старое;
     *    иначе RecountAvgPrice, затем в одной транзакции xp_CorrectInput + xp_UpdateKolInSkladByNakl;
     *    детали: при Kl > 0 только xp_CorrectInput (склад деталей Access здесь не трогает);
     * 3) новое = 0 — строка удаляется, иначе пересчёт SumWNDS, Vsego, SumNDS.
     * Отличие от Access: после «больше, чем в счёте» Access всё равно пересчитывал суммы по новому количеству при
     * вернутом старом (суммы расходились с количеством) — здесь на этом выходим.
     */
    private function updKolInNakl(int $nakl, bool $det, array $rec, array $row, int $new): array
    {
        $db = Yii::$app->db;
        $allId = (int)$row['AllID']; $skladId = (int)$row['SkladID']; $old = (int)$row['Kol'];
        $prSch = (int)($rec['PrSchID'] ?? 0);
        $shc = $det ? -$nakl : $nakl;                                  // txtShcID (у деталей Access передаёт -txtShcID)
        $kd = (int)(($this->row($nakl, $det, $allId) ?? [])['Код'] ?? 0);
        $sklId = $skladId > 0 ? $skladId : $kd;
        $setKol = fn(int $k) => $db->createCommand('UPDATE dbo.tblAll SET Количество = :k WHERE AllID = :a', [':k' => $k, ':a' => $allId])->execute();
        $setKol($new);

        $kolN = (int)LegacySp::sp4($prSch, $det ? 'dbo.xp_GetKolInSchDetal' : 'dbo.xp_GetKolInSch', $skladId, 0, 'int');
        $total = 0;
        foreach ($this->exec('dbo.xp_ShcetaGetIDs', [$prSch, $det ? 1 : 0])->queryColumn() as $sid) {
            foreach ($this->exec('dbo.xp_ShcetaGetAllIDs', [(int)$sid, $skladId])->queryAll() as $r) {
                $total += ((int)$sid === $shc && (int)$r['AllID'] === $allId) ? $new : (int)$r['Количество'];
            }
        }
        if ($skladId > 0 && $total > 0 && $kolN < $total) {
            $setKol($old);
            return ['ok' => false, 'msg' => 'Кол-во по накладной больше, чем в счёте'];
        }

        $kl = $old - $new;
        $msg = '';
        $textOf = fn(string $t) => $t === '' ? 'Данные по складу откорректированы'
            : (str_ends_with($t, ',') ? 'Внесены изменения в следующие списки занесения на склад ' . mb_substr($t, 0, -1) : $t);
        if (!$det) {
            $stock = $db->createCommand('SELECT Количество FROM dbo.Sklad WHERE Код = :k', [':k' => $sklId])->queryScalar();
            if ($stock !== false) {
                if ((float)$stock < $kl) {
                    $setKol($old);
                    return ['ok' => false, 'msg' => 'Невозможно уменьшить запасы на складе на указанную величину'];
                }
                $this->recountAvgPrice($shc, $sklId, $new, $kl, (float)$row['Prc'], false);
                $t = $db->beginTransaction();
                try {
                    $res = $this->correctInput($kl, abs($shc), $skladId, $allId);
                    $db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_UpdateKolInSkladByNakl ?, ?, ?', [1 => $skladId, 2 => $kl, 3 => $shc])->execute();
                    $t->commit();
                } catch (\Throwable $e) {
                    if ($t->isActive) $t->rollBack();
                    $setKol($old);
                    Yii::error($e->getMessage(), __METHOD__);
                    return ['ok' => false, 'msg' => $e->getMessage() . "\nОткат транзакции"];
                }
                $msg = $textOf($res);
            }
        } elseif ($kl > 0) {
            $msg = $textOf($this->correctInput($kl, abs($shc), $skladId, $allId));
        }

        if ($new === 0) {
            $db->createCommand('DELETE FROM dbo.tblAll WHERE AllID = :a', [':a' => $allId])->execute();
            return ['ok' => true, 'msg' => $msg, 'del' => true];
        } else {
            $db->createCommand('UPDATE dbo.tblAll SET SumWNDS = round(:k * PriceWNDS, 2), Vsego = round(:k2 * PriceWNDS * round((100 + NDS) / 100, 2), 2),'
                . ' SumNDS = round(:k3 * PriceWNDS * round(NDS / 100, 2), 2) WHERE AllID = :a', [':k' => $new, ':k2' => $new, ':k3' => $new, ':a' => $allId])->execute();
        }
        return ['ok' => true, 'msg' => $msg];
    }

    /** CorrectInput: xp_CorrectInput (Kolvo float, SkladID, ShcID, AllID, res nvarchar(255) OUT) → текст. */
    private function correctInput(float $kolvo, int $shcId, int $skladId, int $allId): string
    {
        return (string)$this->scalar('DECLARE @r nvarchar(255); EXEC dbo.xp_CorrectInput ?, ?, ?, ?, @r OUTPUT; SELECT @r',
            [$kolvo, $skladId, $shcId, $allId]);
    }

    /**
     * RecountAvgPrice(IDNakl, SkladID, NewKol, Kl, NewPrice, IsDetal): средняя оптовая цена склада после правки накладной.
     * Нет ДатаНакладной — 0. Иначе в транзакции идём по строкам tblAll этого SkladID с датой накладной ≥ даты этой накладной
     * (по дате, AllID): цена = (Nk·NP + OldOptPrice·OldKol) / (Nk + OldKol) (OldKol ≤ 0 — NP; Nk или NP = 0 — OldOptPrice);
     * у следующей строки OldOptPrice = цена, OldKol −= Kl; после последней Sklad.ЦенаОптовая = цена (SkladID > 0). Успех — 1.
     */
    public function recountAvgPrice(int $idNakl, int $skladId, int $newKol, int $kl, float $newPrice, bool $det): int
    {
        $db = Yii::$app->db;
        $dta = $db->createCommand('SELECT ДатаНакладной FROM dbo.tblShceta WITH (NOLOCK) WHERE ShcID = :n', [':n' => abs($idNakl)])->queryScalar();
        if (!$dta) return 0;
        $join = $det ? '-dbo.tblAll.ShcID = dbo.tblShceta.ShcID' : 'dbo.tblAll.ShcID = dbo.tblShceta.ShcID';
        $t = $db->beginTransaction();
        try {
            $rows = $db->createCommand("SELECT tblAll.AllID, tblAll.ЦенаОптовая, tblAll.Количество, tblAll.OldOptPrice, tblAll.OldKol FROM dbo.tblShceta"
                . " INNER JOIN dbo.tblAll ON $join WHERE tblAll.SkladID = :s AND tblShceta.ДатаНакладной >= CONVERT(date, :d)"
                . ' ORDER BY tblShceta.ДатаНакладной, tblAll.AllID', [':s' => $skladId, ':d' => substr((string)$dta, 0, 10)])->queryAll();
            $nk = $newKol; $np = $newPrice; $prc = 0.0;
            foreach ($rows as $i => $r) {
                if ($r['OldKol'] === null) throw new \RuntimeException('Пустое OldKol у строки ' . $r['AllID']);   // в VBA Kol = Null → ошибка
                $oldKol = (float)$r['OldKol'];
                if ($nk == 0 || $np == 0) $prc = (float)$r['OldOptPrice'];
                elseif ($oldKol <= 0) $prc = $np;
                else $prc = ($nk * $np + (float)$r['OldOptPrice'] * $oldKol) / ($nk + $oldKol);
                $next = $rows[$i + 1] ?? null;
                if ($next) {
                    $nk = (int)$next['Количество']; $np = (float)$next['ЦенаОптовая'];
                    $db->createCommand('UPDATE dbo.tblAll SET OldOptPrice = :p, OldKol = OldKol - :k WHERE AllID = :a',
                        [':p' => $prc, ':k' => $kl, ':a' => (int)$next['AllID']])->execute();
                } elseif ($skladId > 0) {
                    $db->createCommand('UPDATE dbo.Sklad SET ЦенаОптовая = :p, UserCur = CURRENT_USER, DT = GETDATE() WHERE Код = :s',
                        [':p' => $prc, ':s' => $skladId])->execute();
                }
            }
            $t->commit();
            return 1;
        } catch (\Throwable $e) {
            if ($t->isActive) $t->rollBack();
            Yii::error('Не удалось рассчитать среднюю оптовую цену: ' . $e->getMessage(), __METHOD__);
            return 0;
        }
    }

    /**
     * CorrectPrice(IDSch = PrSchID, SkladID, Sm, Rgm = WhoOpen): цена в рублях. По всем строкам накладных счёта с этим SkladID
     * (tblShceta.ShcID = tblAll.ShcID — у деталей строки с минусом сюда не попадают, как в Access):
     * ЦенаОптовая = Sm·NDS/KursUSD (4 знака), PriceWNDS = Sm, SumWNDS, SumNDS, Vsego; RecountAvgPrice по строке, 0 — вернуть цену и стоп.
     * NDS = xp_GetNDS (2), KursUSD = xp_GetKursByVal 840. Счёт на элементы (WhoOpen 0): то же в tblSchEl в транзакции.
     */
    private function correctPrice(int $prSch, int $skladId, float $sm, bool $det): array
    {
        $db = Yii::$app->db;
        $kurs = (new SchetPosElemService())->kursByVal(840);
        if ($kurs == 0) return ['ok' => false, 'msg' => 'Не найден курс доллара'];
        $nds = (float)str_replace(',', '.', LegacySp::sp2(2, 'dbo.xp_GetNDS', '1.22', 10));
        $rows = $db->createCommand('SELECT tblAll.AllID, tblAll.Количество AS Kol, tblAll.ЦенаОптовая AS Prc, tblShceta.ShcID FROM dbo.tblShceta'
            . ' INNER JOIN dbo.tblAll ON tblShceta.ShcID = tblAll.ShcID WHERE tblShceta.PrSchID = :p AND tblAll.SkladID = :s',
            [':p' => $prSch, ':s' => $skladId])->queryAll();
        $res = 1;
        foreach ($rows as $r) {
            $kol = (float)$r['Kol'];
            $opt = round($sm * $nds / $kurs * 10000) / 10000;
            $db->createCommand('UPDATE dbo.tblAll SET ЦенаОптовая = :o, PriceWNDS = :sm, SumWNDS = :a, SumNDS = :b, Vsego = :c WHERE AllID = :id',
                [':o' => $opt, ':sm' => $sm, ':a' => round($sm * $kol, 2), ':b' => round($sm * $kol * ($nds - 1), 2),
                 ':c' => round($sm * $kol * $nds, 2), ':id' => (int)$r['AllID']])->execute();
            $res = $this->recountAvgPrice((int)$r['ShcID'], $skladId, (int)$kol, 0, $opt, false);
            if ($res === 0) {
                $db->createCommand('UPDATE dbo.tblAll SET ЦенаОптовая = :o WHERE AllID = :id', [':o' => $r['Prc'], ':id' => (int)$r['AllID']])->execute();
                return ['ok' => false, 'msg' => 'Не удалось рассчитать среднюю оптовую цену'];
            }
        }
        if (!$det) {   // одним UPDATE (в Access — набор записей в транзакции); n = round((NDS + 100) / 100, 2)
            try {
                $db->createCommand('UPDATE dbo.tblSchEl SET ЦенаОптовая = ROUND(CAST(:sm1 AS float) * ROUND((NDS + 100) / 100.0, 2) / CAST(:k AS float), 4), PriceWNDS = :sm2,'
                    . ' SumWNDS = ROUND(CAST(:sm3 AS float) * Количество, 2), Vsego = ROUND(CAST(:sm4 AS float) * Количество * ROUND((NDS + 100) / 100.0, 2), 2)'
                    . ' WHERE ShcID = :p AND SkladID = :s',
                    [':sm1' => $sm, ':sm2' => $sm, ':sm3' => $sm, ':sm4' => $sm, ':k' => $kurs, ':p' => $prSch, ':s' => $skladId])->execute();
            } catch (\Throwable $e) {
                Yii::error($e->getMessage(), __METHOD__);
                return ['ok' => false, 'msg' => $e->getMessage() . "\nНе удалось изменить оптовую цену"];
            }
        }
        return ['ok' => true];
    }

    /**
     * Даты строки (двойной щелчок по «Дата произв.» / «Годен до», fnWarrantyNaklUpdDate). Вопросы, календарь и срок (frmInputBox,
     * OpenArgs 2: число + d/m/y) задаёт экран; сюда приходит готовая строка: 'ггггммдд' + срок ('m12') — дата производства и срок,
     * или 'ггггммдд-1' — «Годен до». WarrantyNaklUpdDateProc = xp_WarrantyNaklUpdDate (AllID, Dta, NUpak = 0, res OUT).
     */
    public function warranty(int $nakl, int $sch, int $allId, string $dta): array
    {
        $det = $sch < 0;
        $this->own($nakl, $sch);
        if (!preg_match('/^\d{8}(-1|[dmy]\d{1,4})$/', $dta)) return ['ok' => false, 'msg' => 'Неверная дата или срок'];
        if (!$this->rowOf($nakl, $det, $allId)) return ['ok' => false, 'msg' => 'Строка не найдена в этой накладной'];
        $res = (int)$this->scalar('DECLARE @r int; EXEC dbo.xp_WarrantyNaklUpdDate ?, ?, ?, @r OUTPUT; SELECT @r', [$allId, $dta, 0]);
        return ['ok' => true, 'res' => $res, 'row' => $this->row($nakl, $det, $allId)];
    }

    /** Ostatok_DblClick: GetFromBox(«Введите остаток», 1) → CLng; sp6(AllID, xp_WarrantyNaklUpdOSt, CStr(Kol), 100) = 1 — успех. */
    public function ostatok(int $nakl, int $sch, int $allId, string $value): array
    {
        $det = $sch < 0;
        $this->own($nakl, $sch);
        $v = str_replace(',', '.', trim($value));
        if (!is_numeric($v)) return ['ok' => false, 'msg' => 'Необходимо ввести числовое значение'];
        if (!$this->rowOf($nakl, $det, $allId)) return ['ok' => false, 'msg' => 'Строка не найдена в этой накладной'];
        $kol = (int)round((float)$v);                                   // CLng
        if (LegacySp::sp6($allId, 'dbo.xp_WarrantyNaklUpdOSt', (string)$kol, 0, 100) !== 1) {
            return ['ok' => false, 'msg' => 'Не удалось отредактировать запись'];
        }
        return ['ok' => true, 'row' => $this->row($nakl, $det, $allId)];
    }

    private function rowOf(int $nakl, bool $det, int $allId): bool
    {
        return (bool)Yii::$app->db->createCommand('SELECT 1 FROM dbo.tblAll WHERE AllID = :a AND ShcID = :n',
            [':a' => $allId, ':n' => self::rowShc($nakl, $det)])->queryScalar();
    }

    /** tblAll.ShcID строк накладной: у накладной (акта) на детали строки записаны с минусом (-ShcID). */
    public static function rowShc(int $nakl, bool $det): int
    {
        return $det ? -$nakl : $nakl;
    }

    /** Одна строка накладной так, как её показывает таблица. */
    private function row(int $nakl, bool $det, int $allId): ?array
    {
        foreach ($this->exec('dbo.xp_SubFrmqrytblAllRS1', [$nakl, $det ? 1 : 0])->queryAll() as $r) {
            if ((int)$r['AllID'] === $allId) return $r;
        }
        return null;
    }

    private function sums(int $nakl, bool $det): array
    {
        return $this->safe(fn() => $this->exec($det ? 'dbo.xp_CountSumScDetal' : 'dbo.xp_CountSumSch', [$nakl])->queryAll(\PDO::FETCH_NUM), []);
    }

    /** Справочник стран (RowSource OrigCountry = xp_lstCountry), первый столбец. */
    public function countries(): array
    {
        return array_values(array_filter(array_map(fn($r) => trim((string)reset($r)), $this->safe(fn() => $this->exec('dbo.xp_lstCountry', [])->queryAll(), [])), 'strlen'));
    }

    /** Подсказки «Маркировка» (RowSource ТочноеНазвание = xp_SubfrmqrytblAll SkladID), первый столбец; можно ввести и своё. */
    public function marks(int $sklad): array
    {
        return array_values(array_unique(array_filter(array_map(fn($r) => trim((string)reset($r)), $this->safe(fn() => $this->exec('dbo.xp_SubfrmqrytblAll', [$sklad])->queryAll(), [])), 'strlen')));
    }

    // ------------------------------------------------------------------ ＋ позиции из счёта (frmAddEl2Nakl)

    /**
     * butDob_Click: fnAllowEditNakl; FillEl2Nakl(PrSchID, IsDet) — во временную dbo.tblEl2Nakl (us = CURRENT_USER) позиции
     * счёта, которых ещё нет в накладных (xp_GetElemNotInNakl); окно frmAddEl2Nakl = xp_frmAddEl2NaklRS WhoOpen.
     */
    public function addList(int $nakl, int $sch): array
    {
        $rec = $this->own($nakl, $sch);
        [$ok, $warn] = $this->allowEdit($nakl, $sch < 0);
        if (!$ok) return ['ok' => false, 'msg' => $warn];
        $db = Yii::$app->db;
        $t = $db->beginTransaction();
        try {
            $db->createCommand('DELETE FROM dbo.tblEl2Nakl WHERE us = CURRENT_USER')->execute();
            $src = $this->exec('dbo.xp_GetElemNotInNakl', [(int)($rec['PrSchID'] ?? $sch), $sch < 0 ? 1 : 0])->queryAll();
            foreach ($src as $r) {
                $db->createCommand('INSERT INTO dbo.tblEl2Nakl (SkladID, Kolvo, AllID, us) VALUES (:s, :k, :a, CURRENT_USER)',
                    [':s' => $r['SkladID'], ':k' => $r['Kol'], ':a' => $r['AllID']])->execute();
            }
            $t->commit();
        } catch (\Throwable $e) {
            $t->rollBack();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
        if (!$src) return ['ok' => false, 'msg' => 'Нет позиций для добавления'];
        return ['ok' => true, 'add' => true, 'msg' => $warn];
    }

    /** Строки окна frmAddEl2Nakl: xp_frmAddEl2NaklRS WhoOpen (0 — счёт на элементы, 2 — на детали). */
    public function addRows(int $sch): array
    {
        return $this->exec('dbo.xp_frmAddEl2NaklRS', [$sch < 0 ? 2 : 0])->queryAll();
    }

    /**
     * Кнопка20_Click: в одной транзакции xp_MakeNaklByPos (Rgm = WhoOpen, ID = накладная, AllID, Kolvo, res OUT)
     * для строк с Kolvo > 0; Add2ChgNakl(ID, False). Количество не больше, чем в счёте (Kolvo_AfterUpdate).
     * После — DELETE tblEl2Nakl (как «dal» в butDob_Click).
     * @param array $items [AllID => Kolvo]
     */
    public function addSave(int $nakl, int $sch, array $items): array
    {
        $this->own($nakl, $sch);
        $db = Yii::$app->db;
        $max = [];
        foreach ($this->addRows($sch) as $r) $max[(int)$r['AllID']] = (int)$r['Kolvo'];
        $t = $db->beginTransaction();
        try {
            foreach ($items as $allId => $kol) {
                $allId = (int)$allId; $kol = (int)$kol;
                if ($kol <= 0) continue;
                if (!isset($max[$allId])) throw new \RuntimeException('Позиции нет в списке для добавления');
                if ($kol > $max[$allId]) throw new \RuntimeException('Количество по накладной больше, чем в счёте');
                $this->scalar('DECLARE @r int; EXEC dbo.xp_MakeNaklByPos ?, ?, ?, ?, @r OUTPUT; SELECT @r', [$sch < 0 ? 2 : 0, $nakl, $allId, $kol]);
            }
            $t->commit();
        } catch (\Throwable $e) {
            $t->rollBack();
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
        $this->lock($nakl, true);
        $this->addCancel();
        return ['ok' => true, 'close' => true];
    }

    /** Окно закрыто без добавления: очистить временную таблицу текущего пользователя. */
    public function addCancel(): array
    {
        Yii::$app->db->createCommand('DELETE FROM dbo.tblEl2Nakl WHERE us = CURRENT_USER')->execute();
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ закрытие

    /**
     * butExit_Click + Form_Close. $edit — окно открыто для правки (OpenArgs 7), $chg — были изменения (IsChg).
     * Ответ close: true — окно закрывается (накладная отпускается: Add2ChgNakl(ID, True), TakeMeDate2Null(счёт)).
     */
    public function close(int $nakl, int $sch, bool $edit, bool $chg, array $answers): array
    {
        $rec = $this->own($nakl, $sch);
        $db = Yii::$app->db;
        $det = $sch < 0;
        $done = function (array $extra = []) use ($nakl, $sch, $rec) {
            $this->lock($nakl, false);                                                   // Form_Close: Add2ChgNakl(ID, True)
            if ($sch > 0 && (int)($rec['PrSchID'] ?? 0) > 0) {                           // WhoOpen 0: TakeMeDate2Null(PrSchID)
                $this->safe(fn() => Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_TakeMeDate2Null ?', [1 => (int)$rec['PrSchID']])->execute(), null);
            }
            return ['ok' => true, 'close' => true, 'reload' => true] + $extra;
        };
        $delNakl = fn() => $db->createCommand('DELETE FROM dbo.tblShceta WHERE ShcID = :id', [':id' => $nakl])->execute();

        $err = $this->numNaklError($nakl);
        if ($err === null) {
            if ($chg) {
                $db->createCommand('UPDATE dbo.tblShceta SET UserCur = CURRENT_USER, DateSosd = ISNULL(DateSosd, GETDATE()) WHERE ShcID = :id', [':id' => $nakl])->execute();
            }
        } else {
            if (!$edit) return $done();                                                  // OpenArgs пусто: просто закрыть
            if (!isset($answers['save'])) return ['ask' => ['key' => 'save', 'text' => 'Сохранить изменения?']];
            if ($answers['save']) return ['ok' => false, 'msg' => $err, 'reload' => true];  // CheckNumNakl() с сообщением → остаёмся
            $delNakl();                                                                  // «Нет» — накладная удаляется
            return $done();
        }
        if (LegacySp::sp1($nakl, 'dbo.xp_GetKolFromInNakl', 0) === 0) { $delNakl(); return $done(); }   // пустая накладная
        // как в Access: ElNotInSklad(False) без признака деталей (у деталей строки с ShcID < 0, вопрос не возникает)
        if ($this->notInSklad($nakl, '', false) > 0) {
            if (!isset($answers['sklad'])) return ['ask' => ['key' => 'sklad', 'text' => "По данной накладной не все элементы занесены на склад.\nЗанести?"]];
            if ($answers['sklad']) {
                $go = true;
                if (!$det && $this->notInSklad($nakl, (string)($rec['Org'] ?? ''), false) > 0) {
                    if (!isset($answers['diff'])) return ['ask' => ['key' => 'diff', 'text' => 'В накладной есть позиции с разными поставщиками. Занести на склад?']];
                    $go = (bool)$answers['diff'];
                }
                if ($go && $this->putInTmpSklad($nakl) && !isset($answers['tmpok'])) {
                    // сообщение показываем и идём дальше (повтор запроса с tmpok, чтобы не занести второй раз)
                    return ['ask' => null, 'info' => 'Элементы по данной накладной отправлены на буферный склад', 'answers' => ['tmpok' => 1, 'sklad' => 0]];
                }
            } else {
                $db->createCommand('DELETE FROM dbo.tblAll WHERE ShcID = :id AND InSklad = 0', [':id' => $nakl])->execute();
                if (LegacySp::sp1($nakl, 'dbo.xp_GetKolFromInNakl', 0) === 0) { $delNakl(); return $done(); }
            }
        }
        if (LegacySp::sp1($nakl, 'dbo.xp_CheckFillGTD', 0) > 0) {
            if (!isset($answers['gtd'])) return ['ask' => ['key' => 'gtd', 'text' => 'Не указаны страна происхождения или ГТД. Будете указывать?']];
            if ($answers['gtd']) return ['ok' => true, 'chg' => true];                   // остаёмся в окне, IsChg = True
        }
        return $done();
    }

    /** Накладная этого счёта (как lstNakl карточки). Возвращает запись xp_frmMassInputRSNew. */
    public function own(int $nakl, int $sch): array
    {
        if ($sch > 0) {
            $rec = $this->exec('dbo.xp_frmMassInputRSNew', [0, $nakl])->queryOne();
            if (!$rec || (int)($rec['PrSchID'] ?? 0) !== $sch) throw new \yii\web\NotFoundHttpException('Накладная не найдена в этом счёте');
            return $rec;
        }
        $card = (new SchetService())->getDetal($sch);
        $ids = $card ? array_map(fn($r) => (int)reset($r), $card['nakl']) : [];
        if (!in_array($nakl, $ids, true)) throw new \yii\web\NotFoundHttpException('Накладная не найдена в этом счёте');
        return $this->exec('dbo.xp_frmMassInputRSNew', [0, $nakl])->queryOne() ?: [];
    }

    private function scalar(string $sql, array $values)
    {
        $bind = [];
        foreach (array_values($values) as $i => $v) $bind[$i + 1] = $v;
        return Yii::$app->db->createCommand('SET NOCOUNT ON; ' . $sql, $bind)->queryScalar();
    }

    /** SetRS4WhereBy: xp_frmWhereByRS SkladID — где и почём покупали элемент. */
    public function whereBy(int $skladId): array
    {
        return $this->safe(fn() => $this->exec('dbo.xp_frmWhereByRS', [$skladId])->queryAll(), []);
    }

    private function safe(callable $f, $dflt)
    {
        try {
            return $f();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return $dflt;
        }
    }

    private function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind  = [];
        foreach (array_values($values) as $i => $v) $bind[$i + 1] = $v;
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc $marks", $bind);
    }
}
