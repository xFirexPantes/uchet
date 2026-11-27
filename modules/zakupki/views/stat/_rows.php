<?php
/**
 * Строки списка «Статистики».
 *
 * Рендерится и на странице (index.php), и при автообновлении по таймеру (StatController::actionRows),
 * чтобы условное форматирование жило в одном месте.
 *
 * Раскраска — fnRepaintFormStat формы frmSt. Цвета Access заданы в BGR, поэтому
 * переводятся в CSS через $css(): 16737945 → #9966ff и т. д.
 *
 * @var array  $rows
 * @var array  $cols   колонки из StatService::columns()
 * @var float  $bnd    граница цены (PriceBound) в валюте вида; 0 — не задана
 * @var string $empty  текст для пустого списка
 * @var string $vid    вид списка: часть правил есть только в виде 1 или только в виде b
 */

use yii\helpers\Html;

// Цвет Access (BGR, 255 = красный) → CSS. 0 = обычный чёрный.
$css = static fn($c) => ($c = (int)$c) ? sprintf('#%02x%02x%02x', $c & 255, ($c >> 8) & 255, ($c >> 16) & 255) : '';
$bnd = (float)($bnd ?? 0);
$vid = (string)($vid ?? '');

if (!$rows): ?>
    <tr>
        <td class="empty" colspan="<?= max(1, count($cols)) ?>"><?= Html::encode($empty) ?></td>
    </tr>
<?php endif ?>
<?php foreach ($rows as $r):
    // reqSchColor: зелёный фон, когда стоит «Запрос счёта» (ConditionalFormat14 в frmMinimum)
    $rowBg = !empty($r['RequestSch']) ? '#b1ffb1' : '';
    // Код — по нему Form_Timer возвращается к текущей записи после перезапроса
    $code = isset($r['Код']) && is_scalar($r['Код']) ? (string)$r['Код'] : '';
    $sost = (int)($r['SostProblEl'] ?? 0);
    $isB  = $vid === 'b'; ?>
    <tr<?= $rowBg ? ' style="background:' . $rowBg . '"' : '' ?><?= $code !== '' ? ' data-code="' . Html::encode($code) . '"' : '' ?>>
        <?php foreach ($cols as [$f, , , $right]):
            $style = [];
            $c     = '';   // цвет текста
            $bold  = false;

            // --- Общие правила fnRepaintFormStat (ветка f = False, т.е. все виды кроме b) ---
            if (!$isB) {
                if ($f === 'Analog' && !empty($r['NoAnalog'])) {
                    $style[] = 'background:' . $css(16761358);          // [NoAnalog] = true
                } elseif ($f === 'Корпус' && !empty($r['IsDelayed'])) {
                    $style[] = 'background:' . $css(16737945);          // [IsDelayed] = true
                } elseif ($f === 'ГдеКупил') {
                    $mv = (int)($r['ExistMove'] ?? 0);
                    if ($mv === 1)      $style[] = 'background:' . $css(16777164);   // [ExistMove] = 1
                    elseif ($mv === 2)  $style[] = 'background:' . $css(13952764);   // [ExistMove] = 2
                } elseif ($f === 'Количество' && (int)($r['ProblKol'] ?? 0) > 0) {
                    $style[] = 'background:' . $css(65535);             // [ProblKol] > 0 — vbYellow
                } elseif (($f === 'txtSV' || $f === 'OrderEls') && (float)($r[$f] ?? 0) > 0) {
                    $style[] = 'background:' . $css(255);               // txtSV > 0 — vbRed
                    $bold = true;
                } elseif ($f === 'Код' && (int)($r['TakeMe4St'] ?? 0) === 1) {
                    $c = $css(16711680);                                // [TakeMe4St] = 1 — vbBlue
                    $bold = true;
                } elseif ($f === 'LowR' && $bnd > 0 && (float)($r[$f] ?? 0) >= $bnd) {
                    $c = $css(16711680);                                // LowR >= PriceBound — vbBlue
                    $bold = true;
                }
            } elseif ($f === 'LowR' && (int)($r['NeedOzn'] ?? 0) === 1) {
                // Вид b: единственное правило — LowR при [NeedOzn] = 1 (vbRed)
                $c = $css(255);
                $bold = true;
            }

            // --- Вид 1: состояние проблемы в «Изготовитель» и недополученное ---
            if ($vid === '1') {
                if ($f === 'Изготовитель') {
                    if ($sost === 2)      $style[] = 'background:' . $css(13434828);
                    elseif ($sost === 3)  $style[] = 'background:' . $css(10092543);
                    elseif ($sost === 4)  $style[] = 'background:' . $css(16764052);
                } elseif ($f === 'Недополучено') {
                    if ((float)($r[$f] ?? 0) > 0) $bold = true;         // > 0 — жирный
                    if ((int)($r['MainPlace'] ?? 1) === 0) {
                        $style[] = 'background:' . $css(13434828);      // [MainPlace] = 0
                    }
                }
            }

            // --- Правила подформы frmMinimum (ConditionalFormat колонок) ---
            if ($f === 'Кратко') {
                if ($sost === 5)                          $c = $css(0x993366);
                if ((int)($r['NotRec'] ?? 0) !== 0)       $c = $css(0xff00ff);
            } elseif ($f === 'Кол-во') {
                $ib = (int)($r['IsBoth'] ?? 0);
                if ($ib === 4)      $style[] = 'background:' . $css(0x99ccff);
                elseif ($ib === 1)  $style[] = 'background:' . $css(0xccffff);
                elseif ((float)($r[$f] ?? 0) < 0) $style[] = 'background:' . $css(0xffcc99);
            } elseif ($f === 'Примечание' && (int)($r['DfltIsp'] ?? 0) === 1) {
                $style[] = 'background:' . $css(0xccffff);
            }

            if ($c !== '') $style[] = "color:$c";
            if ($bold)     $style[] = 'font-weight:bold';
            if ($right)    $style[] = 'text-align:right';

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
