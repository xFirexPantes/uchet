<?php
/**
 * Аналоги текущего элемента — подформа аналогов «Статистики» (subFrmStAnalog).
 *
 * @var array $rows
 * @var array $cols   колонки из StatService::analogColumns()
 * @var string|null $error
 */

use yii\helpers\Html;

if ($error): ?>
    <div class="vh-alert"><?= Html::encode($error) ?></div>
<?php endif ?>
<?php if (!$rows && !$error): ?>
    <div class="empty">Аналогов нет</div>
<?php endif ?>
<?php if ($rows): ?>
    <table class="vh-list" id="stAnList">
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
                    if (is_bool($v)) $v = $v ? 'да' : ''; ?>
                    <td<?= $right ? ' style="text-align:right"' : '' ?>
                        title="<?= Html::encode((string)$v) ?>"><?= Html::encode(is_scalar($v) ? (string)$v : '') ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
