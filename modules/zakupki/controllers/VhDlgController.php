<?php
namespace app\modules\zakupki\controllers;

use app\controllers\BaseController;
use app\modules\zakupki\services\VhDlgService;
use app\modules\zakupki\services\VhSchetService;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Окна-диалоги «Входящих счетов» (frmStat) и карточки счёта. Все открываются в iframe (access-dialog.js, макет dialog).
 * Право — то же, что у самих экранов: форма frmStat.
 */
class VhDlgController extends BaseController
{
    public $layout = 'dialog';

    public function behaviors()
    {
        $b = parent::behaviors();
        $b['access']['rules'] = [['allow' => true, 'roles' => ['form:frmStat']]];
        return $b;
    }

    private function sch(int $id): array
    {
        $row = (new VhSchetService())->getOne($id);
        if (!$row) throw new NotFoundHttpException('Счёт не найден');
        return $row;
    }

    private static function date(?string $d): string
    {
        return $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
    }

    /**
     * Фильтр по счетам (frmfltSch, Rgm 0): фирма из списка xp_GetOrg4AllScet за даты, даты, «Интервал».
     * ✓ — вернуть экрану фирму и даты (ApproveFlt), ⌫ — снять фильтр (DelFlt), ✕ — закрыть.
     * @doc    vh-dlg/flt
     * @legacy frmfltSch: Form_Open, SetRowS4cboFirma, cboFirma_DblClick (frmfndHolding 1), GetInterval_Click, ApproveFlt_Click, DelFlt_Click, ClsFrm_Click
     */
    public function actionFlt(string $firma = '', string $dateS = '', string $datePo = '', string $holding = '')
    {
        $dateS = self::date($dateS); $datePo = self::date($datePo); $holding = mb_substr($holding, 0, 100);
        return $this->render('flt', [
            'firma' => mb_substr($firma, 0, 100), 'dateS' => $dateS, 'datePo' => $datePo, 'holding' => $holding,
            'firms' => (new VhDlgService())->firms($dateS, $datePo, $holding),
        ]);
    }

    /**
     * История изменений счетов (frmChgPredShcetaHistory).
     * rgm 1 — из «Входящих счетов» (кнопка «Ист»): по счетам списка (заранее act ChgList), фильтры в шапке.
     * rgm 0 — из карточки (история цен позиции): Args «0|1,AllID,ShcID», галка «Все изменения по счёту».
     * @doc    vh-dlg/chg-hist
     * @legacy frmChgPredShcetaHistory: Form_Open, SetRS4PredShcetaHistory, AllPosSch_AfterUpdate, flt*_DblClick, flt*_KeyDown,
     *         fltSch_AfterUpdate, GetInt_Click, GetIntDSch_Click, bntChgReason_Click; frmMassInputSch: btnShowLogPrice_Click
     */
    public function actionChgHist(int $rgm = 1)
    {
        $q = Yii::$app->request;
        $rgm = $rgm === 0 ? 0 : 1;
        $a = ['rgm' => $rgm, 'all' => (int)$q->get('all') === 1 ? 1 : 0, 'allId' => (int)$q->get('allId'), 'shcId' => (int)$q->get('shcId')];
        if ($rgm === 1) {
            foreach (['bdta', 'edta', 'bds', 'eds'] as $k) $a[$k] = self::date($q->get($k));
            if (!$q->get('bdta') && !$q->get('edta') && !$q->get('f')) {          // Form_Open: месяц назад … сегодня
                $a['bdta'] = date('Y-m-d', strtotime('-1 month')); $a['edta'] = date('Y-m-d');
            }
            $a['zac'] = (int)$q->get('zac');
            foreach (['zacTxt', 'sch', 'reas', 'reasTxt', 'wh', 'whTxt'] as $k) $a[$k] = mb_substr((string)$q->get($k, ''), 0, 255);
            // в процедуру идут только списки чисел через запятую (KodNullEl = UchetID & ",")
            foreach (['reas', 'wh'] as $k) if (!preg_match('/^(\d+,)*$/', $a[$k])) $a[$k] = '';
            $a['sch'] = mb_substr($a['sch'], 0, 50);
        }
        return $this->render('chg-hist', ['a' => $a] + (new VhDlgService())->chgHistory($a));
    }

    /**
     * Письмо по счёту (frmGetTxt4Mail): шаблоны текста, E-mail, «От», «Сформировать письмо».
     * tip 0 — из «Входящих счетов» (MakeEmail), tip 1 — об оплате из «Платежей по счёту» (plat — платёжка).
     * @doc    vh-dlg/mail-txt
     * @legacy frmGetTxt4Mail: Form_Open, lstTxt_AfterUpdate, AddTxt_Click, EditTxt_Click, SndMl_Click (0, 1), ClsFrm_Click
     */
    public function actionMailTxt(int $id, int $tip = 0, string $email = '', int $plat = 0)
    {
        $this->sch($id);
        $tip = $tip === 1 ? 1 : 0;
        return $this->render('mail-txt', [
            'id' => $id, 'tip' => $tip, 'plat' => $plat, 'email' => mb_substr($email, 0, 255),
            'list' => (new VhDlgService())->txtList($tip, ''),
        ]);
    }

    /**
     * Переписка по счёту (frmSchPost): письма xp_SchElGetMailsRS, удаление.
     * @doc    vh-dlg/sch-post
     * @legacy frmSchPost: Form_Open, DelMailBut_Click, DelMail, ExitBut_Click; frmStat: MailFormBut_Click; frmMassInputSch: MailFormBtn_Click
     */
    public function actionSchPost(int $id)
    {
        $this->sch($id);
        return $this->render('sch-post', ['id' => $id, 'rows' => (new VhDlgService())->mails($id)]);
    }

    /**
     * Создание переписки (frmSchElMailAdd): тип письма, примечание, email (по умолчанию xp_SchElGetMail), для типа 5 — ответ и кто ответил.
     * @doc    vh-dlg/mail-add
     * @legacy frmSchElMailAdd: Form_Open, GetMailAdr, MType_AfterUpdate, AddBut_Click, AddMail, ExitBut_Click; frmSchPost: AddMail_Click
     */
    public function actionMailAdd(int $id)
    {
        $this->sch($id);
        return $this->render('mail-add', ['id' => $id] + (new VhDlgService())->mailAddPrep($id));
    }

    /**
     * История дат поставки позиции счёта (frmShowLogSklad, режим DtaPost): вариант из списка внизу.
     * @doc    vh-dlg/show-log
     * @legacy frmShowLogSklad: Form_Load (DtaPost), lstVars_AfterUpdate; frmMassInputSch: btnShowLogDtaPost_Click
     */
    public function actionShowLog(int $allId, int $var = 1)
    {
        return $this->render('show-log', ['allId' => $allId, 'var' => $var, 'changed' => Yii::$app->request->get('v') !== null]
            + (new VhDlgService())->dtaLog($allId, $var));
    }

    /**
     * Файл письма (двойной щелчок по «Файл:» в переписке). Нет файла — JSON «Не найден файл».
     * @doc    vh-dlg/sch-post
     * @legacy subfrmSchPost: Fle_DblClick (FollowHyperlink)
     */
    /**
     * «Перенос поставки» в карточке счёта: frmFndIsp4Nakl, режим 2 — выбрать позиции, дата, xp_GetFirstGuiltyRes4LitvinovNew.
     * @doc    vh-dlg/move-date
     * @legacy frmMassInputSch: MoveDate_Click; frmFndIsp4Nakl: Form_Open (Case 2), All2One_MouseDown, ChoiceRab_Click
     */
    public function actionMoveDate(int $id)
    {
        return $this->render('move-date', ['id' => $id, 'rows' => (new VhDlgService())->posScet($id)]);
    }

    /**
     * «Сроки поставки»: выбор поставщика (frmChoice4InControl 101), файл Excel грузится из окна (act SrokLoad).
     * @doc    vh-dlg/srok
     * @legacy frmStat: KompelSrokBut_Click; frmChoice4InControl: Form_Open, Группа5_AfterUpdate
     */
    public function actionSrok()
    {
        return $this->render('srok');
    }

    /**
     * «Письма» во «Входящих счетах»: frmfndDocs, режим 3 (xp_InvBookTipDocView 'ID%', 3).
     * @doc    vh-dlg/docs
     * @legacy frmStat: btnLtrs1C_Click; frmfndDocs: Form_Open (Case 3), FillTipDocInvBook
     */
    public function actionDocs(int $id)
    {
        $this->sch($id);
        return $this->render('docs', ['id' => $id, 'rows' => (new VhDlgService())->docs($id)]);
    }

    public function actionMailFile(int $id, int $mail)
    {
        $this->sch($id);
        $r = (new VhDlgService())->mailFile($id, $mail);
        if (!$r['ok']) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $r;
        }
        return Yii::$app->response->sendFile($r['path'], basename(str_replace('\\', '/', $r['unc'])), ['inline' => false]);
    }

    /**
     * Файл акта (tip 0) или отчёта о входном контроле (tip 1) — «Открыть» в frmActsAndReps. Нет файла — JSON «Не найден файл».
     * @doc    vh-dlg/acts
     * @legacy frmActsAndReps: OpenRep_Click; GetNameAktF6, GetNameFileInControl
     */
    public function actionActFile(int $nakt, int $tip = 0)
    {
        $r = (new VhDlgService())->actFile($nakt, $tip === 1 ? 1 : 0);
        if (!$r['ok']) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $r;
        }
        return Yii::$app->response->sendFile($r['path'], basename(str_replace('\\', '/', $r['unc'])), ['inline' => false]);
    }

    /**
     * Платежи по счёту (frmPlatFrom1C): платёжки из 1С, «отправить e-mail» (окно письма tip 1), «не надо отправлять».
     * det = 1 — счёт на детали (кнопок нет, xp_SubFrmPlatFrom1C ID,1).
     * @doc    vh-dlg/plat
     * @legacy frmPlatFrom1C: Form_Open, SendInfo_Click, DontSend_Click, Form_Close; frmStat: Plat_Click
     */
    public function actionPlat(int $id, int $det = 0)
    {
        $row = $this->sch($id);
        return $this->render('plat', [
            'id' => $id, 'det' => $det === 1, 'nsch' => (string)($row['NSch'] ?? ''),
            'rows' => (new VhDlgService())->plats($id, $det === 1),
        ]);
    }

    /**
     * Забрать товар (frmGetTov): список xp_GetTakeMe4ExcelNew по фильтру, выгрузка в Excel.
     * @doc    vh-dlg/get-tov
     * @legacy frmGetTov: Form_Open, fltSt_AfterUpdate, ToXl_Click, ClsFrm_Click; frmStat: butExel_Click
     */
    public function actionGetTov(int $flt = 0)
    {
        $flt = $flt === 1 ? 1 : 0;
        return $this->render('get-tov', ['flt' => $flt, 'rows' => (new VhDlgService())->tov($flt)]);
    }

    /**
     * Файл Excel для «Забрать товар» (ToXl_Click): xp_GetTakeMe4Excel2. Пусто — JSON с сообщением.
     * @doc    vh-dlg/get-tov
     */
    public function actionTovXl()
    {
        $svc = new VhDlgService();
        try {
            [$cols, $rows] = $svc->tovXl();
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), __METHOD__);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['ok' => false, 'msg' => 'Не удалось получить данные о счетах'];
        }
        if (!$rows) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['ok' => false, 'msg' => 'Нет данных для выгрузки'];
        }
        return Yii::$app->response->sendContentAsFile($svc->tovXlsx($cols, $rows), 'Забрать_товар_' . date('Ymd_Hi') . '.xlsx', [
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Акты (tip 0) и отчёты (tip 1) по счёту (frmActsAndReps): в xp_GetRepsAndActs идёт ShcID.
     * Открытие файла ждёт путей к \\TSRV.
     * @doc    vh-dlg/acts
     * @legacy frmActsAndReps: Form_Open, ExitBut_Click; frmStat: ActBut_Click, RepBut_Click
     */
    public function actionActs(int $id, int $tip = 0)
    {
        $this->sch($id);                                   // счёт должен существовать
        $tip = $tip === 1 ? 1 : 0;
        return $this->render('acts', ['tip' => $tip, 'rows' => (new VhDlgService())->acts($id, $tip)]);
    }

    /**
     * Действия окон (JSON). id — счёт (PredShcID). Если в ответе row = true — строка списка счетов перечитывается (row_html).
     * ChgList (ids[]), ChgReason (hid, reason, comm), Email, TxtList (tip, dflt), TxtGet (txt), TxtAdd (tip, text), TxtUpd (txt, text),
     * MailSend (tip, txt, email, dflt, text, plat), MailDel (mail), PlatPrep (plat), PlatDont (plat).
     * @doc    vh-dlg/mail-txt
     * @legacy frmStat: GetIDs4SelSch, MakeEmail_Click; frmChgPredShcetaHistory: bntChgReason_Click; frmGetTxt4Mail; frmSchPost: DelMail; frmPlatFrom1C
     */
    public function actionAct(int $id = 0)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r = Yii::$app->request;
        $svc = new VhDlgService();
        $p = fn($k) => (string)$r->post($k, '');
        $res = match ($p('act')) {
            'ChgList'   => $svc->chgList((array)$r->post('ids', [])),
            'ChgReason' => $svc->chgReason((int)$r->post('hid'), (int)$r->post('reason'), $p('comm')),
            'Email'     => $svc->emailBySch($id),
            'TxtList'   => ['ok' => true, 'list' => $svc->txtList((int)$r->post('tip') === 1 ? 1 : 0, $p('dflt'))],
            'TxtGet'    => ['ok' => true, 'text' => $svc->txt((int)$r->post('txt'))],
            'TxtAdd'    => $svc->addTxt((int)$r->post('tip') === 1 ? 1 : 0, $p('text')),
            'TxtUpd'    => $svc->updTxt((int)$r->post('txt'), $p('text')),
            'MailSend'  => $svc->send($id, (int)$r->post('tip') === 1 ? 1 : 0, (int)$r->post('txt'), $p('email'), $p('dflt'), $p('text'), (int)$r->post('plat')),
            'MailDel'   => $svc->delMail((int)$r->post('mail')),
            'MailUpd'   => $svc->updMail((int)$r->post('mail'), (int)$r->post('rgm'), $p('val'), (bool)$r->post('isAns')),
            'MailAdd'   => $svc->addMail($id, (int)$r->post('type'), $p('prim'), $p('email'), $p('ans'), $p('who'), UploadedFile::getInstanceByName('file')),
            'PosAll'    => $svc->posAll((int)$r->post('sch'), (int)$r->post('on')),
            'MoveDate'  => $svc->moveDate(array_map(fn($x) => ['id' => (int)($x['id'] ?? 0), 'name' => (string)($x['name'] ?? '')],
                               (array)json_decode((string)$r->post('pos', '[]'), true)), $p('date')),
            'SrokLoad'  => (new \app\modules\zakupki\services\SrokService())->load((int)$r->post('tip'), UploadedFile::getInstanceByName('file')),
            'MailAttach' => $svc->mailAttach((int)$r->post('mail'), UploadedFile::getInstanceByName('file')),
            'MailDetach' => $svc->mailDetach($id, (int)$r->post('mail')),
            'TovFlag'   => $svc->tovFlag($p('flag') === 'WriteAdr' ? 'WriteAdr' : 'shipped', $p('key')),
            'PlatPrep'  => $svc->platMailPrep($id, (int)$r->post('plat')),
            'PlatDont'  => $svc->platDontSend($id, (int)$r->post('plat')),
            'Row'       => ['ok' => true, 'row' => true],                   // строку списка перечитать (Form_Close «Платежей», IsRfh)
            default     => ['ok' => false, 'msg' => 'Неизвестное действие'],
        };
        if (!empty($res['row']) && $id > 0 && ($one = (new VhSchetService())->getOne($id))) {
            $res['row_html'] = $this->renderPartial('/vh-schet/_list-modern', ['rows' => [$one]]);
        }
        if (isset($res['list']) || $p('act') === 'TxtAdd' || $p('act') === 'TxtUpd') {
            $res['list'] ??= $svc->txtList((int)$r->post('tip') === 1 ? 1 : 0, $p('dflt'));
        }
        return $res;
    }
}
