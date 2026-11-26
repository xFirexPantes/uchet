<?php
namespace app\modules\zakupki\models;

use yii\base\Model;

/**
 * Фильтры формы frmSt «Статистика».
 *
 * Имена полей — по элементам Access, чтобы сверять с VBA:
 *   vid     ComboBox «Вид» (RowSource exec dbo.xp_GetVidSel, ColumnWidths "2326;0" — видно Name, значение Num)
 *   fnd     строка поиска (FindElem: Change → FillStat → SetRS4Statistic "%"+st+"%")
 *   post    filt1, поставщик
 *   city    filt2, город
 *   dateS   filt3, дата документа «с»
 *   datePo  дата документа «по» (в Access PickDate / PickDate2)
 *   firm    WhatFirm
 *   fz275   флажок FZ275
 *   typeSel Rgm: 1 — НЕ давальческое, 2 — давальческое, 3 — не важно
 *   analog  Analog: 0 — обычные, 1 — по аналогу
 *   sklad   SkladID (для вида 1: список по складу)
 *   bnd, razn, kurs — только для вида 7_0 (нижняя граница, разница, курс)
 *
 * fltZ1 («Недопол>0» / «Все») пока не выносится в интерфейс: 0 — без суффикса _0.
 */
class StatFilter extends Model
{
    public $vid = '1';      // значение Num из xp_GetVidSel
    public $fnd = '';       // поиск по списку
    public $post = '';      // filt1: поставщик
    public $city = '';      // filt2: город
    public $dateS;          // filt3: дата документа с
    public $datePo;         // дата документа по
    public $firm = 0;       // WhatFirm: фирма из exec dbo.xp_GetWhatFirm (в Access видна только в виде 1)
    public $fz275 = 0;      // флажок FZ275
    public $typeSel = 1;    // TypeSel / Rgm
    public $analog = 0;     // Analog
    public $sklad = 0;      // SkladID
    public $bnd;            // PriceBound: граница цены; при виде 7 — в долларах (dl=1), иначе в рублях
    public $razn = 0;       // Razn: разница, только вид 7
    public $kurs = 0;       // KursUSD: курс на экране учёта (Forms!frmUchet!KursUSD)
    public $dl = 0;         // флажок dl: 1 — граница в долларах (Access ставит его для вида 7)
    public $fltZ1 = 0;      // 1 — «Недопол>0» (суффикс _0), 0 — все
    public $sort = '';      // сортировка списка: имя поля (OrdKod/OrdNaim/… в Access — кнопки-переключатели)
    public $dir = 'asc';    // asc | desc

    public function formName()
    {
        return '';          // адрес вида ?vid=1&fnd=... без префикса модели
    }

    public function rules()
    {
        return [
            ['vid', 'string', 'max' => 4],
            ['vid', 'match', 'pattern' => '/^[0-9ab]$/', 'message' => 'Неизвестный вид списка.'],
            ['fnd', 'trim'],
            ['fnd', 'string', 'max' => 100],
            ['post', 'string', 'max' => 100],
            ['city', 'string', 'max' => 100],
            [['dateS', 'datePo'], 'date', 'format' => 'php:Y-m-d', 'skipOnEmpty' => true],
            ['datePo', 'compare', 'compareAttribute' => 'dateS', 'operator' => '>=',
                'skipOnEmpty' => true, 'message' => 'Неправильно указаны даты.'],
            [['fz275', 'analog', 'fltZ1', 'dl'], 'boolean'],
            ['typeSel', 'in', 'range' => [1, 2, 3]],
            [['sklad', 'firm'], 'integer'],
            [['bnd', 'razn', 'kurs'], 'number'],
            ['sort', 'string', 'max' => 40],
            ['dir', 'in', 'range' => ['asc', 'desc']],
        ];
    }

    /** Вид с суффиксом для имени процедуры: 1 + fltZ1=1 → "1_0". */
    public function what(): string
    {
        return (string)$this->vid . ((int)$this->fltZ1 === 1 ? '_0' : '');
    }
}
