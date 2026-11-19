<?php
namespace app\modules\zakupki\models;

use yii\base\Model;

/**
 * Фильтры формы frmStat. Значения по умолчанию те же, что в Form_Open:
 * период = последний год, «Открытые счета», НЕ давальческое сырьё, «Элементы».
 */
class VhSchetFilter extends Model
{
    public $dateS;          // txtDateS
    public $datePo;         // txtDatePo
    public $nsch = '';      // cboNCh, № счета (ищется по части)
    public $allSch = 0;     // AllSch: 0 = открытые, 1 = все
    public $typeSel = 1;    // TypeSel: 1 = НЕ давальческое, 2 = давальческое, 3 = не важно
    public $typeSch = 0;    // GrpTypeSch: 0 = элементы, 1 = детали
    public $id = 0;         // OpenArgs: открыть на один счёт

    public function init()
    {
        parent::init();
        $this->datePo = date('Y-m-d');
        $this->dateS  = date('Y-m-d', strtotime('-1 year'));
    }

    public function formName()
    {
        return '';          // адрес вида ?dateS=...&nsch=..., без префикса модели
    }

    public function rules()
    {
        return [
            [['dateS', 'datePo'], 'date', 'format' => 'php:Y-m-d', 'skipOnEmpty' => true],
            ['datePo', 'compare', 'compareAttribute' => 'dateS', 'operator' => '>=',   // строки Y-m-d сравниваются верно
                'skipOnEmpty' => true, 'message' => 'Неправильно указаны даты.'],
            ['nsch', 'trim'],
            ['nsch', 'string', 'max' => 18],          // в процедуре varchar(20), плюс два '%'
            [['allSch'], 'boolean'],
            ['typeSel', 'in', 'range' => [1, 2, 3]],
            ['typeSch', 'in', 'range' => [0, 1]],
            ['id', 'integer', 'min' => 0],
        ];
    }
}
