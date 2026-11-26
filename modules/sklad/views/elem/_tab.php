<?php
/**
 * Тело подчинённого списка карточки элемента.
 *
 * Рендерится сервером и приходит в JSON из ElemController::actionSub — так же, как строки
 * «Статистики» (stat/_rows.php): форматирование и подписи живут в одном месте.
 *
 * @var string $tab
 * @var array $rows
 * @var array $cols  колонки из ElemService::columns()
 * @var string|null $error
 */

use yii\helpers\Html;

$val = static function ($v): string {
    if ($v === null || $v === '') return '';
    if (is_bool($v)) return $v ? 'да' : '';
    // Даты SQL Server приходят как '2026-11-19 00:00:00.000' — показываем без времени, как в Access
    if (is_string($v) && preg_match('/^(\d{4}-\d{2}-\d{2}) 00:00:00(\.0+)?$/', $v, $m)) return $m[1];
    return (string)$v;
};
?>
<?php if ($error): ?>
    <div class="el-tab-err"><?= Html::encode($error) ?></div>
<?php endif ?>

<?php if (!$rows && !$error): ?>
    <div class="el-tab-empty">Записей нет</div>
<?php elseif ($rows): ?>
    <table class="el-tbl" data-tab="<?= Html::encode($tab) ?>">
        <colgroup>
            <?php foreach ($cols as [$f, , $w]): ?>
                <col<?= $w ? ' style="width:' . (int)$w . 'px"' : '' ?>>
            <?php endforeach ?>
        </colgroup>
        <thead>
        <tr>
            <?php foreach ($cols as [$f, $title]): ?>
                <th title="<?= Html::encode($f) ?>"><?= Html::encode($title) ?></th>
            <?php endforeach ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <?php foreach ($cols as [$f, , , $right]):
                    $v = $val($r[$f] ?? null); ?>
                    <td<?= $right ? ' style="text-align:right"' : '' ?> title="<?= Html::encode($v) ?>"><?= Html::encode($v) ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
