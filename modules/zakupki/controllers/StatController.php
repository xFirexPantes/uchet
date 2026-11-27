<?php
namespace app\modules\zakupki\controllers;

use app\controllers\BaseController;
use app\modules\zakupki\models\StatFilter;
use app\modules\zakupki\services\InvoiceColumns;
use app\modules\zakupki\services\StatColumns;
use app\modules\zakupki\services\StatContext;
use app\modules\zakupki\services\StatService;
use Yii;

/**
 * Статистика (форма Access frmSt): список элементов с фильтрами.
 *
 * Экран открывается сразу современным видом — раскладку 1:1 с Access не делаем,
 * координаты формы для этого не нужны.
 *
 * @doc    stat/index
 * @legacy frmSt: Form_Load, fnRepaintFormStat, SetRS4Statistic; frmMinimum: Form_Current, FillStat, Ord*_AfterUpdate
 */
class StatController extends BaseController
{
    public function behaviors()
    {
        $b = parent::behaviors();
        // Право на форму frmSt, как GetPermission("frmSt") в Access
        $b['access']['rules'] = [['allow' => true, 'roles' => ['form:frmSt']]];
        return $b;
    }

    /**
     * Список статистики: вид, поиск, фирмы, WhatFirm, менеджер, даты, сырьё, «Заказ», ФЗ-275, состояние.
     *
     * Набор видимых фильтров зависит от вида — правила fnRepaintFormStat применяет
     * StatFilter::applyViewRules(), поэтому в ссылке может прийти любой набор параметров.
     *
     * @doc    stat/index
     * @legacy frmSt: ПолеСоСписком24_AfterUpdate (fnRepaintFormStat), fltZ1_AfterUpdate, FZ275_AfterUpdate,
     *         fltStatProblEl_AfterUpdate, cboflt_AfterUpdate; frmMinimum: FindElem_Change (FillStat)
     */
    public function actionIndex()
    {
        $res = $this->load();

        return $this->render('index', [
            'filter' => $res['filter'],
            'vids'   => $res['vids'],
            'firms'  => $res['firms'],
            'managers' => $res['managers'],
            'statuses' => $res['statuses'],
            'fz275'  => $res['fz275'],
            'rows'   => $res['rows'],
            'error'  => $res['error'],
            'note'   => $res['note'],
            'footer' => $res['footer'],
            // Признаки ExactDate/DatePost по коду — раскраска «Наименование» (frmMinimum)
            'flags'  => $res['flags'],
            // Подсказки по колонкам: Descr.Caption на GotFocus подформы frmMinimum
            'hints'  => StatService::hints((string)$res['filter']->vid, StatContext::needPaintDolg()),
        ]);
    }

    /**
     * Тело списка для автообновления по таймеру (frmMinimum.Form_Timer).
     *
     * Возвращает только строки таблицы: фильтры на странице не трогаются, поэтому
     * недописанный текст в полях не сбрасывается. Фильтры берутся из тех же GET-параметров.
     *
     * @legacy frmMinimum: Form_Timer (перезапрос с возвратом к текущей записи по «Код»)
     */
    public function actionRows()
    {
        $res = $this->load(false);
        $cols = StatService::columns($res['rows'], (string)$res['filter']->vid);

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return [
            'html'  => $this->renderPartial('_rows', [
                'rows'  => $res['rows'],
                'cols'  => $cols,
                'bnd'   => $res['footer']['bnd'],
                'vid'   => (string)$res['filter']->vid,
                'paint' => StatContext::needPaintDolg(),
                'flags' => $res['flags'],
                'empty' => $res['error'] ? 'Список не загружен' : 'Нет записей, удовлетворяющих условию',
            ]),
            'count' => count($res['rows']),
            'error' => $res['error'],
            'note'  => $res['note'],
        ];
    }

    /**
     * Резервы текущего элемента — подформа SubMinimFilter формы frmSt.
     *
     * Грузятся по щелчку по строке списка, как перезапрос подформы на Form_Current.
     *
     * @legacy frmSt: подформа SubMinimFilter (источник exec xp_SubMinimumFilter @txtID, @Rgm)
     */
    public function actionReserves(int $id, string $vid = '1')
    {
        $res = (new StatService())->reserves($id, $vid);

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return [
            'html'  => $this->renderPartial('_reserves', [
                'rows'  => $res['rows'],
                'cols'  => StatColumns::reserveColumns($res['rows'], $vid),
                'error' => $res['error'],
            ]),
            'count' => count($res['rows']),
            'error' => $res['error'],
        ];
    }

    /**
     * Предложения поставщиков текущего элемента — подформа SubFrmInvoices (Invoices).
     *
     * Грузятся по щелчку по строке списка, как перезапрос подформы на Form_Current.
     * В frmSt подформа только для просмотра, поэтому параметры всегда нулевые.
     *
     * @legacy frmSt: подформа Invoices → exec dbo.xp_GetInvoices1 @SkladID, @Numtbl, @IDRes, @Tpe, @DtaTxt
     */
    public function actionInvoices(int $id, int $numtbl = 0, int $idres = 0, int $tpe = 0, string $dta = '')
    {
        $res = (new StatService())->invoices($id, $numtbl, $idres, $tpe, $dta);

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return [
            'html'  => $this->renderPartial('_invoices', [
                'rows'  => $res['rows'],
                'cols'  => InvoiceColumns::columns($res['rows']),
                'error' => $res['error'],
            ]),
            'count' => count($res['rows']),
            'error' => $res['error'],
        ];
    }

    /**
     * Пересчёт таргета по резерву текущего элемента — кнопка TrgRecalc формы frmSt.
     *
     * Access: если резервов нет — «Не выбран резерв»; если IDRes <= 0 — «Не удается найти
     * резерв»; иначе спрашивает номер сводной таблицы и подтверждение, затем UpdTrgRec.
     *
     * @legacy frmSt: TrgRecalc_Click → UpdTrgRec → dbo.xp_UpdTrgRec(IDres, numtbl)
     */
    public function actionTrgRecalc()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('Нужен POST.');
        }
        $post   = Yii::$app->request->post();
        $idRes  = (int)($post['idres'] ?? 0);
        $numtbl = (int)($post['numtbl'] ?? 0);

        if ($numtbl <= 0) {
            return ['ok' => false, 'error' => 'Укажите номер сводной таблицы.'];
        }

        return (new StatService())->updTrgRec($idRes, $numtbl);
    }

    /**
     * Аналоги текущего элемента — подформа аналогов «Статистики» (subFrmStAnalog).
     *
     * Грузятся по щелчку по строке списка, как Form_Current подформы в Access.
     *
     * @legacy frmMinimum: SubFrmStAnalog.Form_Current → exec xp_frmStRS1Analog(Me![Код])
     */
    public function actionAnalogs(int $id)
    {
        $res = (new StatService())->analogs($id);

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return [
            'html'  => $this->renderPartial('_analogs', [
                'rows'  => $res['rows'],
                'cols'  => StatService::analogColumns($res['rows']),
                'error' => $res['error'],
            ]),
            'count' => count($res['rows']),
            'error' => $res['error'],
        ];
    }

    /**
     * Список состояний проблемы — ветка «25» формы frmfndStatus.
     *
     * Нужен двойному щелчку по «Код»: в Access он открывает frmfndStatus с OpenArgs 25,
     * но только при праве ProblEl (GetPermission("ProblEl", False, 1)).
     *
     * @legacy frmMinimum: txtID_DblClick; frmfndStatus: FillList (Case "25")
     */
    public function actionStatuses()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        if (!$this->canProblEl()) {
            return ['items' => [], 'error' => 'У вас нет прав на изменение состояния проблемы.'];
        }

        return ['items' => (new StatService())->problemStatuses(), 'error' => null];
    }

    /**
     * Действия двойного щелчка по строке списка: примечание, комментарий производства, состояние проблемы.
     *
     * @legacy frmMinimum: Поле18_DblClick (xp_SkladUpdPrim), SostProblEl_DblClick (xp_SkladTrackingUpdComm),
     *         txtID_DblClick (frmfndStatus 25 → xp_StatWorkWithProblemUpdSost)
     */
    public function actionAct()
    {
        if (!Yii::$app->request->isPost) {
            throw new \yii\web\BadRequestHttpException('Нужен POST.');
        }
        $post = Yii::$app->request->post();
        $id   = (int)($post['id'] ?? 0);
        $act  = (string)($post['act'] ?? '');
        $svc  = new StatService();

        $res = match ($act) {
            'Prim' => $id > 0
                ? $svc->setPrim($id, (string)($post['text'] ?? ''))
                : ['ok' => false, 'error' => 'Не указан элемент.'],
            'Comm' => $id > 0
                ? $svc->setTrackingComm($id, (string)($post['text'] ?? ''))
                : ['ok' => false, 'error' => 'Не указан элемент.'],
            'Sost' => $this->canProblEl()
                ? $svc->setProblemStatus($id, (int)($post['sost'] ?? 0))
                : ['ok' => false, 'error' => 'У вас нет прав на это действие.'],
            // RequestSch_MouseDown: UpdateNotRecFlg(txtID, "ReqSch") — переключение флага
            'ReqSch' => $id > 0
                ? $svc->toggleRequestSch($id)
                : ['ok' => false, 'error' => 'Не указан элемент.'],
            // NotRec_MouseDown: UpdateNotRecFlg(txtID) — Rgm по умолчанию "NotRec"
            'NotRec' => $id > 0
                ? $svc->toggleNotRec($id)
                : ['ok' => false, 'error' => 'Не указан элемент.'],
            default => ['ok' => false, 'error' => 'Неизвестное действие.'],
        };

        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $res;
    }

    /** GetPermission("ProblEl", False, 1) — право менять состояние проблемы. */
    private function canProblEl(): bool
    {
        return Yii::$app->user->can('ProblEl', ['withMe' => false]);
    }

    /**
     * Общая загрузка: фильтр из GET, футер, список видов, фирм, менеджеров, строк.
     *
     * @param bool $withVids false — не читать список видов (при автообновлении он не нужен)
     * @return array{filter:StatFilter,footer:array,vids:array,firms:array,managers:array,statuses:array,fz275:array,rows:array,error:?string,note:?string}
     */
    private function load(bool $withVids = true): array
    {
        $filter = new StatFilter();
        $filter->load(Yii::$app->request->get());

        // Применяем правила по «Виду» (как fnRepaintFormStat в Access): fltZ1, FZ275, даты.
        $filter->applyViewRules();

        // Менеджер влияет на Otv и раскраску долгов — читаем из фильтра, а не из StatContext::$manager
        StatContext::setManager((string)$filter->manager, (string)$filter->vid);

        $svc   = new StatService();
        // Футер правит границу цены под валюту вида, поэтому считается до list()
        $footer = $svc->footer($filter);
        $vids  = $withVids ? $svc->vids() : [];
        $firms = $withVids ? $svc->firms() : [];
        $managers = $withVids ? StatContext::managerList((string)$filter->vid) : [];
        $statuses = $withVids ? $svc->problemStatuses(1) : [];
        $fz275 = $withVids ? $svc->fz275List() : [];
        $rows  = [];
        $error = null;
        $note  = null;

        if ($filter->validate()) {
            $res   = $svc->list($filter);
            $rows  = $svc->sort($res['rows'], $filter->sort !== '' ? $filter->sort : null, $filter->dir);
            $error = $res['error'];
            $note  = $res['note'];
        } else {
            // На странице ошибки полей показываются из модели; для автообновления — текстом
            $error = implode(' ', $filter->getFirstErrors());
        }

        // Раскраска «Наименование»: ExactDate/DatePost считаются по коду строки (VBA frmMinimum)
        $flags = $rows ? $svc->dateFlags(array_column($rows, 'Код')) : [];

        return compact('filter', 'footer', 'vids', 'firms', 'managers', 'statuses', 'fz275', 'rows', 'error', 'note', 'flags');
    }
}
