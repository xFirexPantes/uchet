<?php
namespace app\modules\zakupki\controllers;

use app\controllers\BaseController;
use app\modules\zakupki\models\VhSchetFilter;
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
}
