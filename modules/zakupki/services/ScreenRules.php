<?php
namespace app\modules\zakupki\services;

use app\components\AccessFormat as F;
use Yii;

/**
 * Правила экранов: что сейчас можно менять, какие кнопки активны, что видно, какие подписи и фон.
 * Решения взяты из VBA форм (Form_Load, OpenSch, RepaintSch, IsChangedNakl и т.п.).
 *
 * Зачем отдельно: вид «как в Access» (views/…) и будущий современный вид берут одно и то же состояние,
 * поэтому ведут себя одинаково. View только раскладывает его. Имена — имена элементов формы Access.
 *
 * Состояние экрана:
 *   edit   [имя => вид]   что можно менять/нажимать: text|num|date|localdate|select|search|check|radio|path|button
 *   off    [имя => почему] видно, но неактивно (подсказка на кнопке)
 *   show   [имена]        показать (в макете Access скрыто)
 *   hide   [имена]        спрятать
 *   labels [имя => текст] подписи, которые меняет VBA
 *   bg     int            цвет фона области данных (BGR, как в Access)
 *   flags  [имя => bool]  признаки для скриптов экрана (allowEd, closed, inUE …)
 *
 * Сервер всё равно проверяет каждое действие сам (закрытый счёт, права): правила здесь — для экрана, а не вместо проверок.
 */
class ScreenRules
{
    /** Карточка счёта на элементы (frmMassInputSch). $d — SchetService::getElem. */
    public static function schetElem(array $d): array
    {
        $rec = $d['rec'];
        $allowEd = SchetSaveService::allowEd($rec);                    // OpenSch: FullSch или EdtDavSch у давальческого
        $closed  = F::bool($rec['IsClosed'] ?? 0);
        $inUE    = (int)($rec['InUE'] ?? 0) !== 0;
        $edit = [];
        $off  = [];
        if ($allowEd) {
            // Me.AllowEdits = AllowEd: поля шапки, флажки, «Ставка НДС»
            $edit = [
                'НомерСчета' => 'text', 'ДатаСчета' => 'date', 'NDS' => 'num', 'Sm' => 'num', 'AdditSum' => 'num',
                'SumDost' => 'num', 'DopSum' => 'num', 'Dst' => 'select', 'TP' => 'select', 'Dta2Opl' => 'date', 'Fnd' => 'search',
                'TakeMe' => 'check', 'NotUsed' => 'check', 'flgFZ275' => 'check', 'SetNDS2Str' => 'button',
                'Pth' => 'path', 'AdditPth' => 'path', 'AllNakl' => 'button', 'butExit' => 'button',
            ];
            if ((int)($rec['Repl'] ?? 0) <= 1) $edit['IsTo1C'] = 'check';   // Flag1C: IsTo1C.Enabled = Not Repl > 1
            if ($closed) $edit['OpnSch'] = 'button';                       // открыть закрытый счёт
            else $edit += ['butSaveAs' => 'button', 'DelRec' => 'button', 'AddNakl' => 'button', 'butDob' => 'button', 'Кнопка15' => 'button'];
        }
        $why = $closed ? 'счёт закрыт' : 'нет прав';
        foreach (['butDob', 'Кнопка15'] as $n) if (!isset($edit[$n])) $off[$n] = $why;

        $hide = ['LoadSchFromXl'];                                     // видна только у нового счёта (ID = 0)
        if (!$inUE) $hide[] = 'InUEtxt';
        if (trim((string)($rec['INN'] ?? '')) !== '') $hide[] = 'LoadM15';
        return [
            'edit' => $edit, 'off' => $off,
            'show' => ($closed && $allowEd) ? ['OpnSch'] : [], 'hide' => $hide, 'labels' => [],
            'bg' => F::bool($rec['flgFZ275'] ?? 0) ? 8715146 : 13290186,   // RepaintSch: зелёный у счёта по ФЗ-275
            'flags' => ['allowEd' => $allowEd, 'closed' => $closed, 'inUE' => $inUE, 'exitChecks' => $allowEd],
        ];
    }

    /** Карточка счёта на детали (frmScetaDetal). $d — SchetService::getDetal. AllowEd у деталей всегда True. */
    public static function schetDetal(array $d): array
    {
        $rec = $d['rec'];
        $closed = $d['calc']['Closed'] ?? F::bool($rec['IsClosed'] ?? 0);   // CheckClosed (xp_IsClosedSch)
        $to1C = SchetDetalService::to1CState((int)($d['calc']['Sost'] ?? 0), $rec);
        // флажки (MouseDown) и Delete в пути работают всегда, как в Access
        $edit = ['TakeMe' => 'check', 'NotUsed' => 'check', 'PrcWithNDS' => 'check', 'SchWithNDS' => 'check',
                 'Pth' => 'path', 'Additpth' => 'path', 'AllNakl' => 'button', 'butExit' => 'button'];
        if ($to1C['enabled']) $edit['IsTo1C'] = 'check';               // Flag1C: IsTo1C.Enabled = Not Sost > 1
        $off = [];
        if ($closed) {
            $edit['OpnSch'] = 'button';
            $off['EdtPosSchDet'] = 'счёт закрыт';
        } else {
            $edit += ['butSaveAs' => 'button', 'DelRec' => 'button', 'AddNakl' => 'button', 'EdtPosSchDet' => 'button'];
        }
        if (SchetDetalService::allowEdits($rec)) {                     // поля ввода — пока счёт не закрыт
            $edit += ['NumSc' => 'text', 'DtaSc' => 'date', 'IspFrm' => 'select', 'Dst' => 'select', 'NDS' => 'num', 'Sm' => 'num',
                      'SmNDS' => 'num', 'SumDost' => 'num', 'Dta2Opl' => 'date', 'Fnd' => 'search', 'TpeScet' => 'radio',
                      'DTPost' => 'localdate', 'DPDT' => 'localdate', 'MassSetDTPost' => 'button', 'MassSetDTDP' => 'button'];
        }
        $show = $closed ? ['OpnSch'] : [];
        if (($rec['UchetID'] ?? null) === null) $show[] = 'LoadM15';   // LoadM15.Visible = IsNull(UchetID)
        return [
            'edit' => $edit, 'off' => $off, 'show' => $show, 'hide' => $closed ? [] : ['OpnSch'], 'labels' => [],
            'bg' => F::bool($rec['flgFZ275'] ?? 0) ? 8715290 : 16764057,   // RepaintSchDetal
            'flags' => ['allowEd' => true, 'closed' => $closed, 'to1C' => $to1C['on'], 'notUsed' => $to1C['notUsed'], 'exitChecks' => true],
        ];
    }

    /**
     * Накладная (frmMassInput). $d — NaklService::view.
     * Просмотр: ✎ (перейти к правке), ＋ и «На склад». Правка (OpenArgs 7): шапка, ＋, 💾, −, «На склад»; фон 12615935.
     * Rgm 2 (новая к счёту на элементы): внизу lstSch, высота 12060 твипов.
     */
    public static function nakl(array $d): array
    {
        $rec = $d['rec'];
        $edit = $d['edit']
            ? ['НомерНакладной' => 'text', 'ДатаНакладной' => 'date', 'Dst' => 'select', 'butSaveAs' => 'button', 'DelRec' => 'button', 'ToSklad' => 'button']
            : ['Кнопка58' => 'button', 'ToSklad' => 'button'];
        $edit += ['butDob' => 'button', 'LoadGTD' => 'button', 'butExit' => 'button'];   // ＋ (frmAddEl2Nakl) и ГТД (frmLoadKompel) — в обоих режимах, как в Access
        $off = [];
        // IsChangedNakl(False): столбцы строк, которые можно менять (подписи цветом 12615935); Vsego — только в валюте
        $cols = [];
        if ($d['edit']) {
            $cols = ['Kol', 'ТочноеНазвание', 'OrigCountry', 'GTD', 'PriceWNDS', 'SumNDS'];
            if (!$d['det']) {                                          // WhoOpen 0: KolByNakl и (в валюте) Vsego
                $cols[] = 'KolByNakl';
                if ((int)($rec['InUE'] ?? 0) > 0) $cols[] = 'Vsego';
            }
        }
        $lst = $d['lstSch'] !== null;
        return [
            'edit' => $edit, 'off' => $off,
            'show' => $lst ? ['lstSch'] : [], 'hide' => $d['leg01'] ? [] : ['Leg01'],   // Leg01: у первой строки IDPcb > -1
            'labels' => [                                              // у деталей с IsAkt = 1 — «акт» вместо «накладной»
                'Надпись33' => $d['isAkt'] ? '№ акта:' : '№ накладной:',
                'Надпись34' => $d['isAkt'] ? 'Дата акта:' : 'Накладная от:',
                'Надпись42' => $d['isAkt'] ? 'АКТ' : 'НАКЛАДНАЯ',
            ],
            'bg' => $d['edit'] ? 12615935 : 13290186,
            'height' => $lst ? 804 : 641,
            'editCols' => $cols,
            'flags' => ['edit' => $d['edit'], 'det' => $d['det'], 'ro' => $d['ro'], 'akt' => $d['isAkt'], 'lstSch' => $lst],
        ];
    }

    /**
     * Окно позиции счёта на элементы (frmPosInput), начальное состояние после Form_Load по режиму (KodNullEl).
     * Дальше видимость меняют действия в окне (⇄ «деньги в рублях», ручной ввод, упаковка) — это делает скрипт окна.
     */
    public static function posElem(array $v): array
    {
        $mode = (int)$v['mode']; $inUE = (int)$v['inUE'];
        $edit = [
            'txtKolich' => 'num', 'txtPriceOpt' => 'num', 'txtPriceRub' => 'num', 'SumNDS' => 'num', 'Vsego' => 'num', 'txtKurs' => 'num',
            'UpKoef' => 'text', 'kol2' => 'num', 'txtInfo' => 'text', 'DtaPost' => 'date', 'FirstSrokPost' => 'date', 'NmeCat' => 'path',
            'ManualKol' => 'text', 'ManualNme' => 'text', 'Warn' => 'text',
            'txtNDS' => 'select', 'PostOKEI' => 'select', 'PostOKEI2' => 'select',
        ];
        $locked = [];                                                  // Enabled = False (поле видно, но не меняется)
        $hide = ['SumWNDS', 'PrcNDS'];
        if ((float)$v['kol2'] <= 1) array_push($hide, 'kol2', 'PostOKEI2');
        if (empty($v['manualFlg'])) array_push($hide, 'ManualKol', 'ManualNme');
        $labels = [];
        if ($mode === 5) {                                             // Case 5: первая поставка не видна, в валюте — без сумм НДС
            $hide[] = 'FirstSrokPost';
            if ($inUE > 0) $hide[] = 'SumNDS';
        }
        if ($inUE !== 0 && ($mode === 7 || $mode === 5)) $labels = ['Надпись89' => 'Цена, вал.', 'Надпись109' => 'Сумма,вал.'];
        if ($mode === 7) {                                             // по позиции есть накладная
            $locked = ['txtKolich', 'txtInfo', 'DtaPost', 'FirstSrokPost', 'txtKurs', 'txtNDS'];
            array_push($hide, 'WOQuest', 'Надпись121');
        }
        return [
            'edit' => $edit, 'off' => [], 'locked' => $locked,
            // в макете скрыты, форма показывает (дальше видимость меняет скрипт)
            'show' => ['txtPriceRub', 'PostOKEI', 'kol2', 'PostOKEI2', 'SumWNDS', 'PrcNDS'],
            'hide' => array_values(array_unique($hide)), 'labels' => $labels, 'bg' => null,
            'flags' => ['mode' => $mode, 'inUE' => $inUE, 'woQuest' => $mode === 7 || !empty($v['woQuest']),
                        'sumNdsInRub' => !($mode === 5 && $inUE > 0)],   // SumNDS при «деньгах в рублях»
        ];
    }
}
