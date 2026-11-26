<?php
namespace app\modules\sklad;

/**
 * Модуль «Склад»: справочные экраны по элементу, которые открывают все отделы.
 *
 * Карточка элемента (форма Access Fobnovlen1) — не часть закупок: её открывают
 * из «Статистики», счетов, склада и производства. Поэтому модуль отдельный,
 * и закупки могут на него ссылаться, а не наоборот.
 *
 * Адреса начинаются с /sklad/..., например /sklad/elem/view?id=123.
 */
class Module extends \yii\base\Module
{
    public $controllerNamespace = 'app\modules\sklad\controllers';

    /** /sklad без продолжения открывает карточку элемента (нужен id) */
    public $defaultRoute = 'elem';
}
