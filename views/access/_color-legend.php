<?php
/**
 * Легенда цветов подформы: кнопка «? Цвета» и окно с расшифровкой.
 *
 * В Access расшифровка цветов была подписями над таблицей; в вебе — отдельная кнопка
 * в шапке панели, как «Цвета в списке счетов» (vh-schet).
 *
 * Условия и цвета берутся из VBA (ConditionalFormat14 / обработчики формы), а не
 * придумываются: каждый блок — колонка подформы, каждая строка — одно правило.
 *
 * Пример:
 *   <?= $this->render('@app/views/access/_color-legend', [
 *       'id'    => 'posColors',
 *       'title' => 'Цвета в позициях счёта',
 *       'note'  => 'Цвет фона или текста в столбце подсказывает состояние позиции.',
 *       'btn'   => 'a-btn',            // класс кнопки: a-btn в карточке, vh-btn в экранах
 *       'blocks' => [
 *           ['t' => 'Наименование', 'rows' => [
 *               ['sw' => '#a7da4e', 'text' => 'по позиции уже есть накладная'],
 *           ]],
 *       ],
 *   ]) ?>
 *
 * Строка: sw — цвет плашки (CSS-цвет) либо text — символ вместо плашки
 * (например «!» красным); cls — дополнительные классы текста (cl-b — жирный).
 *
 * @var string $id     уникальный id окна (он же в data-cl-open кнопки)
 * @var string $title  заголовок окна и подсказка кнопки
 * @var string $note   пояснение под заголовком
 * @var string $btn    класс кнопки; пусто — кнопку не выводить
 * @var array  $blocks [['t' => заголовок, 'rows' => [['sw' => цвет, 'text' => ...], ...]], ...]
 */

use yii\helpers\Html;

$this->registerCssFile('@web/css/color-legend.css');
$this->registerJsFile('@web/js/color-legend.js');

$id = (string)$id;
$btn = (string)($btn ?? 'cl-btn');
?>
<?php if ($btn !== ''): ?>
    <button type="button" class="<?= Html::encode($btn) ?>" data-cl-open="<?= Html::encode($id) ?>"
            title="<?= Html::encode($title) ?>">? Цвета</button>
<?php endif ?>
<div class="cl-modal" id="<?= Html::encode($id) ?>">
    <div class="cl-box" role="dialog" aria-modal="true" aria-labelledby="<?= Html::encode($id) ?>T">
        <div class="cl-head">
            <span id="<?= Html::encode($id) ?>T"><?= Html::encode($title) ?></span>
            <button type="button" class="cl-x" data-cl-x title="Закрыть (Esc)">✕</button>
        </div>
        <div class="cl-body">
            <?php if (!empty($note)): ?>
                <p class="cl-note"><?= Html::encode($note) ?></p>
            <?php endif ?>
            <?php foreach ($blocks as $b): ?>
                <div class="cl-group">
                    <div class="cl-t"><?= Html::encode($b['t'] ?? '') ?></div>
                    <?php foreach ($b['rows'] ?? [] as $r): ?>
                        <div class="cl-row">
                            <?php if (isset($r['text'])): ?>
                                <span class="cl-sw-text"<?= !empty($r['color']) ? ' style="color:' . Html::encode($r['color']) . '"' : '' ?>><?= Html::encode($r['text']) ?></span>
                            <?php else: ?>
                                <span class="cl-sw"<?= !empty($r['sw']) ? ' style="background:' . Html::encode($r['sw']) . '"' : '' ?>></span>
                            <?php endif ?>
                            <span class="cl-txt<?= !empty($r['cls']) ? ' ' . Html::encode($r['cls']) : '' ?>"><?= Html::encode($r['label'] ?? '') ?></span>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>
</div>
