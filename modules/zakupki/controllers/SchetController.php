<?php
namespace app\modules\zakupki\controllers;

use app\controllers\BaseController;
use app\modules\zakupki\services\SchetActionService;
use app\modules\zakupki\services\SchetDetalService;
use app\modules\zakupki\services\SchetPosDetalService;
use app\modules\zakupki\services\SchetPosElemService;
use app\modules\zakupki\services\SchetSaveService;
use app\modules\zakupki\services\SchetService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Карточка счёта. Открывается двойным щелчком во «Входящих счетах» (frmStat: xListSch_DblClick):
 * ID > 0 → frmMassInputSch (счёт на элементы), ID < 0 → frmScetaDetal (счёт на детали).
 * Шаг А: только просмотр, кнопки на местах, но неактивны.
 */
class SchetController extends BaseController
{
    public function behaviors()
    {
        $b = parent::behaviors();
        // В Access отдельной проверки на карточку нет: кто видит frmStat, тот её открывает.
        $b['access']['rules'] = [['allow' => true, 'roles' => ['form:frmStat']]];
        $b['verbs'] = ['class' => VerbFilter::class, 'actions' => ['save' => ['POST'], 'firm' => ['POST'], 'act' => ['POST'],
            'pos-detal-save' => ['POST'], 'pos-detal-accept' => ['POST'], 'pos-elem-calc' => ['POST'], 'pos-elem-save' => ['POST']]];
        return $b;
    }

    /**
     * @doc    schet/view
     * @legacy frmMassInputSch: Form_Load, OpenSch, UpdateList, RepaintSch; frmScetaDetal: Form_Load, SetRS4SchDetal, UpdateListDetal
     */
    public function actionView(int $id)
    {
        // ID = 0 в Access значит «новый счёт»: xp_frmMassInputSch1 0 сразу вставляет запись в tblPredShceta.
        // В просмотре такого быть не должно, поэтому не пускаем.
        if ($id === 0) {
            throw new NotFoundHttpException('Новый счёт создаётся из «Статистики» (вторая очередь)');
        }
        $svc = new SchetService();
        $elem = $id > 0;
        $data = $elem ? $svc->getElem($id) : $svc->getDetal($id);
        if ($data === null) {
            throw new NotFoundHttpException("Счёт $id не найден");
        }
        $this->layout = 'card';   // без шапки сайта, как отдельное окно Access
        // Счёт на элементы — современный вид, счёт на детали пока открывается как в Access.
        return $this->render($elem ? 'elem-modern' : 'detal', ['id' => $id, 'd' => $data]);
    }

    /**
     * «Позиция счёта для деталей» (диалог поверх карточки): pos = IDPos, 0 — новая.
     * @doc    schet/pos-detal
     * @legacy frmScetaDetalAdd: Form_Open, FillPosSchDetal; frmScetaDetal: EdtPosSchDet_Click, butDob_Click
     */
    public function actionPosDetal(int $id, int $pos = 0)
    {
        $row = (new SchetPosDetalService())->open($id, $pos);
        $this->layout = 'dialog';
        return $this->render('pos-detal', ['id' => $id, 'idPos' => $pos, 'row' => $row]);
    }

    /**
     * @doc    schet/pos-detal
     * @legacy frmScetaDetalAdd: FullNme_AfterUpdate, Kol_AfterUpdate, Prc_AfterUpdate, Sm/SmNDS/Vsego_AfterUpdate
     */
    public function actionPosDetalSave(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r = Yii::$app->request;
        return (new SchetPosDetalService())->saveField($id, (string)$r->post('field'), $r->post('value'));
    }

    /**
     * Номенклатура позиции выбрана в fObnov (двойной щелчок по «Номенклатуре»).
     * @doc    schet/pos-detal
     * @legacy frmScetaDetalAdd: Nme_DblClick
     */
    public function actionPosDetalNomenkl(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return (new SchetPosDetalService())->nomenkl($id, (int)Yii::$app->request->post('sklad'));
    }

    /**
     * @doc    schet/pos-detal
     * @legacy frmScetaDetalAdd: AccptRec_Click
     */
    public function actionPosDetalAccept(int $id, int $pos)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return (new SchetPosDetalService())->accept($id, $pos);
    }

    /**
     * Позиция счёта на элементы (frmPosInput), диалог. mode: 3 — правка, 7 — по позиции есть накладная,
     * 5 — новая позиция с элементом $sklad (из облегчённого поиска frmFndElem).
     * @doc    schet/pos-elem
     * @legacy frmPosInput: Form_Load (KodNullEl 3, 5, 7); frmMassInputSch: Кнопка15_Click, butDob_Click
     */
    public function actionPosElem(int $id, int $pos = 0, int $mode = 3, int $sklad = 0)
    {
        $d = (new SchetPosElemService())->open($id, $pos, $mode, $sklad);
        $this->layout = 'dialog';
        return $this->render('pos-elem', ['d' => $d]);
    }

    /**
     * Расчёты окна frmPosInput: sums (SetSums4Pos), checkNds, okei, testPrice, testKol.
     * @doc    schet/pos-elem
     * @legacy frmPosInput: SetSums4Pos, CheckNDS, UpdOKEI, UpdOKEI2, TestPrice, TestKol
     */
    public function actionPosElemCalc(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r = Yii::$app->request;
        $v = (array)$r->post('v', []);
        $svc = new SchetPosElemService();
        $sklad = $svc->skladOf($id, (int)($v['allId'] ?? 0), (int)($v['skladId'] ?? 0));
        $num = fn($x) => (float)str_replace(',', '.', (string)$x);
        return match ((string)$r->post('op')) {
            'sums'      => ['set' => $svc->sums(['priceRub' => $num($v['priceRub'] ?? 0), 'kol' => $num($v['kol'] ?? 0),
                                'incNds' => ($v['incNds'] ?? '0') === '1', 'nds' => $num($v['nds'] ?? 0)])],
            'checkNds'  => ['res' => $svc->checkNds($id, $num($r->post('nds')))],
            'okei'      => $svc->okei($id, $sklad, (int)$r->post('okei'), $r->post('okei2') !== null ? (int)$r->post('okei2') : null, (int)$r->post('kol2')),
            'testPrice' => ['text' => $svc->testPrice($sklad, $num($v['priceOpt'] ?? 0))],
            'testKol'   => ['text' => $svc->testKol($sklad, $num($v['kol'] ?? 0))],
            default     => ['ok' => false],
        };
    }

    /**
     * @doc    schet/pos-elem
     * @legacy frmPosInput: butSave_Click
     */
    public function actionPosElemSave(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r = Yii::$app->request;
        $v = (array)$r->post('v', []);
        $num = fn($k) => (float)str_replace(',', '.', (string)($v[$k] ?? 0));
        $bool = fn($k) => in_array((string)($v[$k] ?? '0'), ['1', 'true'], true);
        $clean = [
            'id' => $id, 'allId' => (int)($v['allId'] ?? 0), 'mode' => (int)($v['mode'] ?? 3), 'skladId' => (int)($v['skladId'] ?? 0), 'inUE' => (int)($v['inUE'] ?? 0),
            'kol' => $num('kol'), 'priceRub' => $num('priceRub'), 'priceOpt' => $num('priceOpt'), 'nds' => $num('nds'), 'kurs' => $num('kurs'),
            'incNds' => $bool('incNds'), 'manualFlg' => $bool('manualFlg'), 'manualKol' => (string)($v['manualKol'] ?? ''), 'manualNme' => (string)($v['manualNme'] ?? ''),
            'kol2' => (int)($v['kol2'] ?? 1), 'okei' => (int)($v['okei'] ?? 796), 'okei2' => (int)($v['okei2'] ?? 796), 'upKoef' => (string)($v['upKoef'] ?? '1'),
            'info' => (string)($v['info'] ?? ''), 'nmeCat' => (string)($v['nmeCat'] ?? ''), 'nsh' => (string)($v['nsh'] ?? ''),
            'dtaPost' => (string)($v['dtaPost'] ?? ''), 'firstPost' => (string)($v['firstPost'] ?? ''), 'wasChg' => (int)($v['wasChg'] ?? 0),
        ];
        $ans = array_map(fn($x) => $x === '1', (array)$r->post('answers', []));
        return (new SchetPosElemService())->savePos($clean, $r->post('reason'), $r->post('comm'), $ans);
    }

    /**
     * Облегчённый поиск компонента (frmFndElem, OpenArgs 1) — диалог из ＋ карточки счёта на элементы.
     * Возвращает родителю SkladID выбранного элемента (Forms!frmMassInputSch!txtSkladID).
     * @doc    schet/fnd-elem
     * @legacy frmFndElem: Form_Open (Rgm 1), AcceptElem_Click; frmMassInputSch: butDob_Click
     */
    public function actionFndElem(int $id)
    {
        $svc = new SchetPosElemService();
        $save = new \app\modules\zakupki\services\SchetSaveService();
        $rec = $save->rec($id);
        $this->layout = 'dialog';
        // OldLine = "--Post--" & TP.Column(0): код покупателя (IspID), а в счёте TP — его название
        return $this->render('fnd-elem', ['id' => $id, 'prin' => $save->ispOf($rec['TP'] ?? null), 'prinList' => $svc->prinList()]);
    }

    /**
     * Строки поиска frmFndElem (fndel_AfterUpdate → FndElemByStr).
     * @doc    schet/fnd-elem
     * @legacy frmFndElem: FndElemByStr
     */
    public function actionFndElemList(int $id, string $q = '', int $prin = 0)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return (new SchetPosElemService())->fndElem($id, $q, $prin);
    }

    /**
     * Резервы по выбранной позиции (подформа lstSchRes).
     * @doc    schet/view
     * @legacy SubFrmMassInputSch: Form_Current → frmMassInputSch.SetRS4LstSchRes
     */
    public function actionRes(int $id, int $skladId)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return (new SchetService())->getRes($id, $skladId);
    }

    /**
     * Сохранить одно поле шапки (AfterUpdate → acCmdSaveRecord в Access).
     * @doc    schet/view
     * @legacy frmMassInputSch: НомерСчета_AfterUpdate, ДатаСчета_GotFocus, Sm_AfterUpdate, TP_AfterUpdate, Dst_AfterUpdate, Dta2Opl_DblClick/KeyDown; frmScetaDetal: NumSc_AfterUpdate, DtaSc_GotFocus, IspFrm_AfterUpdate, Sm_AfterUpdate, SumDost_AfterUpdate, Dst_AfterUpdate
     */
    public function actionSave(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r = Yii::$app->request;
        $svc = $id < 0 ? new SchetDetalService() : new SchetSaveService();
        return $svc->saveField($id, (string)$r->post('field'), $r->post('value'), $r->post('withNds', '1') === '1');
    }

    /**
     * Выбрать контрагента из списка фирм.
     * @doc    schet/view
     * @legacy frmMassInputSch: xListINN_DblClick; frmScetaDetal: xListINN_DblClick
     */
    public function actionFirm(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $firmId = (int)Yii::$app->request->post('firmId');
        return $id < 0 ? (new SchetDetalService())->setFirm($id, $firmId) : (new SchetSaveService())->setFirm($id, $firmId);
    }

    /**
     * Поиск фирм по началу названия (поле Fnd).
     * @doc    schet/view
     * @legacy frmMassInputSch: fnd_Change, FillListINN
     */
    public function actionFirms(string $q = '')
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return (new SchetSaveService())->firms($q);
    }

    /**
     * Действия карточки: флажки, «Ставка НДС», закрытие (✕). answers — ответы на вопросы Да/Нет.
     * @doc    schet/view
     * @legacy frmMassInputSch: NotUsed_MouseDown, IsTo1C_MouseDown, TakeMe_MouseDown, flgFZ275_MouseUp, SetNDS2Str_Click, butExit_Click
     * @legacy frmMassInputSch: pth_KeyDown, AdditPth_KeyDown, OpnSch_Click, butSaveAs_Click, DelRec_Click
     * @legacy frmScetaDetal: butDob_Click, butSaveAs_Click, DelRec_Click, NotUsed_MouseDown, IsTo1C_MouseDown, PrcWithNDS_MouseDown, SchWithNDS_MouseDown, MassSetDTPost_Click, MassSetDTDP_Click, pth_KeyDown, AdditPth_KeyDown, TakeMe_MouseDown, butExit_Click
     */
    public function actionAct(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r   = Yii::$app->request;
        $ans = array_map(fn($v) => $v === '1', (array)$r->post('answers', []));
        if ($id < 0) {   // счёт на детали
            $d = new SchetDetalService();
            return match ((string)$r->post('act')) {
                'NotUsed'       => $d->notUsed($id),
                'IsTo1C'        => $d->to1C($id),
                'PrcWithNDS'    => $d->withNds($id, 1),
                'SchWithNDS'    => $d->withNds($id, 0),
                'MassSetDTPost' => $d->massDate($id, 0, $r->post('date'), $ans),
                'MassSetDTDP'   => $d->massDate($id, 1, $r->post('date'), $ans),
                'PthDel'        => $d->pathDel($id, false, $ans),
                'AdditPthDel'   => $d->pathDel($id, true, $ans),
                'TakeMe'        => $d->takeMe($id, $r->post('on') === '1', $r->post('date'), $r->post('time')),
                'butExit'       => $d->close($id, $ans),
                'OpnSch'        => $d->reopen($id),
                'butSaveAs'     => $d->saveAs($id),
                'DelRec'        => $d->delPos($id, (int)$r->post('pos'), $ans),
                'OpnNakl'       => (new \app\modules\zakupki\services\NaklService())->open($id, (int)$r->post('nakl'), $r->post('btn') === '1'),
                'AddNakl'       => (new \app\modules\zakupki\services\NaklService())->create($id, $ans),
                'butDob'        => $d->addPos($id),
                default         => ['ok' => false, 'msg' => 'Это действие для счёта на детали ещё не перенесено'],
            };
        }
        $svc = new SchetActionService();
        return match ((string)$r->post('act')) {
            'NotUsed'   => $svc->notUsed($id),
            'IsTo1C'    => $svc->to1C($id),
            'TakeMe'    => $svc->takeMe($id, $r->post('on') === '1', $r->post('date'), $r->post('time')),
            'flgFZ275'  => $svc->fz275($id, (int)$r->post('num') ?: null),
            'SetNDS2Str'=> $svc->ndsAll($id, $ans),
            'InUEtxt'   => $svc->setUE($id, $r->post('ue')),
            'butExit'   => $svc->close($id, $ans),
            'PthDel'    => $svc->pathDel($id, false, $ans),
            'AdditPthDel' => $svc->pathDel($id, true, $ans),
            'OpnSch'    => $svc->reopen($id),
            'butSaveAs' => $svc->saveAs($id),
            'DelRec'    => $svc->delPos($id, (int)$r->post('pos'), $r->post('reason'), $ans),
            'EdtPos'    => (new SchetPosElemService())->mode($id, (int)$r->post('pos'), $ans),
            'butDob'    => $svc->addPos($id, $ans),
            'OpnNakl'   => (new \app\modules\zakupki\services\NaklService())->open($id, (int)$r->post('nakl'), $r->post('btn') === '1'),
            'AddNakl'   => (new \app\modules\zakupki\services\NaklService())->create($id, $ans),
            'AddPos'    => (new SchetPosElemService())->checkNew($id, (int)$r->post('sklad'), $ans),
            default     => ['ok' => false, 'msg' => 'Неизвестное действие'],
        };
    }
}
