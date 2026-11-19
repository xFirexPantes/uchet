<?php

namespace app\modules\zakupki\controllers;

use yii\web\Controller;

/**
 * Default controller for the `zakupki` module
 */
class DefaultController extends \app\controllers\BaseController
{
    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
    }
}
