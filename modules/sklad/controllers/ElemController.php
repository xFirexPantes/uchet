<?php
namespace app\modules\sklad\controllers;

use app\controllers\BaseController;
use app\modules\sklad\services\ElemService;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Карточка элемента (форма Access Fobnovlen1).
 *
 * Открывается двойным щелчком по строке списков: «Статистика» (frmMinimum.OpenKartElem),
 * счёт на элементы и другие экраны — так же, как DoCmd.OpenForm "Fobnovlen1" в Access.
 *
 * Отдельного права в Access у карточки нет: право проверяет тот экран, откуда её открыли
 * (frmSt → form:frmSt). Поэтому здесь пускаем любого вошедшего, а редактирование полей
 * в дальнейшем будет закрыто своими правами (EditEl, TaskNaborNakl, ChgOptPrice и т.д.).
 *
 * @doc    elem/view
 * @legacy Fobnovlen1: Form_Open, Form_Current, Form_Close
 */
class ElemController extends BaseController
{
    /**
     * @doc    elem/view
     * @legacy Fobnovlen1: Form_Open (OpenArgs → Код; Код < 0 → РГМ), xp_Fobnovlen1Shelf
     */
    public function actionView(int $id = 0)
    {
        if ($id === 0) {
            throw new NotFoundHttpException('Не указан код элемента');
        }
        $svc  = new ElemService();
        $data = $svc->card($id);
        if ($data['row'] === null) {
            throw new NotFoundHttpException('Элемент ' . abs($id) . ' не найден');
        }

        $this->layout = '@app/views/layouts/card';   // отдельное окно, как acDialog в Access

        return $this->render('view', [
            'id'   => $data['id'],
            'rgm'  => $data['rgm'],
            'rec'  => $data['row'],
            'tabs' => ElemService::TABS,
        ]);
    }

    /**
     * Строки подчинённого списка карточки (вкладки «История», «Договоры», «Наименования», «Отслеживание»).
     *
     * В Access все подформы заполнялись при открытии карточки (Form_Open). Здесь они грузятся
     * по щелчку на вкладке: у больших элементов история бывает в тысячи строк, и читать её
     * вместе с карточкой незачем.
     *
     * @legacy Fobnovlen1: SetHistoryRowS (xp_HistoryElem), SetRS4DogsElem, SetRS4SkladNaim, SetRS4TrackingAnalogs
     */
    public function actionSub(int $id = 0, string $tab = 'history')
    {
        if ($id === 0) {
            throw new NotFoundHttpException('Не указан код элемента');
        }
        if (!isset(ElemService::TABS[$tab])) {
            throw new NotFoundHttpException('Нет такого списка: ' . $tab);
        }

        $svc  = new ElemService();
        $data = $svc->card($id);
        if ($data['row'] === null) {
            throw new NotFoundHttpException('Элемент ' . abs($id) . ' не найден');
        }

        $res  = $svc->tab($tab, $data['id'], $data['row']);
        $cols = $svc->columns($tab, $res['rows']);

        Yii::$app->response->format = Response::FORMAT_JSON;
        return [
            'html'  => $this->renderPartial('_tab', [
                'tab'   => $tab,
                'rows'  => $res['rows'],
                'cols'  => $cols,
                'error' => $res['error'],
            ]),
            'count' => count($res['rows']),
            'error' => $res['error'],
        ];
    }
}
