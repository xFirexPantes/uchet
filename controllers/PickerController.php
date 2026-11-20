<?php
namespace app\controllers;

use app\components\Picker;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Данные для окон выбора (frmfndFirm и т.п.). Процедуры идут под логином человека,
 * поэтому права на данные проверяет SQL Server, как в Access.
 */
class PickerController extends BaseController
{
    /** Описание режима: заголовок, подпись, колонки. */
    public function actionMode(string $form, int $mode)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $m = Picker::mode($form, $mode) ?? throw new NotFoundHttpException('Нет такого режима');
        unset($m['proc']);
        return $m;
    }

    /** Строки списка по строке поиска (fnd_Change → FillList). */
    public function actionList(string $form, int $mode, string $q = '')
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $m = Picker::mode($form, $mode) ?? throw new NotFoundHttpException('Нет такого режима');
        return Picker::rows($m, mb_substr($q, 0, 100));
    }
}
