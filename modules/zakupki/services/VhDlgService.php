<?php
namespace app\modules\zakupki\services;

use app\components\AccessFormat as F;
use app\components\LegacyMail;
use app\services\LegacySp;
use Yii;

/**
 * Окна, которые открываются кнопками «Входящих счетов» (frmStat) и карточки счёта:
 * frmfltSch, frmChgPredShcetaHistory, frmGetTxt4Mail, frmSchPost, frmPlatFrom1C, frmGetTov, frmActsAndReps.
 * Все процедуры идут под логином человека, параметры передаются только через привязку (никаких склеек строк SQL).
 */
class VhDlgService
{
    // ---------------------------------------------------------------- frmfltSch

    /** cboFirma.RowSource = exec dbo.xp_GetOrg4AllScet 'ггггммддггггммдд','' — первая колонка. Пустые даты — пустая строка, как Format$(Null). */
    public function firms(string $dateS, string $datePo, string $holding = ''): array
    {
        $d = $this->ymd($dateS) . $this->ymd($datePo);
        // SetRowS4cboFirma(Firma): второй параметр — холдинг из окна frmfndHolding (пусто — все)
        $rows = $this->exec('dbo.xp_GetOrg4AllScet', [$d, mb_substr($holding, 0, 255)])->queryAll(\PDO::FETCH_NUM);
        return array_values(array_unique(array_map(fn($r) => (string)$r[0], $rows)));
    }

    // ---------------------------------------------------------------- frmChgPredShcetaHistory

    /**
     * GetIDs4SelSch (frmStat): номера видимых счетов → dbo.tmpIDs (Tip 118) через xp_ChgPredShcetaGetList.
     * Процедура выполняет присланный текст; в нём только целые числа, собранные здесь же. Порции до ~900 символов, как в Access:
     * первая с Clr = 1 (очистить старый список), остальные с 0.
     */
    public function chgList(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v !== 0)));
        if (!$ids) return ['ok' => false, 'msg' => ''];
        $clr = 1;
        foreach (array_chunk($ids, 80) as $part) {
            $sql = 'insert into dbo.tmpIDs (ID,Who,Tip) SELECT PredShcID,Current_User,118 FROM dbo.tblPredShceta WHERE PredShcID in ('
                . implode(',', $part) . ')';
            LegacySp::sp7($clr, 'dbo.xp_ChgPredShcetaGetList', $sql, '', 1024, 255);
            $clr = 0;
        }
        return ['ok' => true];
    }

    /**
     * SetRS4PredShcetaHistory: строки (xp_ChgPredShcetaHistoryNew) и итоги (xp_ChgPredShcetaHistoryStat) с одинаковыми параметрами.
     * Rgm 0: Args = «0|1,AllID,ShcID». Rgm 1: Args = «2,0,0» + 'BDta','EDta',Zac,'Sch','Reas','WH','BDSch','EDSch'.
     * В Access в ветке «Дата счёта» строка параметров затиралась (ExtraArgs = Format$(...)) — здесь даты счёта добавляются как задумано.
     */
    public function chgHistory(array $a): array
    {
        if ((int)$a['rgm'] === 0) {
            $p = [(int)$a['all'] ? 1 : 0, (int)$a['allId'], (int)$a['shcId']];
        } else {
            $bds = $this->ymd($a['bds'] ?? '');
            $p = [2, 0, 0,
                $this->ymd($a['bdta'] ?? ''), $this->ymd($a['edta'] ?? ''),
                (int)($a['zac'] ?? 0),
                ($a['sch'] ?? '') === '' ? '%' : (string)$a['sch'],
                ($a['reas'] ?? '') === '' ? '0' : (string)$a['reas'],
                ($a['wh'] ?? '') === '' ? '-1' : (string)$a['wh'],
                $bds === '' ? '20000101' : $bds,
                $bds === '' ? '20000101' : ($this->ymd($a['eds'] ?? '') ?: $bds),
            ];
        }
        $rows = $this->canon($this->exec('dbo.xp_ChgPredShcetaHistoryNew', $p)->queryAll(), ['WhoTxt', 'DT', 'SkladID', 'WhatHappen', 'WasVl', 'CurVl',
            'Reason', 'NumSch', 'DSch', 'Zac', 'ReasonComm', 'Comm', 'Cat', 'ID', 'SmChgR', 'SmChgD']);
        $st = $this->exec('dbo.xp_ChgPredShcetaHistoryStat', $p)->queryOne() ?: [];
        return ['rows' => $rows, 'SmChgR' => $st['SmChgR'] ?? null, 'SmChgD' => $st['SmChgD'] ?? null];
    }

    /** bntChgReason_Click: право ChgPredShcetaHst, затем xp_ChgPredShcetaUpdReason ID, Reason, ReasonComm, @res OUT. */
    public function chgReason(int $id, int $reason, string $comm): array
    {
        if (!Yii::$app->user->can('ChgPredShcetaHst')) return ['ok' => false, 'msg' => 'У Вас нет прав на данное действие'];
        if ($id <= 0 || $reason <= 0) return ['ok' => false, 'msg' => ''];
        Yii::$app->db->createCommand(
            'SET NOCOUNT ON; DECLARE @r nvarchar(255); EXEC dbo.xp_ChgPredShcetaUpdReason ?, ?, ?, @r OUTPUT',
            [1 => $id, 2 => $reason, 3 => mb_substr($comm, 0, 255)]
        )->execute();
        return ['ok' => true];
    }

    // ---------------------------------------------------------------- frmGetTxt4Mail

    /**
     * MakeEmail_Click: xp_GetEmailBySch ID, @res OUT varchar(300).
     * Ответ: '0' + текст или длина адреса (3 знака) + адрес + текст; текст на «?» — вопрос «отправлял письмо … Отправить ещё раз?».
     */
    public function emailBySch(int $id): array
    {
        $st = LegacySp::sp2($id, 'dbo.xp_GetEmailBySch', '', 300);
        if ($st === '') return ['ok' => true, 'email' => '', 'ask' => ''];
        preg_match('/^\s*(\d{1,3})/', substr($st, 0, 3), $m);
        $n = (int)($m[1] ?? 0);
        $skip = $n > 0 ? 3 : strlen($m[0] ?? '');                    // '0' без адреса — один знак
        $email = $n > 0 ? mb_substr($st, 3, $n) : '';
        $rest = mb_substr($st, $n > 0 ? 3 + $n : $skip);
        return ['ok' => true, 'email' => trim($email), 'ask' => str_ends_with(rtrim($rest), '?') ? trim($rest) : ''];
    }

    /** lstTxt.RowSource = exec dbo.xp_GetTxt4MailNew Tip,'Dflt': колонка 0 — номер текста, колонка 2 — что видно в списке. */
    public function txtList(int $tip, string $dflt): array
    {
        $rows = $this->exec('dbo.xp_GetTxt4MailNew', [$tip, mb_substr($dflt, 0, 255)])->queryAll(\PDO::FETCH_NUM);
        return array_map(fn($r) => ['id' => (int)$r[0], 'text' => (string)($r[2] ?? $r[1] ?? '')], $rows);
    }

    /** lstTxt_AfterUpdate: Txt = CommonStartSP2(lstTxt, "dbo.xp_GetTxtMail", "", , , 4000). */
    public function txt(int $id): string
    {
        return LegacySp::sp2($id, 'dbo.xp_GetTxtMail', '', 4000);
    }

    /** AddTxt_Click: < 0 — добавлен (номер = −res), 0 — ошибка, > 0 — такой уже есть (его номер). */
    public function addTxt(int $tip, string $txt): array
    {
        if (trim($txt) === '') return ['ok' => false, 'msg' => ''];
        $r = LegacySp::sp6($tip, 'dbo.xp_AddTxt4Mail', $txt, 0, 2000);
        if ($r < 0) return ['ok' => true, 'sel' => -$r];
        if ($r === 0) return ['ok' => false, 'msg' => 'Произошла ошибка при добавлении текста'];
        return ['ok' => true, 'sel' => $r, 'msg' => 'Такой текст уже существует'];
    }

    /** EditTxt_Click: > 0 — «Текст отредактирован». */
    public function updTxt(int $id, string $txt): array
    {
        if ($id <= 0) return ['ok' => false, 'msg' => ''];
        return LegacySp::sp6($id, 'dbo.xp_UpdateTxt4Mail', $txt, 0, 2000) > 0
            ? ['ok' => true, 'sel' => $id, 'msg' => 'Текст отредактирован'] : ['ok' => false, 'msg' => ''];
    }

    /**
     * SndMl_Click. Tip 0 — письмо по счёту: [Sch], [Dta], [Org] из строки списка (NSch, DtaSch, TP), без темы;
     * после отправки xp_UpdateLogMailOplSch ID, номер текста (без результата) — строка списка перечитывается.
     * Tip 1 — об оплате (из «Платежей по счёту»): ещё [Sm], [PP], [DtaPP] платёжки, тема «Оплата по счёту N от Д»;
     * после отправки xp_UpdateInformPlat платёжка, 1.
     * Отправитель Access (Dflt) идёт в «Ответить» (Reply-To), письмо уходит с адреса сервера (LegacyMail).
     */
    public function send(int $id, int $tip, int $txtId, string $email, string $dflt, string $txt, int $plat): array
    {
        if (trim($dflt) === '') return ['ok' => false, 'msg' => "Не указан адрес 'От'"];
        $sch = (new VhSchetService())->getOne($id);
        if (!$sch) return ['ok' => false, 'msg' => 'Счёт не найден'];
        $nsch = F::value($sch['NSch'] ?? ''); $dta = F::value($sch['DtaSch'] ?? '', 'Short Date'); $org = F::value($sch['TP'] ?? '');
        $st = str_replace(['[Sch]', '[Dta]', '[Org]'], [$nsch, $dta, $org], $txt);
        $subj = '';
        if ($tip === 1) {
            $p = $this->platRow($id, $plat);
            if (!$p) return ['ok' => false, 'msg' => 'Не найдена платёжка'];
            $st = str_replace(['[Sm]', '[PP]', '[DtaPP]'],
                [F::value($p['Sm'] ?? ''), F::value($p['Nm'] ?? ''), F::value($p['Dta'] ?? '', 'Short Date')], $st);
            $subj = 'Оплата по счёту ' . $nsch . ' от ' . $dta;
        }
        $res = LegacyMail::send(trim($email), $subj, $st, trim($dflt));
        if (!str_starts_with($res, LegacyMail::OK)) return ['ok' => false, 'msg' => $res];
        if ($tip === 0) {
            $this->exec('dbo.xp_UpdateLogMailOplSch', [$id, $txtId])->execute();
            return ['ok' => true, 'row' => true];
        }
        $this->exec('dbo.xp_UpdateInformPlat', [$plat, 1])->execute();
        return ['ok' => true];
    }

    // ---------------------------------------------------------------- frmSchPost

    /** lstMalis.RecordSource = exec dbo.xp_SchElGetMailsRS ID */
    public function mails(int $id): array
    {
        return $this->canon($this->exec('dbo.xp_SchElGetMailsRS', [$id])->queryAll(),
            ['IsAnswered', 'ID', 'MType', 'Prim', 'Email', 'Who', 'DT', 'ans', 'Whoans', 'DTans', 'WhoAddans', 'Fle']);
    }

    /**
     * subfrmSchPost.UpdateMail: xp_SchElUpdateMail ID, RGM, Prim(255), email(100), ans(1024), Whoans(100), isanswered, fle(255).
     * RGM: 0 примечание, 1 email, 2 ответ, 3 кто ответил, 4 «получен ответ», 5 дата ответа (yyyymmdd во Whoans, как в Access), 6 файл.
     */
    public function updMail(int $mail, int $rgm, string $val, bool $isAns = false): array
    {
        if ($mail <= 0 || $rgm < 0 || $rgm > 6) return ['ok' => false, 'msg' => 'Неизвестное действие'];
        if ($rgm === 0 && mb_strlen($val) > 255) return ['ok' => false, 'msg' => 'Превышена длина примечания (255)', 'icon' => 'error'];
        if ($rgm === 1 && $val !== '' && !preg_match('/^[\w\-.]+@[\w\-.]+\.\w+$/u', $val)) return ['ok' => false, 'msg' => 'Введен ошибочный почтовый адрес', 'icon' => 'error'];
        if ($rgm === 2 && mb_strlen($val) > 1024) return ['ok' => false, 'msg' => 'Превышена длина ответа (1024)', 'icon' => 'error'];
        if ($rgm === 5 && $val !== '') $val = $this->ymd($val);
        $v = [0 => '', 1 => '', 2 => '', 3 => '', 6 => ''];
        if (isset($v[$rgm])) $v[$rgm] = $val;
        $this->exec('dbo.xp_SchElUpdateMail', [$mail, $rgm, mb_substr($v[0], 0, 255), mb_substr($v[1], 0, 100), mb_substr($v[2], 0, 1024),
            mb_substr($rgm === 5 ? $val : $v[3], 0, 100), $isAns ? 1 : 0, mb_substr($v[6], 0, 255)])->execute();
        return ['ok' => true, 'row' => true];
    }

    /** frmFndIsp4Nakl режим 2 «Перенос даты счета»: FillNmes — CommonStartSP1(5, "dbo.xp_DelTmpScDate", 0, , 1, , False), exec dbo.xp_GetPosScet ID. */
    public function posScet(int $id): array
    {
        LegacySp::sp1(5, 'dbo.xp_DelTmpScDate', 0);
        return $this->canon($this->exec('dbo.xp_GetPosScet', [$id])->queryAll(), ['IsSel', 'Num', 'ID', 'FullName', 'IspolnitID', 'Kol']);
    }

    /** All2One_MouseDown: UpdAll — exec dbo.xp_UpdAllPos4Date ScID, rgm (1 — выбрать все, 0 — снять). */
    public function posAll(int $id, int $on): array
    {
        $this->exec('dbo.xp_UpdAllPos4Date', [$id, $on ? 1 : 0])->execute();
        return ['ok' => true];
    }

    /**
     * ChoiceRab_Click (режим 2): для каждой выбранной позиции FixDates4Mail = xp_GetFirstGuiltyRes4LitvinovNew 'yyyymmdd', PosID, @res OUT;
     * 3 — «По позиции … не удалось изменить дату поставки». Позиции карточки потом перечитываются.
     */
    public function moveDate(array $pos, string $date): array
    {
        $ymd = $this->ymd($date);
        if ($ymd === '') return ['ok' => false, 'msg' => 'Не указана дата'];
        $bad = [];
        foreach ($pos as $p) {
            $r = (int)Yii::$app->db->createCommand('SET NOCOUNT ON; DECLARE @r int; EXEC dbo.xp_GetFirstGuiltyRes4LitvinovNew ?, ?, @r OUTPUT; SELECT @r',
                [1 => $ymd, 2 => (int)$p['id']])->queryScalar();
            if ($r === 3) $bad[] = 'По позиции ' . $p['name'] . ' не удалось изменить дату поставки';
        }
        return ['ok' => true, 'msg' => implode("\n", $bad)];
    }

    /** frmfndDocs режим 3 «Письма»: FillTipDocInvBook(ID) = exec dbo.xp_InvBookTipDocView 'ID%', 3. */
    public function docs(int $id): array
    {
        return $this->canon($this->exec('dbo.xp_InvBookTipDocView', [$id . '%', 3])->queryAll(), ['NumTip', 'Tip', 'HaveIdx', 'vkMark', 'otkMark']);
    }

    /** UNC-путь вложения письма, как в Access: \\Tsrv\Docs\Счета на оплату\<Replace_symbols(фирма)>\Переписка\<имя файла>. */
    private function attachUnc(string $org, string $name): string
    {
        $U = \app\components\UncPath::class;
        if (trim($org) === '') throw new \yii\web\BadRequestHttpException('Не удалось определить фирму для папки переписки');
        return '\\\\Tsrv\\Docs\\Счета на оплату\\' . $U::safeName(trim($org)) . '\\Переписка\\' . mb_substr($U::safeName(basename(str_replace('\\', '/', $name))), 0, 120);
    }

    /** subfrmSchPost.Fle_DblClick (файла нет): файл копируется на \\Tsrv, путь пишется в письмо (UpdateMail RGM 6). */
    public function mailAttach(int $mail, ?\yii\web\UploadedFile $file): array
    {
        if (!$file || $file->hasError) return ['ok' => false, 'msg' => 'Файл не получен' . ($file ? ' (ошибка ' . $file->error . ')' : '')];
        $unc = $this->attachUnc(LegacySp::sp2($mail, 'dbo.xp_SchMailGetOrg', '', 255), $file->name);
        if ($err = \app\components\UncPath::save($file->tempName, $unc)) return ['ok' => false, 'msg' => $err, 'icon' => 'error'];
        return $this->updMail($mail, 6, $unc);
    }

    /** subfrmSchPost.Fle_KeyDown (Delete, после «Удаляем?»): Kill файла и UpdateMail RGM 6 ''. */
    public function mailDetach(int $id, int $mail): array
    {
        foreach ($this->mails($id) as $r) {
            if ((int)$r['ID'] !== $mail) continue;
            $unc = trim((string)($r['Fle'] ?? ''));
            if ($unc === '') return ['ok' => true];
            if (!\app\components\UncPath::mapped($unc)) return ['ok' => false, 'msg' => 'Папка TSRV не подключена на сервере (настройка uncMap)'];
            $p = \app\components\UncPath::local($unc);
            if ($p === null) return ['ok' => false, 'msg' => "Не найден файл\n$unc"];
            if (!@unlink($p)) return ['ok' => false, 'msg' => "Не удалось удалить файл\n$unc"];
            return $this->updMail($mail, 6, '');
        }
        return ['ok' => false, 'msg' => 'Письмо не найдено'];
    }

    /** Fle_DblClick (файл есть): FollowHyperlink — здесь файл письма отдаётся браузеру. Путь берётся из списка писем счёта. */
    public function mailFile(int $id, int $mail): array
    {
        foreach ($this->mails($id) as $r) {
            if ((int)$r['ID'] !== $mail) continue;
            $unc = trim((string)($r['Fle'] ?? ''));
            $local = $unc === '' ? null : \app\components\UncPath::local($unc);
            return $local ? ['ok' => true, 'path' => $local, 'unc' => $unc] : ['ok' => false, 'msg' => 'Не найден файл' . ($unc !== '' ? "\n$unc" : '')];
        }
        return ['ok' => false, 'msg' => 'Письмо не найдено'];
    }

    /** DelMail: CommonStartSP1(ID письма, "dbo.xp_SchElDelMailNew", 0): 1 — удалено, 2 — нет прав. */
    /** frmSchElMailAdd: типы писем (xp_SclElMailTypes: колонка 0 — код, 1 — название) и адрес по умолчанию (xp_SchElGetMail ID, @eml OUT). */
    public function mailAddPrep(int $id): array
    {
        $types = array_map(fn($r) => ['id' => (int)$r[0], 'text' => (string)($r[1] ?? '')],
            $this->exec('dbo.xp_SclElMailTypes', [])->queryAll(\PDO::FETCH_NUM));
        return ['types' => $types, 'email' => trim(LegacySp::sp2($id, 'dbo.xp_SchElGetMail', '', 100))];
    }

    /** AddBut_Click → AddMail: проверки и xp_SchElAddMail SchID, MType, Prim(255), email(100), Ans(1024), WhoAns(255), Fle(255). */
    public function addMail(int $id, int $type, string $prim, string $email, string $ans, string $who, ?\yii\web\UploadedFile $file = null): array
    {
        if ($type <= 0) return ['ok' => false, 'msg' => 'Не выбран тип письма', 'icon' => 'error'];
        if (mb_strlen($prim) > 255) return ['ok' => false, 'msg' => 'Превышено максимальное кол-во символов в примечании (255)', 'icon' => 'error'];
        if (trim($email) === '') return ['ok' => false, 'msg' => 'Не выбран email получателя', 'icon' => 'error'];
        $five = $type === 5;                                               // MType_AfterUpdate: «Ответ», «Кто ответил», «Файл» — только у типа 5
        $nul = fn($v) => $v === '' ? null : $v;
        $fle = null;
        if ($five && $file && !$file->hasError) {                          // Fle_DblClick: копия в \\Tsrv\Docs\Счета на оплату\<фирма>\Переписка
            $fle = $this->attachUnc(LegacySp::sp2($id, 'dbo.xp_SchMailGetOrg1', '', 255), $file->name);
            if ($err = \app\components\UncPath::save($file->tempName, $fle)) return ['ok' => false, 'msg' => $err, 'icon' => 'error'];
        }
        $this->exec('dbo.xp_SchElAddMail', [$id, $type, $nul($prim), mb_substr(trim($email), 0, 100),
            $five ? $nul(mb_substr($ans, 0, 1024)) : null, $five ? $nul(mb_substr($who, 0, 255)) : null, $fle])->execute();
        return ['ok' => true, 'row' => true];
    }

    public function delMail(int $mailId): array
    {
        return match ((int)LegacySp::sp1($mailId, 'dbo.xp_SchElDelMailNew', 0)) {
            1 => ['ok' => true, 'row' => true],
            2 => ['ok' => false, 'msg' => 'У Вас нет прав на данное действие'],
            default => ['ok' => false, 'msg' => 'Не удалось удалить запись'],
        };
    }

    // ---------------------------------------------------------------- frmPlatFrom1C

    /** RecordSource = exec dbo.xp_SubFrmPlatFrom1C ID (для деталей — ID,1). */
    public function plats(int $id, bool $det): array
    {
        return $this->canon($this->exec('dbo.xp_SubFrmPlatFrom1C', $det ? [$id, 1] : [$id])->queryAll(), ['ID', 'Stat', 'Nm', 'Dta', 'Sm', 'Inf']);
    }

    /** Имена полей как в подформе Access (там регистр не важен: !nm и Nm — одно поле). */
    private function canon(array $rows, array $names): array
    {
        $map = array_combine(array_map('strtolower', $names), $names);
        return array_map(function ($r) use ($map) {
            $o = [];
            foreach ($r as $k => $v) $o[$map[strtolower($k)] ?? $k] = $v;
            return $o;
        }, $rows);
    }

    private function platRow(int $id, int $plat): ?array
    {
        foreach ($this->plats($id, false) as $r) if ((int)($r['ID'] ?? 0) === $plat) return $r;
        return null;
    }

    /**
     * SendInfo_Click (до окна письма): Stat 0 — можно; 2 — спросить «уже была отправлена…»; иначе нельзя.
     * Адрес — CommonStartSP2(ID счёта, "dbo.xp_GetEMailZac", "", , , 255).
     */
    public function platMailPrep(int $id, int $plat): array
    {
        $p = $this->platRow($id, $plat);
        if (!$p) return ['ok' => false, 'msg' => ''];
        $stat = (int)($p['Stat'] ?? -1);
        if ($stat !== 0 && $stat !== 2) return ['ok' => false, 'msg' => 'Из указанного состояния нельзя отправить письмо'];
        $email = trim(LegacySp::sp2($id, 'dbo.xp_GetEMailZac', '', 255));
        if ($email === '') return ['ok' => false, 'msg' => 'Не удалось определить электронный адрес', 'icon' => 'warn', 'stat' => $stat];
        return ['ok' => true, 'email' => $email, 'stat' => $stat];
    }

    /** DontSend_Click: Stat 0, 2, 3 → xp_UpdateInformPlat платёжка, 3. */
    public function platDontSend(int $id, int $plat): array
    {
        $p = $this->platRow($id, $plat);
        if (!$p) return ['ok' => false, 'msg' => ''];
        if (!in_array((int)($p['Stat'] ?? -1), [0, 2, 3], true)) return ['ok' => false, 'msg' => 'Нельзя установить такое состояние'];
        $this->exec('dbo.xp_UpdateInformPlat', [$plat, 3])->execute();
        return ['ok' => true];
    }

    // ---------------------------------------------------------------- frmGetTov

    /** GetTov.RecordSource = exec dbo.xp_GetTakeMe4ExcelNew 0|1 (0 «Забрать», 1 «Доставлено»). */
    public function tov(int $flt): array
    {
        return $this->canon($this->exec('dbo.xp_GetTakeMe4ExcelNew', [$flt === 1 ? 1 : 0])->queryAll(),
            ['Org', 'NumScet', 'DtaScet', 'Pok', 'DtaRes', 'AdrF', 'WriteAdr', 'shipped', 'Idsc', 'MainPlaceTxt', 'UchetID']);
    }

    /**
     * SubfrmGetTov: флажки «Писать адрес» (WriteAdr_AfterUpdate: xp_UpdateWriteAdr UchetID) и «Доставлено»
     * (shipped_MouseDown: xp_UpdateShipped Idsc). Процедуры получают только код и сами меняют флажок; список перечитывается.
     */
    public function tovFlag(string $flag, string $key): array
    {
        $proc = $flag === 'WriteAdr' ? 'dbo.xp_UpdateWriteAdr' : 'dbo.xp_UpdateShipped';
        if (trim($key) === '') return ['ok' => false, 'msg' => ''];
        return LegacySp::sp5(trim($key), $proc, 0, 12) > 0 ? ['ok' => true] : ['ok' => false, 'msg' => 'Не удалось изменить состояние флажка'];
    }

    /** ToXl_Click: xp_GetTakeMe4Excel2 в транзакции → [имена полей, строки]. */
    public function tovXl(): array
    {
        $db = Yii::$app->db;
        $tr = $db->beginTransaction();
        try {
            $rows = $db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_GetTakeMe4Excel2')->queryAll();
            $tr->commit();
        } catch (\Throwable $e) {
            $tr->rollBack();
            throw $e;
        }
        return [$rows ? array_keys($rows[0]) : [], $rows];
    }

    /** Excel как в ToXl_Click: шапка — имена полей, ширины A10 B20 C авто D10 E12 F10 G30 (перенос) H12, рамки A1:I, книжная, 1 × 2 страницы. */
    public function tovXlsx(array $cols, array $rows): string
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->fromArray($cols, null, 'A1');
        $r = 2;
        foreach ($rows as $row) {
            $c = 1;
            foreach ($row as $v) {
                $cell = $sh->getCell([$c, $r]);
                if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?/', $v) && ($d = date_create(substr($v, 0, 19)))) {
                    $cell->setValue(\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($d));
                    $sh->getStyle($cell->getCoordinate())->getNumberFormat()->setFormatCode('dd.mm.yyyy');
                } elseif (is_numeric($v) && !preg_match('/^0\d/', (string)$v)) {
                    $cell->setValue(+$v);
                } else {
                    $cell->setValueExplicit((string)$v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $c++;
            }
            $r++;
        }
        foreach (['A' => 10, 'B' => 20, 'D' => 10, 'E' => 12, 'F' => 10, 'G' => 30, 'H' => 12] as $col => $w) {
            $sh->getColumnDimension($col)->setWidth($w);
        }
        $sh->getColumnDimension('C')->setAutoSize(true);
        $sh->getRowDimension(1)->setRowHeight(28);
        $last = count($rows) + 1;
        $sh->getStyle('A1:' . $sh->getHighestColumn() . '1')->getAlignment()
            ->setHorizontal('center')->setVertical('bottom')->setWrapText(true);
        $sh->getStyle('G1:G' . $last)->getAlignment()->setVertical('top')->setWrapText(true);
        $sh->getStyle('A1:I' . $last)->getBorders()->getAllBorders()->setBorderStyle('thin');
        $ps = $sh->getPageSetup();
        $ps->setOrientation('portrait')->setFitToWidth(1)->setFitToHeight(2);
        $ps->setFitToPage(true);
        $sh->getPageMargins()->setLeft(30 / 72)->setRight(30 / 72)->setTop(30 / 72)->setBottom(30 / 72);   // 30 пунктов
        $f = tempnam(sys_get_temp_dir(), 'tov');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($f);
        $data = file_get_contents($f);
        @unlink($f);
        return $data;
    }

    // ---------------------------------------------------------------- frmActsAndReps

    /** lst.RowSource = exec dbo.xp_GetRepsAndActs TP, 0|1 (TP — колонка «TP» строки счёта). */
    public function acts(string $tp, int $tip): array
    {
        return $this->exec('dbo.xp_GetRepsAndActs', [$tp, $tip === 1 ? 1 : 0])->queryAll(\PDO::FETCH_NUM);
    }

    /**
     * OpenRep_Click: путь к файлу акта (GetNameAktF6 = xp_GetData4AktDefGetFileF6_3, 255) или отчёта о входном контроле
     * (GetNameFileInControl: GetAdr(19) + «Ф5-1-2004 » / «Ф5-3-2015 » / «Ф5 » + «Отчет о входном контроле» + номер + «.xls»).
     */
    public function actFile(int $nakt, int $tip): array
    {
        if ($nakt <= 0) return ['ok' => false, 'msg' => ''];
        if ($tip === 0) {
            $unc = LegacySp::sp2($nakt, 'dbo.xp_GetData4AktDefGetFileF6_3', '', 255);
        } else {
            $st = $nakt < 683 ? 'Ф5-1-2004 ' : ($nakt > 4995 ? 'Ф5 ' : 'Ф5-3-2015 ');
            $unc = LegacyMail::adr(19) . $st . 'Отчет о входном контроле' . $nakt . '.xls';
        }
        $local = \app\components\UncPath::local($unc);
        return $local ? ['ok' => true, 'path' => $local, 'unc' => $unc] : ['ok' => false, 'msg' => 'Не найден файл', 'unc' => $unc];
    }

    // ---------------------------------------------------------------- frmShowLogSklad (DtaPost)

    /** lstVars = xp_frmMassInputSchDtaLogVars (0 — код, 1 — название, 2 — заголовок окна); lstH = xp_frmMassInputSchDtaLog AllID, вариант. */
    public function dtaLog(int $allId, int $var): array
    {
        $vars = array_map(fn($r) => ['id' => (int)$r[0], 'text' => (string)($r[1] ?? ''), 'cap' => (string)($r[2] ?? '')],
            $this->exec('dbo.xp_frmMassInputSchDtaLogVars', [])->queryAll(\PDO::FETCH_NUM));
        return ['vars' => $vars, 'rows' => $this->exec('dbo.xp_frmMassInputSchDtaLog', [$allId, $var])->queryAll()];
    }

    // ----------------------------------------------------------------

    private function ymd(?string $d): string
    {
        return $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? str_replace('-', '', $d) : '';
    }

    private function exec(string $proc, array $values)
    {
        $marks = $values ? ' ' . implode(', ', array_fill(0, count($values), '?')) : '';
        $bind = [];
        foreach (array_values($values) as $i => $v) $bind[$i + 1] = $v;
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc$marks", $bind);
    }
}
