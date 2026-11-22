<?php
namespace app\modules\zakupki\controllers;

use app\controllers\BaseController;
use app\modules\zakupki\models\VhSchetFilter;
use app\modules\zakupki\services\OplService;
use app\modules\zakupki\services\PartSumsService;
use app\modules\zakupki\services\VhSchetService;
use Yii;
use yii\web\Response;

/**
 * Входящие счета (форма Access frmStat).
 */
class VhSchetController extends BaseController
{
    public function behaviors()
    {
        $b = parent::behaviors();
        // Открыть экран можно только с правом на форму frmStat, как GetPermission("frmStat") в Access.
        // Гостя AccessControl сам отправит на форму входа.
        $b['access']['rules'] = [['allow' => true, 'roles' => ['form:frmStat']]];
        return $b;
    }

    /**
     * Список входящих счетов с фильтрами и панелью выбранного счёта.
     *
     * @doc    vh-schet/index
     * @legacy frmStat: Form_Open, butF_Click, FillListSch, xListSch_ItemClick, xListSch_ColumnClick
     * @tz     -
     */
    public function actionIndex()
    {
        $filter = new VhSchetFilter();
        $filter->load(Yii::$app->request->get());
        $rows = $total = null;

        if ($filter->validate()) {
            $svc   = new VhSchetService();
            $rows  = $svc->getList($filter);
            $total = $filter->id > 0 ? null : $svc->getTotal($filter);
        }
        return $this->render('index', ['filter' => $filter, 'rows' => $rows ?? [], 'total' => $total]);
    }

    /**
     * JSON для панели выбранного счёта: оплаты, к оплате, платежи из 1С.
     *
     * @doc    vh-schet/index
     * @legacy frmStat: SetRowSOplPrc
     */
    public function actionPanel(int $id, int $typeSch = 0)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return (new VhSchetService())->getPanel($id, $typeSch === 1 ? 1 : 0);
    }

    /**
     * Сохранения с экрана. act: Opl* — «+» оплат (AddOpl), ToDo* — «+» к оплате (AddOplToDo) и окно frmGetPrcSch 2/4; ClsByPlat, DelOplPrc (opl), DelOplToDo (todo, typeSch), WhoPick (value 0|1), PthDel (удалить путь к счёту), Prim / Comm (text) — из окна frmShowPrim;
     * KompelLoad (file) — загрузка «Сроков КОМПЭЛ», KompelReserv (row — ID строки tmptblKOMPELSrokNew, res — ID резерва).
     * @doc    vh-schet/index
     * @legacy frmStat: AddOpl_Click, fnAddOplPrc, MailAboutIt, MailAboutBigSum, AddOplToDo_Click; fnSkladDogsAllowScet; frmGetPrcSch: FillVl4Cmd2Opl, Dta2Opl_DblClick, Prc_Click, Rb_Click, Cncl_Click;
     *         frmStat: WhoPick_AfterUpdate, PthSch_KeyDown, txtComm_DblClick, ClsByPlat_Click, DelOplPrc_Click, DelOplToDo_Click, ChangeDataInList, SpokKompelbtn_Click, SelResKompel, SetReserv; frmShowPrim: txtUpdate (44, 83)
     */
    public function actionAct(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r = Yii::$app->request;
        $svc = new VhSchetService();
        $opl = new OplService();
        $todo = (int)$r->post('todo');
        $tip = $id < 0 ? 1 : 0;                                   // GrpTypeSch: счёт на детали — режим 4, Tip 1
        $res = match ((string)$r->post('act')) {
            'OplPrep'  => $opl->prep($id),
            'OplKorr'  => $opl->korr($id),
            'OplAdd'   => $opl->add($id, (float)$r->post('prc'), (int)$r->post('ovr') === 1),
            'OplMail'  => $opl->mail($id, (float)$r->post('prc'), (int)$r->post('it') === 1),
            'ToDoPrep' => $opl->todoPrep($id),
            'ToDoNew'  => $opl->todoNew($id, $tip),
            'ToDoChk'  => $opl->todoChk($todo, $id, (string)$r->post('ymd')),
            'ToDoSave' => $opl->todoSave($todo, $id, $tip, (int)$r->post('unit') === 1 ? 1 : 0, [
                'Prc' => $r->post('Prc'), 'Prim' => $r->post('Prim'), 'Dta2Opl' => $r->post('Dta2Opl'), 'SrokOb' => $r->post('SrokOb'),
            ]),
            'ToDoDel'  => $opl->todoDel($todo, $id),
            'ClsByPlat' => $svc->clsByPlat($id),
            'DelOplPrc' => $svc->delOpl($id, (int)$r->post('opl')),
            'DelOplToDo'=> $svc->delToDo($id, (int)$r->post('typeSch') === 1 ? 1 : 0, (int)$r->post('todo')),
            'WhoPick' => $svc->whoPick($id, (int)$r->post('value') === 1 ? 1 : 0),
            'PthDel'  => $svc->pathDel($id),
            'PthSet'  => $svc->pathSet($id, \yii\web\UploadedFile::getInstanceByName('file')),
            'Prim'    => $svc->prim($id, (string)$r->post('text', '')),
            'Comm'    => $svc->comm($id, (string)$r->post('text', '')),
            'KompelLoad'   => (new \app\modules\zakupki\services\SrokService())->kompelLoad(\yii\web\UploadedFile::getInstanceByName('file')),
            'KompelReserv' => (new \app\modules\zakupki\services\SrokService())->kompelReserv((int)$r->post('row'), (int)$r->post('res')),
            default   => ['ok' => false, 'msg' => 'Неизвестное действие'],
        };
        // ChangeDataInList: строка счёта заново (xp_GetSchByID) — та же разметка, что у списка
        if (!empty($res['row']) && ($one = $svc->getOne($id))) {
            $res['row_html'] = $this->renderPartial('_list', ['rows' => [$one]]);
        }
        return $res;
    }

    /**
     * Окно ввода текста (frmShowPrim) для примечания (mode 44) или комментария (mode 83) счёта.
     * @doc    vh-schet/prim
     * @legacy frmShowPrim: Form_Open (44, 83); frmStat: EditPrim_Click, txtComm_DblClick
     */
    public function actionPrim(int $id, int $mode = 44)
    {
        $row = (new VhSchetService())->getOne($id);
        if (!$row) throw new \yii\web\NotFoundHttpException('Счёт не найден');
        $mode = $mode === 83 ? 83 : 44;
        $this->layout = 'dialog';
        $f = $mode === 44 ? 'Prim' : 'Comm';
        // текст из xp_GetSchByID; если процедура такого поля не отдаёт — тот, что сейчас на экране (ListSubItems(1) / txtComm)
        $text = array_key_exists($f, $row) ? (string)$row[$f] : (string)Yii::$app->request->get('t', '');
        return $this->render('prim', ['id' => $id, 'mode' => $mode, 'text' => $text]);
    }

    /**
     * Окно frmGetPrcSch: режим 0 — «Оплатить: N процентов / рублей» для «+» оплат (значение возвращается экрану);
     * режимы 2, 4 — строка «к оплате» (заготовка todo уже создана act ToDoNew), сохраняется act ToDoSave.
     * @doc    vh-schet/get-prc
     * @legacy frmGetPrcSch: Form_Open, MakeWindowPrcSch, AllowOverflow_AfterUpdate, Prc_Click, Rb_Click, Cncl_Click, Dta2Opl_DblClick, SrokOb_DblClick
     */
    public function actionGetPrc(int $id, int $mode = 0, int $todo = 0)
    {
        $one = (new VhSchetService())->getOne($id);
        if (!$one) throw new \yii\web\NotFoundHttpException('Счёт не найден');
        $mode = in_array($mode, [0, 2, 4], true) ? $mode : 0;
        $row = null;
        if ($mode !== 0) {
            $row = (new OplService())->todoRow($todo, $id);
            if (!$row) throw new \yii\web\NotFoundHttpException('Строка «к оплате» не найдена');
        }
        $this->layout = 'dialog';
        return $this->render('get-prc', [
            'id' => $id, 'mode' => $mode, 'todo' => $todo, 'row' => $row, 'unit' => (string)($one['InUEtxt'] ?? ''),
        ]);
    }

    /**
     * «Список элементов, требующих внимания» (frmFndPR, режим 11): строки xp_WhoBuySch перед первой оплатой счёта.
     * ✓ (ChoiceRab) — продолжить (frmStat!Prc = -1), ✕ — отказаться от оплаты.
     * @doc    vh-schet/who-buy
     * @legacy frmFndPR: Form_Open (11), ChoiceRab_Click (11), CloseFrm_Click; frmStat: AddOpl_Click
     */
    public function actionWhoBuy(int $id)
    {
        if (!(new VhSchetService())->getOne($id)) throw new \yii\web\NotFoundHttpException('Счёт не найден');
        $this->layout = 'dialog';
        return $this->render('who-buy', ['rows' => (new OplService())->whoRows($id)]);
    }

    /**
     * Файл счёта по пути PthSch (двойной щелчок, путь указан). Нет файла или папки — JSON с сообщением.
     * @doc    vh-schet/index
     * @legacy frmStat: PthSch_DblClick (WShell.Run)
     */
    public function actionPthFile(int $id)
    {
        $r = (new VhSchetService())->pathFile($id);
        if (!$r['ok']) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $r;
        }
        return Yii::$app->response->sendFile($r['path'], basename(str_replace('\\', '/', $r['unc'])), ['inline' => false]);
    }

    /**
     * «Долги»: покупатель выбран в окне frmFndFirm 9 (UchetID) → exec dbo.xp_GetPartSums 'UchetID', 0 → файл Excel.
     * Пусто — JSON с сообщением «Нет данных для выгрузки».
     * @doc    vh-schet/index
     * @legacy frmStat: GetPartSums_Click
     */
    public function actionPartSums(string $uchet)
    {
        $uchet = mb_substr(trim($uchet), 0, 50);
        $svc = new PartSumsService();
        [$cols, $rows, $types] = $uchet === '' ? [[], [], []] : $svc->data($uchet);
        if (!$rows) {
            Yii::$app->response->format = Response::FORMAT_JSON;
            return ['ok' => false, 'msg' => 'Нет данных для выгрузки'];
        }
        return Yii::$app->response->sendContentAsFile($svc->xlsx($cols, $rows, $types), 'Долги_' . date('Ymd_Hi') . '.xlsx', [
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
