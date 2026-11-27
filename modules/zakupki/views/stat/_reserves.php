<?php
/**
 * Резервы текущего элемента — подформа SubMinimFilter формы frmSt.
 *
 * Источник: exec dbo.xp_SubMinimumFilter @txtID, @Rgm.
 * В Access колонка «В» вычисляемая (=IIf([IsExactDate]=True,"!","")), поэтому
 * её значение готовит StatService::reserves().
 *
 * @var array $rows
 * @var array $cols  колонки из StatColumns::reserveColumns()
 * @var string|null $error
 */

use yii\helpers\Html;

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
        <?php foreach ($rows as $r): ?>
            <tr>
                <?php foreach ($cols as [$f, , , $right]):
                    $v = $r[$f] ?? '';
                    if (is_bool($v)) $v = $v ? 'да' : '';
                    // В Access «В» и «Пробл» красные (ForeColor = 255), «Пробл» ещё и жирный
                    $mark  = in_array($f, ['В', 'IsGuilty'], true) && (string)$v === '!';
                    $style = $right ? 'text-align:right' : '';
                    if ($mark) $style .= ($style !== '' ? ';' : '') . 'color:#c00;font-weight:700'; ?>
                    <td<?= $style !== '' ? ' style="' . $style . '"' : '' ?>
                        title="<?= Html::encode((string)$v) ?>"><?= Html::encode(is_scalar($v) ? (string)$v : '') ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
