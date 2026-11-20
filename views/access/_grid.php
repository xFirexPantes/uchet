<?php
/**
 * Таблица на месте подформы-таблицы (DefaultView = 2) или списка Access.
 * Столбцы раздвигаются мышью (web/js/colresize.js), ширины запоминаются по $key.
 *
 * @var string $tid   id таблицы
 * @var string $key   ключ для запоминания ширин
 * @var array  $cols  [[поле, заголовок, ширина px, опции]]; опции: al (r|c), fmt, dp, chk, cls (класс столбца),
 *                    cf (function(array $row): string — CSS ячейки, условное форматирование)
 * @var array  $rows
 * @var string|null $idField  поле, которое кладётся в data-id строки
 * @var array  $dataFields    поля, которые кладутся в data-row (JSON) для скриптов
 */
use app\components\AccessFormat as F;
use yii\helpers\Html;
use yii\helpers\Json;

$idField ??= null; $dataFields ??= [];
?>
<table id="<?= Html::encode($tid) ?>" class="a-grid" data-key="<?= Html::encode($key) ?>">
    <colgroup>
        <?php foreach ($cols as $c): ?><col class="<?= Html::encode($c[3]['cls'] ?? '') ?>" style="width:<?= (int)$c[2] ?>px"><?php endforeach ?>
    </colgroup>
    <thead><tr>
        <?php foreach ($cols as $c): ?>
            <th class="<?= Html::encode($c[3]['cls'] ?? '') ?>" title="<?= Html::encode($c[1]) ?>"><?= Html::encode($c[1]) ?><span class="rsz"></span></th>
        <?php endforeach ?>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr<?= $idField ? ' data-id="' . Html::encode($r[$idField] ?? '') . '"' : '' ?><?= $dataFields ? ' data-row="' . Html::encode(Json::encode(array_intersect_key($r, array_flip($dataFields)))) . '"' : '' ?>>
        <?php foreach ($cols as [$f, , , $o]):
            $v = $r[$f] ?? null;
            $st = match ($o['al'] ?? '') { 'r' => 'text-align:right;', 'c' => 'text-align:center;', default => '' };
            if (isset($o['cf'])) $st .= $o['cf']($r);
            $cls = $o['cls'] ?? '';
            if (!empty($o['chk'])): ?>
                <td class="<?= $cls ?>" style="text-align:center;<?= $st ?>"><input type="checkbox" disabled <?= F::bool($v) ? 'checked' : '' ?>></td>
            <?php else:
                $t = F::value($v, $o['fmt'] ?? null, isset($o['dp']) ? (int)$o['dp'] : null); ?>
                <td class="<?= $cls ?>" style="<?= $st ?>" title="<?= Html::encode($t) ?>"><?= Html::encode($t) ?></td>
            <?php endif;
        endforeach ?>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
