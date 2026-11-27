<?php
/**
 * Строки списка «Статистики».
 *
 * Рендерится и на странице (index.php), и при автообновлении по таймеру (StatController::actionRows),
 * чтобы условное форматирование жило в одном месте.
 *
 * @var array $rows
 * @var array $cols   колонки из StatService::columns()
 * @var float $bnd    граница цены (PriceBound) в валюте вида; 0 — не задана
 * @var string $empty текст для пустого списка
 */

use yii\helpers\Html;

// Цвет Access (BGR, 255 = красный) → CSS. 0 = обычный чёрный.
$css = static fn($c) => ($c = (int)$c) ? sprintf('#%02x%02x%02x', $c & 255, ($c >> 8) & 255, ($c >> 16) & 255) : '';
$bnd = (float)($bnd ?? 0);

if (!$rows): ?>
    <tr>
        <td class="empty" colspan="<?= max(1, count($cols)) ?>"><?= Html::encode($empty) ?></td>
    </tr>
<?php endif ?>
<?php foreach ($rows as $r):
    // reqSchColor: зелёный фон, когда стоит «Запрос счёта» (ConditionalFormat14 в frmMinimum)
    $rowBg = !empty($r['RequestSch']) ? '#b1ffb1' : '';
    // Код — по нему Form_Timer возвращается к текущей записи после перезапроса
    $code = isset($r['Код']) && is_scalar($r['Код']) ? (string)$r['Код'] : ''; ?>
    <tr<?= $rowBg ? ' style="background:' . $rowBg . '"' : '' ?><?= $code !== '' ? ' data-code="' . Html::encode($code) . '"' : '' ?>>
        <?php foreach ($cols as [$f, , , $right]):
            $style = [];
            // Условное форматирование frmMinimum (цвета Access → CSS)
            $c = '';
            if ($f === 'Кратко') {
                if ((int)($r['SostProblEl'] ?? 0) === 5) $c = $css(0x993366);
                if ((int)($r['NotRec'] ?? 0) !== 0)      $c = $css(0xff00ff);
            } elseif ($f === 'Кол-во') {
                $ib = (int)($r['IsBoth'] ?? 0);
                if ($ib === 4)      $style[] = 'background:' . $css(0x99ccff);
                elseif ($ib === 1)  $style[] = 'background:' . $css(0xccffff);
                elseif ((float)($r[$f] ?? 0) < 0) $style[] = 'background:' . $css(0xffcc99);
            } elseif ($f === 'Примечание' && (int)($r['DfltIsp'] ?? 0) === 1) {
                $style[] = 'background:' . $css(0xccffff);
            } elseif ($f === 'Код' && (int)($r['TakeMe4St'] ?? 0) === 1) {
                $style[] = 'background:' . $css(0x00ff00);
            } elseif ($f === 'LowR' && $bnd > 0 && (float)($r[$f] ?? 0) >= $bnd) {
                // fnRepaintFormStat: LowR >= PriceBound → синий жирный (цена дошла до границы)
                $c = $css(0xff0000);
            }
            if ($c) $style[] = "color:$c;font-weight:bold";
            if ($right) $style[] = 'text-align:right';
            $v = $r[$f] ?? '';
            if (is_bool($v)) $v = $v ? 'да' : '';
            ?>
            <?php if ($f === 'RequestSch'): /* CheckBox формы: ControlSource = RequestSch */ ?>
                <td data-f="RequestSch" style="text-align:center">
                    <input type="checkbox" class="vh-chk" data-code="<?= Html::encode($code) ?>"
                           title="Запросить счёт"<?= (int)($r['RequestSch'] ?? 0) !== 0 ? ' checked' : '' ?>>
                </td>
            <?php else: ?>
            <td data-f="<?= Html::encode($f) ?>"<?= $style ? ' style="' . implode(';', $style) . '"' : '' ?>
                title="<?= Html::encode((string)$v) ?>"><?= Html::encode(is_scalar($v) ? (string)$v : '') ?></td>
            <?php endif ?>
        <?php endforeach ?>
    </tr>
<?php endforeach ?>
