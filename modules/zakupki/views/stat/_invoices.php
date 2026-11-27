<?php
/**
 * Предложения поставщиков по текущему элементу — подформа SubFrmInvoices (Invoices)
 * формы frmSt.
 *
 * Источник: exec dbo.xp_GetInvoices1 @SkladID, @Numtbl, @IDRes, @Tpe, @DtaTxt.
 * В frmSt подформа только для просмотра (blck = True), поэтому галки «В расчёт»,
 * «Активный» и «Проект» нарисованы неактивными.
 *
 * Условное форматирование NumTbl (ConditionalFormat14 подформы): [IsCurTbl]=1 —
 * светло-зелёный фон #ccffcc, то есть строка из текущей сводной таблицы.
 *
 * @var array $rows
 * @var array $cols  колонки из InvoiceColumns::columns()
 * @var string|null $error
 */

use yii\helpers\Html;

/** Дата Access dd.mm.yy из 'YYYY-MM-DD hh:mm:ss'. */
$dmy = static function ($v): string {
    $s = substr((string)$v, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)
        ? substr($s, 8, 2) . '.' . substr($s, 5, 2) . '.' . substr($s, 2, 2)
        : (string)$v;
};

/** Число как General Number: 1 234,5 без лишних нулей. */
$num = static function ($v): string {
    if (!is_numeric($v)) {
        return (string)$v;
    }
    $s = number_format((float)$v, 2, ',', ' ');
    $s = rtrim(rtrim($s, '0'), ',');
    return $s === '' || $s === '-' ? '0' : $s;
};

if ($error): ?>
    <div class="vh-alert"><?= Html::encode($error) ?></div>
<?php endif ?>
<?php if (!$rows && !$error): ?>
    <div class="vh-stub">Предложений поставщиков нет</div>
<?php endif ?>
<?php if ($rows): ?>
    <table class="vh-list" id="stInvList">
        <colgroup>
            <?php foreach ($cols as [$f, , $w]): ?>
                <col<?= $w ? ' style="width:' . (int)$w . 'px"' : '' ?>>
            <?php endforeach ?>
        </colgroup>
        <thead>
        <tr>
            <?php foreach ($cols as [, $title]): ?>
                <th><?= Html::encode($title) ?></th>
            <?php endforeach ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr data-id="<?= (int)($r['ID'] ?? 0) ?>">
                <?php foreach ($cols as [$f, , , $right]):
                    $v = $r[$f] ?? '';
                    $style = $right ? 'text-align:right' : '';
                    $text = '';

                    if (in_array($f, ['InPivot', 'AnalogActive'], true)) {
                        // CheckBox в Access: значение 0/1
                        $on = !empty($v);
                        $text = '<input type="checkbox" class="vh-chk" disabled' . ($on ? ' checked' : '') . '>';
                    } elseif ($f === 'ProjectPrice') {
                        // CheckBox с источником =[ProjectPrice]>0
                        $on = (float)$v > 0;
                        $text = '<input type="checkbox" class="vh-chk" disabled' . ($on ? ' checked' : '') . '>';
                    } elseif (in_array($f, ['DtaAdv', 'DC'], true)) {
                        $text = Html::encode($dmy($v));
                    } elseif ($right) {
                        $text = Html::encode($num($v));
                    } else {
                        $text = Html::encode(is_scalar($v) ? (string)$v : '');
                    }

                    // [IsCurTbl]=1 — строка текущей сводной таблицы (BackColor 0xccffcc)
                    if ($f === 'NumTbl' && !empty($r['IsCurTbl'])) {
                        $style .= ($style !== '' ? ';' : '') . 'background:#ccffcc';
                    }
                    ?>
                    <td<?= $style !== '' ? ' style="' . $style . '"' : '' ?>
                        title="<?= Html::encode(is_scalar($v) ? (string)$v : '') ?>"><?= $text ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
