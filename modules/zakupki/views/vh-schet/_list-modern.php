<?php
/**
 * Таблица счетов современного вида. Колонки — как в FillListSch:
 * порядок колонок менять нельзя, скрипт (web/js/vh-schet.js) правит tr.cells[1] (примечание)
 * и tr.cells[16] (комментарий) после изменения текста, а сортирует по td.dataset.sort.
 *
 * @var array $rows
 */

use yii\helpers\Html;
use yii\helpers\Json;

// Цвет из процедуры: число Access (BGR, 255 = красный) → CSS. 0 = обычный чёрный.
$css = static fn($c) => ($c = (int)$c) ? sprintf('#%02x%02x%02x', $c & 255, ($c >> 8) & 255, ($c >> 16) & 255) : '';

// [поле, заголовок, поле с цветом, поле для сортировки, ширина px, подсказка]
// Ширины — пропорция колонок экрана (таблица растягивается на всю ширину, colresize держит пропорцию):
// «Примечание» и «Комментарий» широкие, служебные колонки узкие.
$cols = [
    ['Hld',       'Холдинг',     null,       null,         70, ''],
    ['Prim',      'Примечание',  null,       null,        270, ''],
    ['NSch',      'Номер счёта', 'Delayed',  null,        130, ''],
    ['DtaSch',    'Дата счёта',  'Delayed2', 'LngDtaSch',  70, ''],
    // Имя поля — Closed: процедура xp_GetAllSch отдаёт колонку с заглавной буквы
    ['Closed',    'Закрыт',      'ColFZ275', null,         60, ''],
    ['Dta',       'Дата',        'OplCol',   'LngDta',     60, ''],
    ['Tme',       'Время',       'ColPth',   null,         60, ''],
    ['InControl', 'К',           null,       null,         26, 'На контроле'],
    ['ClassVl',   'Р',           null,       null,         26, ''],
    ['TP',        'TP',          'TpCol',    null,         62, ''],
    ['usr',       'User',        null,       null,        100, ''],
    ['HowOpl',    'Оплата',      'PlCol',    null,         85, ''],
    ['DtaToOpl',  'Отдано',      'OplPrc',   null,         85, ''],
    ['Mails',     'Писем',       null,       null,         48, ''],
    ['Acts',      'Акты',        null,       null,         48, ''],
    ['Reps',      'Отчет',       null,       null,         52, ''],
    ['Comm',      'Комментарий', null,       null,        340, ''],
];
// Скрытые поля для панели (в Access ListSubItems 13, 15, 17-26)
$hidden = ['PredShcID', 'NSch', 'TP', 'pth', 'OstOPl', 'SmSch', 'WhoPick', 'Mails', 'Diff', 'Acts', 'Reps', 'InUEtxt', 'Comm', 'Prim', 'OplPrc'];

// Что означает цвет текста в столбце (в Access это были подписи над таблицей) — в подсказке заголовка
$colorTips = [
    'Hld'      => 'Красный — разница дат > 2 нед.; синий — забрать полностью; зелёный — забрать частично',
    'NSch'     => 'Красный — задерживаются; жёлтый — проблемные',
    'DtaSch'   => 'Красный — прошла ДП',
    'Closed'   => 'Белый — все пол. / закр к опл; зелёный — ФЗ-275',
    'Dta'      => 'Розовый — письмо отправлено',
    'Tme'      => 'Фиолетовый — нет пути к файлу',
    'HowOpl'   => 'Сиреневый — не отправлено',
    'DtaToOpl' => 'Голубой — отдано в оплату; персиковый — закрыт к оплате',
];
?>
<table id="vhList" class="vh-list-table" style="width:<?= array_sum(array_column($cols, 4)) ?>px">
    <colgroup>
        <?php foreach ($cols as $c): ?><col style="width:<?= $c[4] ?>px"><?php endforeach ?>
    </colgroup>
    <thead>
    <tr>
        <?php foreach ($cols as $i => [$f, $title, , , , $tip]): ?>
            <th data-col="<?= $i ?>" data-field="<?= $f ?>" title="<?= Html::encode(trim(($tip ?: $title) . ($colorTips[$f] ?? '' ? ' · ' . $colorTips[$f] : ''))) ?>"><?= Html::encode($title) ?><span class="rsz"></span></th>
        <?php endforeach ?>
    </tr>
    </thead>
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
