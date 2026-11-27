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
 *                   Для вида b: exec dbo.xp_SkladTrackingSprLst 1.
 *   dateS, datePo   PickDate / PickDate2: заполнены оба → xp_frmStNewRS1_1 (в Access так же)
 *   firm            WhatFirm: exec dbo.xp_GetWhatFirm, ColumnWidths "0;1701" — видно название, значение ID
 *   fz275           ID ФЗ-275: exec dbo.xp_frmStLstFZ275, ColumnWidths "0;1419;852" —
 *                   значение ID, по умолчанию 0 («Все»). Видно только в виде 1.
 *   typeSel         Rgm: 1 — НЕ давальческое, 2 — давальческое, 3 — не важно
 *   fltStatProblEl  состояние проблемы: exec dbo.xp_StatWorkWithProblemLstSost 1, значение 255 — все
 *   fltZ1           переключатель «Заказ>0» / «Все» (вид b: «Все» / «Нет счетов более недели»):
 *                   1 — первый вариант (суффикс _0 в имени процедуры), 0 — второй
 *   sklad           SkladID (для вида 1: список по складу; для вида b: -500)
 *   bnd, razn, kurs — только для вида 7_0 (нижняя граница, разница, курс)
 *   analog          Analog: в Access не фильтр списка — флажок подформы доп. данных
 *                   (Analog_AfterUpdate → SetRS4DopData). В вебе пока не используется.
 *
 * filt1/filt2/filt3 (поставщик, город, дата документа) — фильтры временной таблицы счетов
 * (#tblTmpSt, кнопки filt9/filt10 в Access). В основную процедуру вида они НЕ передаются.
 * Переносятся отдельно, вместе с экраном фильтра по счетам.
 *
 * ==========================================================================
 * Правила по видам — из fnRepaintFormStat (строки 2752–3100 в FrmSt.frm):
 *
 *   Вид | fltZ1 | fltZ1 вид | FZ275 вид | Состояние вид | Даты вид | Фирмы вид | WhatFirm/Менеджер вид | Сырьё вид | Склад
 *   --- | ----- | --------- | --------- | ------------- | -------- | --------- | --------------------- | --------- | ------
 *   1   | =1    | виден     | виден     | виден         | виден*   | видна     | видны                 | видно     | 0
 *   2   | =1    | виден     | =0 скрыт  | скрыт         | скрыт    | видна     | видны                 | видно     | 0
 *   3   | =0    | скрыт     | =0 скрыт  | виден         | скрыт    | видна     | видны                 | видно     | 0
 *   4   | =1    | виден     | =0 скрыт  | скрыт         | скрыт    | видна     | видны                 | видно     | 0
 *   5   | =0    | скрыт     | =0 скрыт  | скрыт         | скрыт    | видна     | видны                 | видно     | 0
 *   7   | =1    | скрыт     | =0 скрыт  | скрыт         | скрыт    | скрыта    | скрыты                | скрыто    | 0
 *   9   | =1    | скрыт     | =0 скрыт  | скрыт         | скрыт    | видна     | видны                 | видно     | 0
 *   a   | =1    | скрыт     | =0 скрыт  | скрыт         | скрыт    | видна     | видны                 | видно     | 0
 *   b   | =1    | виден**   | =0 скрыт  | скрыт         | скрыт    | видна     | видны                 | видно     | -500
 *
 * * Даты: PickDate.Visible = fltZ1 = 1 And lgOp = "1" — только вид 1.
 * ** Для вида b подписи: «Все» (1) / «Нет счетов более недели» (0), заголовок пустой.
 *   FndZac/WhatFirm/SkladManagers в Case "b" скрываются, но общий блок после Select Case
 *   (TypeSel.Visible = lgOp <> "7") снова их показывает — поэтому в вебе они видны.
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
    public $fz275 = 0;              // ID ФЗ-275 (выбор из xp_frmStLstFZ275)
    public $typeSel = 1;            // TypeSel / Rgm
    public $fltStatProblEl = 255;   // состояние проблемы (виден только в виде 1)
    public $fltZ1 = 1;              // 1 — первый вариант, 0 — второй (суффикс _0 в имени)
    public $sklad = 0;              // SkladID
    public $bnd;                    // PriceBound: граница цены; при виде 7 — в долларах (dl=1), иначе в рублях
    public $razn = 0;               // Razn: разница, только вид 7
    public $kurs = 0;               // KursUSD: курс на экране учёта (Forms!frmUchet!KursUSD)
    public $dl = 0;                 // флажок dl: 1 — граница в долларах (Access ставит его для вида 7)
    public $sort = '';              // сортировка списка: имя поля (OrdKod/OrdNaim/… в Access — кнопки-переключатели)
    public $dir = 'asc';            // asc | desc
    public $vidPrev = '';           // вид на момент предыдущей отправки формы (скрытое поле)

    /**
     * Правила по «Виду» — из fnRepaintFormStat и ПолеСоСписком24_AfterUpdate (frmSt).
     * Ключи (не указан — значение по умолчанию):
     *   z1      — значение fltZ1, которое Access выставляет сам (нет ключа — оставить выбор);
     *   z1Visible — виден ли переключатель «Заказ» (по умолчанию true);
     *   firms   — виден ли выбор фирм FndZac (по умолчанию true);
     *   what    — видны ли WhatFirm / SkladManagers / TypeSel (по умолчанию true);
     *   sklad   — принудительное значение SkladID (по умолчанию 0).
     */
    private const VIEW_RULES = [
        '1' => [],
        '2' => ['z1' => 1],
        '3' => ['z1' => 0, 'z1Visible' => false],
        '4' => ['z1' => 1],
        '5' => ['z1' => 0, 'z1Visible' => false],
        '7' => ['z1' => 1, 'z1Visible' => false, 'firms' => false, 'what' => false],
        '9' => ['z1' => 1, 'z1Visible' => false],
        'a' => ['z1' => 1, 'z1Visible' => false],
        'b' => ['z1' => 1, 'sklad' => -500],
    ];

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
            ['vidPrev', 'string', 'max' => 4],
            ['fnd', 'trim'],
            ['fnd', 'string', 'max' => 100],
            ['f', 'string', 'max' => 500],
            ['manager', 'string', 'max' => 100],
            [['dateS', 'datePo'], 'date', 'format' => 'php:Y-m-d', 'skipOnEmpty' => true],
            ['datePo', 'compare', 'compareAttribute' => 'dateS', 'operator' => '>=',
                'skipOnEmpty' => true, 'message' => 'Неправильно указаны даты.'],
            ['fz275', 'integer'],
            ['fltZ1', 'in', 'range' => [0, 1]],
            ['typeSel', 'in', 'range' => [1, 2, 3]],
            ['fltStatProblEl', 'integer'],
            [['sklad', 'firm'], 'integer'],
            [['bnd', 'razn', 'kurs'], 'number'],
            ['sort', 'string', 'max' => 40],
            ['dir', 'in', 'range' => ['asc', 'desc']],
        ];
    }

    /**
     * Применяет правила по «Виду» к фильтру (как fnRepaintFormStat в Access).
     *
     * Важно: Access вызывает fnRepaintFormStat ТОЛЬКО при смене вида
     * (ПолеСоСписком24_AfterUpdate). Поэтому принудительный fltZ1 ставится только
     * если вид изменился (vidPrev), иначе перебивался бы выбор пользователя —
     * например, «Все» в видах 2, 4 и b, где переключатель виден.
     *
     * Скрытые фильтры (FZ275, даты, SkladID) приводятся к значениям Access всегда:
     * пользователь их не видит и изменить не может.
     */
    public function applyViewRules(): void
    {
        $r = self::VIEW_RULES[$this->vid] ?? [];
        $changed = $this->firstTime() || (string)$this->vidPrev !== (string)$this->vid;

        // Принудительное значение fltZ1 — только при смене вида
        if ($changed && array_key_exists('z1', $r)) {
            $this->fltZ1 = (int)$r['z1'];
        }

        // FZ275: только в виде 1, в остальных = 0
        if ((string)$this->vid !== '1') {
            $this->fz275 = 0;
        }

        // Вид b: принудительный склад -500 (в Access Case "b": Me!SkladID = -500)
        if (array_key_exists('sklad', $r)) {
            $this->sklad = (int)$r['sklad'];
        }

        // Даты видны только в виде 1 при fltZ1=1 — Access скрывает и очищает
        if (!$this->showDates()) {
            $this->dateS = null;
            $this->datePo = null;
        }
    }

    /** Первое открытие формы: в Access это FirstTime = True (только что открыли). */
    public function firstTime(): bool
    {
        return (string)$this->vidPrev === '';
    }

    /**
     * Все фильтры в значениях по умолчанию — условие FirstTime в cboflt_AfterUpdate.
     *
     * Access: если при первом применении фильтров f = "%", SkladManagers = "%",
     * TypeSel = 1 и WhatFirm = 0, процедура вызывается с SkladID = -500 —
     * то есть форма открывается пустой («установите фильтры»), а не грузит весь склад.
     */
    public function isDefaultSet(): bool
    {
        return $this->f === '%'
            && $this->manager === '%'
            && (int)$this->typeSel === 1
            && (int)$this->firm === 0;
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

    // ==========================================================================
    // Вспомогательные методы для видимости фильтров в представлении.
    // ==========================================================================

    /** Переключатель «Заказ» виден: 1, 2, 4, b (в 3, 5, 7, 9, a Access его скрывает) */
    public function showZ1(): bool
    {
        return (bool)(self::VIEW_RULES[$this->vid]['z1Visible'] ?? true);
    }

    /** Подписи вариантов fltZ1: [значение 1, значение 0] */
    public function z1Labels(): array
    {
        if ((string)$this->vid === 'b') {
            return ['Все', 'Нет счетов более недели'];
        }
        return ['Заказ>0', 'Все'];
    }

    /** Заголовок группы fltZ1: у вида b Access очищает подпись «Фильтр по "Заказать"» */
    public function z1Title(): string
    {
        return (string)$this->vid === 'b' ? '' : 'Заказ';
    }

    /** FZ275 виден: только вид 1 */
    public function showFz275(): bool
    {
        return (string)$this->vid === '1';
    }

    /** Состояние проблемы (fltStatProblEl) видно: 1 и 3 */
    public function showStatus(): bool
    {
        return in_array((string)$this->vid, ['1', '3'], true);
    }

    /** Даты PickDate/PickDate2: вид 1 и fltZ1=1 (уже после applyViewRules) */
    public function showDates(): bool
    {
        return (string)$this->vid === '1' && (int)$this->fltZ1 === 1;
    }

    /**
     * Кнопка выбора фирм (FndZac) видна: все виды, кроме 7.
     *
     * В Case "b" Access сначала ставит FndZac.Visible = False, но общий блок после
     * Select Case снова включает её (Me!FndZac.Visible = Me!TypeSel.Visible, а TypeSel
     * скрыт только в виде 7) — поэтому в вебе для b она видна.
     */
    public function showFirms(): bool
    {
        return (bool)(self::VIEW_RULES[$this->vid]['firms'] ?? true);
    }

    /** WhatFirm / менеджер / «Сырьё» видны: не 7 */
    public function showWhat(): bool
    {
        return (bool)(self::VIEW_RULES[$this->vid]['what'] ?? true);
    }
}
