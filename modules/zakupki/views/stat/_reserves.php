<?php
/**
 * Резервы текущего элемента — подформа SubMinimFilter формы frmSt.
 *
 * Источник: exec dbo.xp_SubMinimumFilter @txtID, @Rgm.
 * В Access колонка «В» вычисляемая (=IIf([IsExactDate]=True,"!","")), поэтому
 * её значение готовит StatService::reserves().
 *
 * Условное форматирование формы (ConditionalFormat14 у «Название темы» и «Пробл»):
 * [DtaPost] > Date() — пурпурный фон, [DtaPost] = Date() — оливковый.
 *
 * @var array $rows
 * @var array $cols  колонки из StatColumns::reserveColumns()
 * @var string|null $error
 */

use yii\helpers\Html;

$today = date('Y-m-d');

if ($error): ?>
    <div class="vh-alert"><?= Html::encode($error) ?></div>
<?php endif ?>
<?php if (!$rows && !$error): ?>
    <div class="vh-stub">Резервов нет</div>
<?php endif ?>
<?php if ($rows): ?>
    <table class="vh-list" id="stResList">
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
        <?php foreach ($rows as $r):
            // DtaPost приходит датой; сравниваем только день, как Date() в Access
            $dta = substr((string)($r['DtaPost'] ?? ''), 0, 10);
            $day = $dta === '' ? '' : ($dta > $today ? 'res-future' : ($dta === $today ? 'res-today' : '')); ?>
            <tr>
                <?php foreach ($cols as [$f, , , $right]):
                    $v = $r[$f] ?? '';
                    if (is_bool($v)) $v = $v ? 'да' : '';
                    $style = $right ? 'text-align:right' : '';
                    // В Access «В» и «Пробл» красные (ForeColor = 255), «Пробл» ещё и жирный
                    if (in_array($f, ['В', 'IsGuilty'], true) && (string)$v === '!') {
                        $style .= ($style !== '' ? ';' : '') . 'color:#c00;font-weight:700';
                    }
                    // Подсветка по сроку — только у колонок с условным форматированием
                    $cls = in_array($f, ['Название темы', 'IsGuilty'], true) ? $day : ''; ?>
                    <td<?= $cls !== '' ? ' class="' . $cls . '"' : '' ?><?= $style !== '' ? ' style="' . $style . '"' : '' ?>
                        title="<?= Html::encode((string)$v) ?>"><?= Html::encode(is_scalar($v) ? (string)$v : '') ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
