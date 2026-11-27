<?php
/**
 * Заказы текущего элемента с отметкой об ознакомлении — список LstZakasTrack главной формы frmSt.
 *
 * Источник: exec dbo.xp_SkladTrackingZakLst @SkladID. Список показывается только в виде b
 * (fnRepaintFormStat); в Access он стоит справа от подформы s.
 *
 * Двойной щелчок по строке (LstZakasTrack_DblClick) ставит или снимает отметку — обработчик
 * в index.php. data-order — «Заказ», data-ozn — текущее значение «Ознакомлен» («да»/«нет»).
 *
 * @var array $rows
 * @var array $cols    колонки из StatColumns::trackColumns()
 * @var string|null $error
 */

use yii\helpers\Html;

if ($error): ?>
    <div class="vh-alert"><?= Html::encode($error) ?></div>
<?php endif ?>
<?php if (!$rows && !$error): ?>
    <div class="vh-stub">Заказов нет</div>
<?php endif ?>
<?php if ($rows): ?>
    <table class="vh-list" id="stTrackList">
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
            <tr data-order="<?= Html::encode((string)($r['Заказ'] ?? '')) ?>"
                data-ozn="<?= Html::encode((string)($r['Ознакомлен'] ?? '')) ?>">
                <?php foreach ($cols as [$f, , , $right]):
                    $v = $r[$f] ?? '';
                    if (is_bool($v)) $v = $v ? 'да' : '';
                    $text = is_scalar($v) ? (string)$v : '';
                    ?>
                    <td<?= $right ? ' style="text-align:right"' : '' ?>
                        title="<?= Html::encode($text) ?>"><?= Html::encode($text) ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
