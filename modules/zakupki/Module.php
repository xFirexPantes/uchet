<?php
namespace app\modules\zakupki;

/**
 * Модуль «Закупки»: перенесённые формы раздела закупок.
 * Подключается одной строкой в config/web.php ('modules' => ['zakupki' => ...]).
 * Адреса экранов начинаются с /zakupki/..., например /zakupki/vh-schet/index.
 */
class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\modules\zakupki\controllers';

    /** /zakupki без продолжения открывает «Входящие счета» */
    public $defaultRoute = 'vh-schet';
}
