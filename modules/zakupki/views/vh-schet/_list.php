<?php
/**
 * Таблица счетов на месте ActiveX ListView xListSch. Колонки как в FillListSch.
 * @var array $rows
 */
use yii\helpers\Html;
use yii\helpers\Json;

// Цвет из процедуры: число Access (BGR, 255 = красный) → CSS. 0 = обычный чёрный.
$css = static fn($c) => ($c = (int)$c) ? sprintf('#%02x%02x%02x', $c & 255, ($c >> 8) & 255, ($c >> 16) & 255) : '';

// [поле, заголовок, поле с цветом, поле для сортировки, ширина px, подсказка]
// Заголовки и ширины взяты из настроек ActiveX ListView в выгрузке (OleData), колонки шириной 0 не показываем.
$cols = [
    ['Hld',       'Холдинг',     null,       null,        140, ''],
    ['Prim',      'Примечание',  null,       null,        307, ''],
    ['NSch',      'Номер счёта', 'Delayed',  null,         96, ''],
    ['DtaSch',    'Дата счёта',  'Delayed2', 'LngDtaSch',  80, ''],
    ['closed',    'Закрыт',      'ColFZ275', null,         67, ''],
    ['Dta',       'Дата',        'OplCol',   'LngDta',     80, ''],
    ['Tme',       'Время',       'ColPth',   null,         53, ''],
    ['InControl', 'К',           null,       null,         40, ''],
    ['ClassVl',   'Р',           null,       null,         40, 'На контроле'],
    ['TP',        'TP',          'TpCol',    null,         87, ''],
    ['usr',       'User',        null,       null,         40, ''],
    ['HowOpl',    'Оплата',      'PlCol',    null,        120, ''],
    ['DtaToOpl',  'Отдано',      'OplPrc',   null,         93, ''],
    ['Mails',     'Писем',       null,       null,         53, ''],
    ['Acts',      'Акты',        null,       null,         53, ''],
    ['Reps',      'Отчет',       null,       null,         53, ''],
    ['Comm',      'Комментарий', null,       null,        133, ''],
];
// Скрытые поля для панели (в Access ListSubItems 13, 15, 17-26)
$hidden = ['PredShcID', 'pth', 'OstOPl', 'SmSch', 'WhoPick', 'Mails', 'Diff', 'Acts', 'Reps', 'InUEtxt', 'Comm', 'Prim', 'OplPrc'];
?>
<table id="vhList" class="vh-table" style="width:<?= array_sum(array_column($cols, 4)) ?>px">
    <colgroup>
        <?php foreach ($cols as $c): ?><col style="width:<?= $c[4] ?>px"><?php endforeach ?>
    </colgroup>
    <thead><tr>
        <?php foreach ($cols as $i => [$f, $title, , , , $tip]): ?>
            <th data-col="<?= $i ?>" data-field="<?= $f ?>" title="<?= Html::encode($tip ?: $title) ?>"><?= Html::encode($title) ?><span class="rsz"></span></th>
        <?php endforeach ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $rowColor = $css($r['ColTakeMe'] ?? 0);
        $data = array_intersect_key($r, array_flip($hidden)); ?>
        <tr data-id="<?= (int)$r['PredShcID'] ?>" data-row="<?= Html::encode(Json::encode($data)) ?>"
            <?= $rowColor ? 'style="color:' . $rowColor . ';font-weight:bold"' : '' ?>>
            <?php foreach ($cols as [$f, , $colorField, $sortField]):
                $c = $colorField ? $css($r[$colorField] ?? 0) : '';
                if ($f === 'InControl' && ($r[$f] ?? '') === 'да') { $c = '#ff0000'; }
                if ($f === 'Mails' && (float)($r['Diff'] ?? 0) > 0) { $c = '#ff0000'; }   // как SubItems(21).ForeColor = vbRed
                $style = $c ? "color:$c;font-weight:bold" : '';
                $sort  = $sortField ? ($r[$sortField] ?? '') : ($r[$f] ?? ''); ?>
                <td data-sort="<?= Html::encode($sort) ?>" <?= $style ? "style=\"$style\"" : '' ?>><?= Html::encode($r[$f] ?? '') ?></td>
            <?php endforeach ?>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
