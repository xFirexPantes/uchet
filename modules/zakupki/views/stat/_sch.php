<?php
/**
 * Счета элемента — подформа s (SubFrmStatSch) формы frmSt.
 *
 * Источник: exec dbo.xp_SubFrmStatSch @txtID, @Rgm. В виде b процедура отдаёт историю
 * элемента (xp_HistoryElem: приход и оплаты, сроки, гарантия), в остальных видах —
 * открытые позиции счетов (Количество − пришло > 0).
 *
 * Кнопка ShowSch подформы («показать счёт») пока не перенесена — обработчик не разобран.
 * data-id на строке — PredShcID позиции счёта (в виде b такого поля в наборе нет).
 *
 * @var array $rows
 * @var array $cols    колонки из StatColumns::schColumns()
 * @var string $empty   текст для пустого списка
 * @var string|null $error
 */

use app\modules\zakupki\services\StatColumns;
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

$empty = $empty ?? 'Записей нет';

if ($error): ?>
    <div class="vh-alert"><?= Html::encode($error) ?></div>
<?php endif ?>
<?php if (!$rows && !$error): ?>
    <div class="vh-stub"><?= Html::encode($empty) ?></div>
<?php endif ?>
<?php if ($rows): ?>
    <table class="vh-list" id="stSchList">
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
            <tr data-id="<?= (int)($r['PredShcID'] ?? 0) ?>">
                <?php foreach ($cols as [$f, , , $right]):
                    $v = $r[$f] ?? '';
                    if (is_bool($v)) $v = $v ? 'да' : '';
                    $text = in_array($f, StatColumns::SCH_DATES, true)
                        ? Html::encode($dmy($v))
                        : Html::encode($right ? $num($v) : (is_scalar($v) ? (string)$v : ''));
                    ?>
                    <td<?= $right ? ' style="text-align:right"' : '' ?>
                        title="<?= Html::encode(is_scalar($v) ? (string)$v : '') ?>"><?= $text ?></td>
                <?php endforeach ?>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
