<?php
namespace app\modules\zakupki\models;

use yii\base\Model;

/**
 * Фильтры формы frmSt «Статистика».
 *
 * Имена полей — по элементам Access, чтобы сверять с VBA:
 *   vid             ComboBox «Вид» (RowSource exec dbo.xp_GetVidSel, ColumnWidths "2326;0" — видно Name, значение Num)
 *   fnd             строка поиска (FindElem: Change → FillStat → SetRS4Statistic "%"+st+"%")
 *   f               мультивыбор фирм: frmFndFirm (OpenArgs 7) → xp_frmStMultiSelectResult.
 *                   "%" — все фирмы; иначе строка ID через запятую. Второй параметр процедуры вида.
 *   manager         SkladManagers: exec dbo.xp_GetSkladManagersNew, ColumnWidths "2268;0;0;0" —
 *                   видно имя, значение первой колонки. DefaultValue "%" — все менеджеры.
 *   dateS, datePo   PickDate / PickDate2: заполнены оба → xp_frmStNewRS1_1 (в Access так же)
 *   firm            WhatFirm: exec dbo.xp_GetWhatFirm, ColumnWidths "0;1701" — видно название, значение ID
 *   fz275           флажок FZ275
 *   typeSel         Rgm: 1 — НЕ давальческое, 2 — давальческое, 3 — не важно
 *   fltStatProblEl  состояние проблемы: exec dbo.xp_StatWorkWithProblemLstSost 1, значение 255 — все
 *   fltZ1           переключатель «Заказ>0» / «Все»: 1 — «Заказ>0» (суффикс _0 в имени процедуры)
 *   sklad           SkladID (для вида 1: список по складу)
 *   bnd, razn, kurs — только для вида 7_0 (нижняя граница, разница, курс)
 *   analog          Analog: в Access не фильтр списка — флажок подформы доп. данных
 *                   (Analog_AfterUpdate → SetRS4DopData). В вебе пока не используется.
 *
 * filt1/filt2/filt3 (поставщик, город, дата документа) — фильтры временной таблицы счетов
 * (#tblTmpSt, кнопки filt9/filt10 в Access). В основную процедуру вида они НЕ передаются.
 * Переносятся отдельно, вместе с экраном фильтра по счетам.
 */
class StatFilter extends Model
{
    public $vid = '1';              // значение Num из xp_GetVidSel
    public $fnd = '';               // поиск по списку
    public $f = '%';                // мультивыбор фирм (frmFndFirm 7)
    public $manager = '%';          // SkladManagers
    public $dateS;                  // PickDate: дата документа с
    public $datePo;                 // PickDate2: дата документа по
    public $firm = 0;               // WhatFirm: фирма из exec dbo.xp_GetWhatFirm
    public $fz275 = 0;              // флажок FZ275
    public $typeSel = 1;            // TypeSel / Rgm
    public $fltStatProblEl = 255;   // состояние проблемы (виден только в виде 1)
    public $fltZ1 = 1;              // 1 — «Заказ>0» (суффикс _0), 0 — все
    public $sklad = 0;              // SkladID
    public $bnd;                    // PriceBound: граница цены; при виде 7 — в долларах (dl=1), иначе в рублях
    public $razn = 0;               // Razn: разница, только вид 7
    public $kurs = 0;               // KursUSD: курс на экране учёта (Forms!frmUchet!KursUSD)
    public $dl = 0;                 // флажок dl: 1 — граница в долларах (Access ставит его для вида 7)
    public $sort = '';              // сортировка списка: имя поля (OrdKod/OrdNaim/… в Access — кнопки-переключатели)
    public $dir = 'asc';            // asc | desc

    public function formName()
    {
        return '';                  // адрес вида ?vid=1&fnd=... без префикса модели
    }

    /**
     * Мультивыбор фирм приходит массивом (f[]), а процедуре нужна строка через запятую.
     * Пустой выбор — «%», как в Access при выборе первой строки списка.
     */
    public function load($data, $formName = null)
    {
        $ok = parent::load($data, $formName);

        if (is_array($this->f)) {
            $vals = array_filter(array_map('strval', $this->f), static fn($v) => $v !== '');
            $this->f = (!$vals || in_array('%', $vals, true)) ? '%' : implode(',', $vals);
        }
        if ($this->f === '') {
            $this->f = '%';
        }
        if ($this->manager === '') {
            $this->manager = '%';
        }

        return $ok;
    }

    public function rules()
    {
        return [
            ['vid', 'string', 'max' => 4],
            ['vid', 'match', 'pattern' => '/^[0-9ab]$/', 'message' => 'Неизвестный вид списка.'],
            ['fnd', 'trim'],
            ['fnd', 'string', 'max' => 100],
            ['f', 'string', 'max' => 500],
            ['manager', 'string', 'max' => 100],
            [['dateS', 'datePo'], 'date', 'format' => 'php:Y-m-d', 'skipOnEmpty' => true],
            ['datePo', 'compare', 'compareAttribute' => 'dateS', 'operator' => '>=',
                'skipOnEmpty' => true, 'message' => 'Неправильно указаны даты.'],
            [['fz275', 'dl'], 'boolean'],
            ['fltZ1', 'in', 'range' => [0, 1]],
            ['typeSel', 'in', 'range' => [1, 2, 3]],
            ['fltStatProblEl', 'integer'],
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

    /** Заголовок фирмы для кнопки фильтра: «Все» или перечень выбранных. */
    public function firmTitle(array $firms): string
    {
        if ($this->f === '%' || $this->f === '') {
            return 'Все';
        }
        $ids = array_filter(array_map('trim', explode(',', $this->f)), static fn($v) => $v !== '');
        if (!$ids) {
            return 'Все';
        }
        $names = [];
        foreach ($firms as $row) {
            $id = (string)($row['id'] ?? '');
            if (in_array($id, $ids, true)) {
                $names[] = (string)($row['name'] ?? $id);
            }
        }
        return $names ? implode(', ', $names) : implode(', ', $ids);
    }
}
