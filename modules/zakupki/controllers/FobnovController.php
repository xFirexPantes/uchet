<?php
namespace app\modules\zakupki\controllers;

use app\controllers\BaseController;
use app\modules\zakupki\services\FObnovService;
use app\modules\zakupki\services\SchetDetalService;
use app\modules\zakupki\services\SchetPosElemService;
use app\modules\zakupki\services\SchetSaveService;
use Yii;
use yii\web\Response;

/**
 * Полный поиск компонента fObnov — диалог. id — счёт: > 0 счёт на элементы (＋ карточки), < 0 счёт на детали
 * (двойной щелчок по «Номенклатуре» в позиции). Выбранный элемент окно возвращает вызвавшему (closeAccessDialog value).
 */
class FobnovController extends BaseController
{
    public function behaviors()
    {
        $b = parent::behaviors();
        $b['access']['rules'] = [['allow' => true, 'roles' => ['form:frmStat']]];   // как у карточки счёта
        return $b;
    }

    /**
     * @doc    fobnov/view
     * @legacy fObnov: Form_Open (frmMassInputSch, frmScetaDetalAdd), SwitchInterface(0), FillTree2, ShowMaxItems
     */
    public function actionView(int $id)
    {
        $prin = 0;
        if ($id > 0) {
            $save = new SchetSaveService();
            $prin = $save->ispOf($save->rec($id)['TP'] ?? null);              // lstPrin = TP.Column(0)
        } else {
            (new SchetDetalService())->rec($id);
        }
        $svc = new FObnovService();
        $this->layout = 'dialog';
        return $this->render('view', [
            'id' => $id, 'det' => $id < 0, 'prin' => $prin,
            'prinList' => $id > 0 ? (new SchetPosElemService())->prinList() : [],
            'maxItems' => $svc->maxItems(),
        ]);
    }

    /**
     * Запросы окна. act: Nodes (path[]) — узлы дерева; List (path[], all) — список узла; Find (q, mark) — поиск;
     * Path (sklad) — путь к элементу в дереве; Max (value) — «Максимум строк»; Choose (sklad) — кнопка выбора.
     * @doc    fobnov/view
     * @legacy fObnov: xTree_NodeClick, xTree_Expand, FillList, FillListNew, FindElem_Change, fndMark_AfterUpdate, xTree4KodnullEl,
     *         MaxItems_AfterUpdate, FlgShowAll_AfterUpdate, xList_Click, xList_DblClick, ButEl_Click
     */
    public function actionAct(int $id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $id > 0 ? (new SchetSaveService())->rec($id) : (new SchetDetalService())->rec($id);
        $r   = Yii::$app->request;
        $svc = new FObnovService();
        $s   = (array)$r->post('s', []);
        if ($id < 0) $s['TypeSel'] = 5;                                        // у frmScetaDetalAdd TypeSel = 5 и скрыт
        $path = (array)$r->post('path', []);
        return match ((string)$r->post('act')) {
            'Nodes'  => ['ok' => true, 'nodes' => $svc->children($s, $path)],
            'List'   => ['ok' => true, 'rows' => $svc->list($s, $path, $r->post('all') === '1')],
            'Find'   => ['ok' => true] + $svc->find($s, (string)$r->post('q', ''), (string)$r->post('mark', '')),
            'Path'   => ['ok' => true, 'path' => $svc->path((int)$r->post('sklad'))],
            'Max'    => ['ok' => true, 'value' => $svc->maxItems(max(0, (int)$r->post('value')))],
            'Choose' => $svc->choose($id, (int)$r->post('sklad')),
            default  => ['ok' => false, 'msg' => 'Неизвестное действие'],
        };
    }
}
