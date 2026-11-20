<?php
namespace app\modules\zakupki\controllers;

use app\controllers\BaseController;
use app\modules\zakupki\services\BufService;
use app\modules\zakupki\services\GtdService;
use app\modules\zakupki\services\NaklService;
use Yii;
use yii\web\Response;

/**
 * Накладная (frmMassInput). Открывается диалогом из карточки счёта: двойной щелчок по списку накладных (lstNakl_DblClick),
 * кнопка «Посмотреть» (OpnFrm_Click) или «Добавить» (AddNakl_Click — новая накладная сразу в режиме правки).
 */
class NaklController extends BaseController
{
    public function behaviors()
    {
        $b = parent::behaviors();
        $b['access']['rules'] = [['allow' => true, 'roles' => ['form:frmStat']]];   // как у карточки счёта
        return $b;
    }

    /**
     * id — накладная (ShcID), sch — счёт, из которого открыли (< 0 — счёт на детали).
     * edit = 1 — режим правки (OpenArgs 7, красный фон), new = 1 — только что создана (Rgm 2: внизу позиции счёта).
     * @doc    nakl/view
     * @legacy frmMassInput: Form_Load (Rgm 1 и 2, WhoOpen 0 и 2), SetRS4PosShc, SetRS4WhereBy, FillListSchetPos
     */
    public function actionView(int $id, int $sch, int $edit = 0, int $new = 0)
    {
        $d = (new NaklService())->view($id, $sch, (bool)$edit, (bool)$new);
        $this->layout = 'dialog';
        return $this->render('view', ['id' => $id, 'd' => $d]);
    }

    /**
     * Действия окна накладной. Ответ: ok, msg, ask {key, text} (клиент повторяет запрос с answers[key]), reload, close, edit, view.
     * @doc    nakl/view
     * @legacy frmMassInput: НомерНакладной/ДатаНакладной/Dst AfterUpdate, butSaveAs_Click, DelRec_Click, Кнопка58_Click,
     *         ToSklad_Click, butExit_Click, Form_Close, butDob_Click, LoadGTD_Click; frmAddEl2Nakl: Кнопка20_Click;
     *         SubFrmqrytblAll: AfterUpdate полей (Количество, ТочноеНазвание, GTD, OrigCountry, KolByNakl, PriceWNDS, SumNDS, Vsego)
     */
    public function actionAct(int $id, int $sch)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r   = Yii::$app->request;
        $ans = array_map(fn($v) => $v === '1', (array)$r->post('answers', []));
        $svc = new NaklService();
        return match ((string)$r->post('act')) {
            'Save'      => $svc->saveHead($id, $sch, (string)$r->post('field'), $r->post('value')),
            'butSaveAs' => $svc->saveAs($id, $sch),
            'DelRec'    => $svc->delRow($id, $sch, (int)$r->post('pos'), $ans),
            'Кнопка58'  => $svc->startEdit($id, $sch),
            'ToSklad'   => $svc->toSklad($id, $sch, $ans),
            'butExit'   => $svc->close($id, $sch, $r->post('edit') === '1', $r->post('chg') === '1', $ans),
            // окно закрыли не кнопкой выхода (крестик диалога, закрытие вкладки): снять отметку «правит пользователь»
            'Release'   => $svc->release($id, $sch),
            'Sost'      => $svc->switchSost((int)$r->post('proc')),
            'butDob'    => $svc->addList($id, $sch),
            'AddSave'   => $svc->addSave($id, $sch, (array)$r->post('items', [])),
            'AddCancel' => $svc->addCancel(),
            'RowSave'   => $svc->rowSave($id, $sch, (int)$r->post('pos'), (string)$r->post('field'), $r->post('value')),
            'Warranty'  => $svc->warranty($id, $sch, (int)$r->post('pos'), (string)$r->post('dta')),
            'Ostatok'   => $svc->ostatok($id, $sch, (int)$r->post('pos'), (string)$r->post('value')),
            'LoadGTD'   => (new GtdService())->check($svc->own($id, $sch)),
            'Countries' => ['ok' => true, 'list' => $svc->countries()],
            'Marks'     => ['ok' => true, 'list' => $svc->marks((int)$r->post('sklad'))],
            default     => ['ok' => false, 'msg' => 'Неизвестное действие'],
        };
    }

    /**
     * Окно «Добавить позиции в накладную» (frmAddEl2Nakl), диалог поверх накладной. Список готовит действие butDob.
     * @doc    nakl/add-el
     * @legacy frmAddEl2Nakl: Form_Load (xp_frmAddEl2NaklRS WhoOpen), Kolvo_AfterUpdate, Кнопка20_Click, Кнопка21_Click
     */
    public function actionAddEl(int $id, int $sch)
    {
        $svc = new NaklService();
        $svc->own($id, $sch);                                          // проверка: накладная этого счёта
        $this->layout = 'dialog';
        return $this->render('add-el', ['id' => $id, 'sch' => $sch, 'rows' => $svc->addRows($sch)]);
    }

    /**
     * «Где купил» по строке накладной (SubFrmqrytblAll: Form_Current → SetRS4WhereBy).
     * @doc    nakl/view
     * @legacy SubFrmqrytblAll: Form_Current
     */
    public function actionWhereBy(int $sklad)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        return (new NaklService())->whereBy($sklad);
    }
    /**
     * Загрузка ГТД из файла поставщика (frmLoadKompel), диалог поверх накладной. Открывает кнопка «ГТД» (act LoadGTD).
     * @doc    nakl/gtd
     * @legacy frmLoadKompel: Form_Open (Case Else), SetRS4PosSchKompel, SetRS4PosNaklKompel; subfrmLoadKompel: Form_Open
     */
    public function actionGtd(int $id, int $sch)
    {
        $svc = new GtdService();
        $rec = (new NaklService())->own($id, $sch);
        $chk = $svc->check($rec);
        if (!isset($chk['gtd'])) throw new \yii\web\BadRequestHttpException($chk['msg'] ?? 'Для этого поставщика загрузки ГТД нет');
        $this->layout = 'dialog';
        return $this->render('gtd', ['id' => $id, 'sch' => $sch, 'd' => $svc->view($rec)]);
    }

    /**
     * Действия окна ГТД. act: Load (файл в поле file), Assign (sch = AllID, str = IDStr, copy = yes|no), Unassign, Put (answers[more]).
     * После каждого действия — обе таблицы заново (StndOnLne / SetRS4PosNaklKompel) и что выделить.
     * @doc    nakl/gtd
     * @legacy frmLoadKompel: LoadKompelXl_Click, GetGTDKompel/Acrol/Vital/Alef/Elsio/Alkon, AssignElKompel_Click, DelAssign_Click, Put2Nakl_Click
     */
    public function actionGtdAct(int $id, int $sch)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $r   = Yii::$app->request;
        $svc = new GtdService();
        $ids = $svc->ids((new NaklService())->own($id, $sch));
        $ans = array_map(fn($v) => $v === '1', (array)$r->post('answers', []));
        $res = match ((string)$r->post('act')) {
            'Load'     => (function () use ($svc, $ids) {
                $f = \yii\web\UploadedFile::getInstanceByName('file');
                if (!$f || $f->error !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => 'Файл не получен'];
                return $svc->load($ids, $f->tempName, $f->name);
            })(),
            'Assign'   => $svc->assign((int)$r->post('pos'), (int)$r->post('str'), $r->post('copy')),
            'Unassign' => $svc->unassign((int)$r->post('pos'), (int)$r->post('str')),
            'Put'      => $svc->put($ids['nakl'], $ans),
            'Lists'    => ['ok' => true],
            default    => ['ok' => false, 'msg' => 'Неизвестное действие'],
        };
        if (empty($res['ask']) && empty($res['close'])) {
            $d = ['cardRec' => (new \app\modules\zakupki\services\SchetService())->getElem($ids['sch'])['rec'] ?? []];
            $g = require Yii::getAlias('@app/modules/zakupki/views/nakl/_gtd-cols.php');
            $res['sch_html'] = $this->renderPartial('@app/views/access/_grid', $g['sch'] + ['rows' => $svc->schRows($ids['sch'])]);
            $res['file_html'] = $this->renderPartial('@app/views/access/_grid', $g['file'] + ['rows' => $svc->fileRows()]);
        }
        return $res;
    }

    /**
     * Составляющие детали на буферный склад (ProdPlanSnd2Buf), диалог поверх накладной счёта на детали.
     * Открывается после «На склад», если xp_ProdPlanIsReadySostDetal > 0 (IsReadySostDetal, OpenArgs 0).
     * @doc    nakl/buf
     * @legacy ProdPlanSnd2Buf: Form_Open; subProdPlanSnd2Buf: Form_Open
     */
    public function actionBuf(int $id, int $sch, int $fake = 0)
    {
        (new NaklService())->own($id, $sch);
        $this->layout = 'dialog';
        return $this->render('buf', ['id' => $id, 'sch' => $sch, 'fake' => $fake === 1 ? 1 : 0, 'rows' => (new BufService())->rows()]);
    }

    /**
     * Действия окна ProdPlanSnd2Buf. act: Chk (str = IDStr), Kol / Sm (str, value), Send. После действия — строки заново.
     * @doc    nakl/buf
     * @legacy subProdPlanSnd2Buf: Chk_MouseDown, MnlKol_DblClick, MnlSm_DblClick; ProdPlanSnd2Buf: btnSend2Buf_Click
     */
    public function actionBufAct(int $id, int $sch, int $fake = 0)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        (new NaklService())->own($id, $sch);
        $r = Yii::$app->request;
        $svc = new BufService();
        $str = (string)$r->post('str', '');
        $res = match ((string)$r->post('act')) {
            'Chk'  => $svc->chk($str),
            'Kol'  => $svc->kol($str, (int)round((float)$r->post('value'))),
            'Sm'   => $svc->sm($str, round((float)$r->post('value'), 4)),
            'Send' => $svc->send($fake === 1 ? 1 : 0),
            default => ['ok' => false, 'msg' => 'Неизвестное действие'],
        };
        $g = require Yii::getAlias('@app/modules/zakupki/views/nakl/_buf-cols.php');
        $res['html'] = $this->renderPartial('@app/views/access/_grid', $g + ['rows' => $svc->rows()]);
        return $res;
    }
}
