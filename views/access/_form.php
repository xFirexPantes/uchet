<?php
/**
 * Общий рисовальщик формы Access по раскладке из SaveAsText (layout-файл делает gen_card.py).
 * Каждый элемент ставится абсолютно: left/top/width/height = твипы / 15.
 * Пока только просмотр: поля readonly, кнопки неактивны с подсказкой (кроме тех, что переданы в $slots).
 *
 * @var array $layout  элементы из layout-файла
 * @var array $rec     запись формы (RecordSource)
 * @var array $calc    значения вычисляемых и свободных полей: [имя элемента => значение]
 * @var array $combos  поля со списком из процедур: [имя => [значение => текст]]
 * @var array $show    имена скрытых в Access элементов, которые VBA делает видимыми
 * @var array $hide    имена элементов, которые VBA прячет
 * @var array $slots   готовый HTML на место элемента (подформы, списки, работающие кнопки)
 * @var array $icons   [имя кнопки => [надпись, подсказка]] для кнопок с картинкой
 * @var string $note   подсказка для неактивных кнопок
 * @var array $edit   редактируемые элементы: [имя => text|num|date|select|search|check|button|localdate|path|radio].
 *                    radio — для OptionGroup: её переключатели становятся активными и сохраняют поле группы.
 *                    localdate — свободное поле даты (не сохраняется само, его читает кнопка), path — путь только для чтения,
 *                    который очищается клавишей Delete.
 *                    check/button — флажок или кнопка-действие: class="act" data-act="имя". Изменение такого поля
 *                    скрипт формы отправляет на сохранение (data-field = поле записи).
 * @var array|null $state  состояние экрана из правил (modules/…/services/ScreenRules): edit, show, hide, off, labels, locked.
 *                    Дополняет параметры выше. off [имя => почему] — подсказка неактивной кнопки, labels [имя => текст] —
 *                    подписи, которые меняет VBA, locked [имена] — поле видно и редактируемого вида, но неактивно (Enabled = False).
 */
use app\components\AccessFormat as F;
use yii\helpers\Html;

$show ??= []; $hide ??= []; $slots ??= []; $icons ??= []; $calc ??= []; $combos ??= [];
$note ??= 'пока только просмотр';
$edit ??= [];
$state ??= null;
$off = $state['off'] ?? []; $labels = $state['labels'] ?? []; $locked = $state['locked'] ?? [];
if ($state) {
    $edit += $state['edit'] ?? [];
    $show = array_merge($show, $state['show'] ?? []);
    $hide = array_merge($hide, $state['hide'] ?? []);
}

// значение элемента: связанное поле записи или вычисленное в PHP
$val = static function (array $c) use ($rec, $calc) {
    if (array_key_exists($c['n'], $calc)) return $calc[$c['n']];
    $src = $c['src'] ?? '';
    if ($src === '' || $src[0] === '=') return null;
    return $rec[$src] ?? null;
};
// список значений вида 0;"Сокол";1;"ОЭЗ" → [0 => 'Сокол', 1 => 'ОЭЗ']
$valueList = static function (string $rs): array {
    $p = array_map(fn($s) => trim($s, " \"\\"), explode(';', $rs));
    $m = [];
    for ($i = 0; $i + 1 < count($p); $i += 2) $m[$p[$i]] = $p[$i + 1];
    return $m;
};
// группы переключателей: какой группе принадлежит переключатель (по попаданию в рамку)
$groups = array_filter($layout, fn($c) => $c['t'] === 'OptionGroup');
$groupOf = static function (array $o) use ($groups) {
    foreach ($groups as $g) {
        if ($o['L'] >= $g['L'] && $o['L'] <= $g['L'] + $g['W'] && $o['T'] >= $g['T'] && $o['T'] <= $g['T'] + $g['H']) return $g;
    }
    return null;
};

foreach ($layout as $c):
    $n = $c['n'];
    if ((!empty($c['hidden']) && !in_array($n, $show, true)) || in_array($n, $hide, true)) continue;
    $style = "left:{$c['L']}px;top:{$c['T']}px;width:{$c['W']}px;height:{$c['H']}px;" . ($c['st'] ?? '');
    $id = Html::encode($n);

    if (isset($slots[$n])) {   // подформа, список или работающая кнопка
        echo "<div class=\"a-slot\" id=\"box-$id\" style=\"$style\">{$slots[$n]}</div>\n";
        continue;
    }
    switch ($c['t']) {
        case 'Rectangle':
        case 'OptionGroup':
            echo "<div class=\"a-frame\" style=\"$style\"></div>\n";
            break;

        case 'Label':
            echo "<div class=\"a-lbl\" id=\"$id\" style=\"$style\">" . Html::encode($labels[$n] ?? ($c['c'] ?? '')) . "</div>\n";
            break;

        case 'TextBox':
            $v = $val($c);
            $extra = '';
            if (is_array($v)) { $extra = $v['style'] ?? ''; $v = $v['value'] ?? null; }
            $text = F::value($v, $c['fmt'] ?? null, isset($c['dp'], $c['fmt']) ? (int)$c['dp'] : null);
            $cls = 'a-txt' . (!empty($c['transp']) ? ' transp' : '');
            if (isset($edit[$n])) {   // редактируемое поле
                $kind = $edit[$n];
                $field = Html::encode($c['src'] ?? $n);
                $attr = "id=\"$id\" class=\"ed\" data-field=\"$field\" data-kind=\"$kind\"" . (in_array($n, $locked, true) ? ' disabled' : '');
                if ($kind === 'date' || $kind === 'localdate') {
                    $iso = is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : '';
                    $inner = "<input type=\"date\" $attr value=\"$iso\">";
                } else {
                    $inner = "<input type=\"text\" $attr value=\"" . Html::encode($text) . "\"" . ($kind === 'search' ? ' autocomplete="off"' : '')
                        . ($kind === 'path' ? ' readonly title="' . Html::encode($text !== '' ? "$text\nDelete — удалить путь" : '') . '"' : '') . '>';
                }
                echo "<div class=\"$cls edit\" style=\"$style$extra\">$inner</div>\n";
                break;
            }
            $inner = $c['H'] >= 30
                ? '<textarea id="' . $id . '" readonly>' . Html::encode($text) . '</textarea>'
                : '<input type="text" id="' . $id . '" readonly value="' . Html::encode($text) . '" title="' . Html::encode($text) . '">';
            echo "<div class=\"$cls\" style=\"$style$extra\">$inner</div>\n";
            break;

        case 'ComboBox':
            // значение и ключи списка сравниваются как числа, если это числа: SQL Server отдаёт 1, 1.0 или 1.00 в зависимости от типа столбца
            $norm = fn($x) => is_numeric($x) ? (string)(0 + $x) : (string)$x;
            $v = $norm($val($c) ?? '');
            $map = [];
            foreach ($combos[$n] ?? (isset($c['rs']) && !str_starts_with(strtolower($c['rs']), 'exec') ? $valueList($c['rs']) : []) as $k => $t) $map[$norm($k)] = $t;
            $text = $map[$v] ?? $v;
            if (isset($edit[$n])) {   // поле со списком, которое можно менять
                $field = Html::encode($c['src'] ?? $n);
                $opts = '<option value=""></option>';
                foreach ($map as $k => $t) {
                    $opts .= '<option value="' . Html::encode($k) . '"' . ((string)$k === $v ? ' selected' : '') . '>' . Html::encode($t) . '</option>';
                }
                echo "<div class=\"a-txt a-select edit\" style=\"$style\"><select id=\"$id\" class=\"ed\" data-field=\"$field\" data-kind=\"select\"" . (in_array($n, $locked, true) ? ' disabled' : '') . ">$opts</select></div>\n";
                break;
            }
            echo "<div class=\"a-txt a-combo\" style=\"$style\"><input type=\"text\" id=\"$id\" readonly value=\"" . Html::encode($text) . "\" title=\"" . Html::encode($text) . "\"><span>▼</span></div>\n";
            break;

        case 'CheckBox':
            $on = F::bool($val($c));
            if (($edit[$n] ?? '') === 'check') {
                echo "<input type=\"checkbox\" class=\"a-opt act\" id=\"$id\" data-act=\"$id\" " . ($on ? 'checked' : '') . " style=\"$style\">\n";
                break;
            }
            echo "<input type=\"checkbox\" class=\"a-opt\" id=\"$id\" disabled " . ($on ? 'checked' : '') . " style=\"$style\">\n";
            break;

        case 'OptionButton':
            $g = $groupOf($c);
            $gv = $g ? $val($g) : null;
            $on = $g && $gv !== null && (string)(int)$gv === (string)($c['ov'] ?? '');
            if ($g && ($edit[$g['n']] ?? '') === 'radio') {   // группа переключателей, которую можно менять
                $field = Html::encode($g['src'] ?? $g['n']);
                echo "<input type=\"radio\" class=\"a-opt ed\" name=\"grp-" . Html::encode($g['n']) . "\" data-field=\"$field\" data-kind=\"radio\" value=\"" . Html::encode($c['ov'] ?? '') . "\" " . ($on ? 'checked' : '') . " style=\"$style\">\n";
                break;
            }
            echo "<input type=\"radio\" class=\"a-opt\" disabled " . ($on ? 'checked' : '') . " style=\"$style\">\n";
            break;

        case 'CommandButton':
        case 'ToggleButton':
            [$label, $tip] = $icons[$n] ?? [($c['c'] ?? ''), ($c['tip'] ?? ($c['c'] ?? ''))];
            if (($edit[$n] ?? '') === 'button') {
                echo "<button type=\"button\" class=\"a-btn act\" id=\"$id\" data-act=\"$id\" title=\"" . Html::encode($tip) . "\" style=\"$style\">" . Html::encode($label) . "</button>\n";
                break;
            }
            echo "<button type=\"button\" class=\"a-btn\" id=\"$id\" disabled title=\"" . Html::encode("$tip: " . ($off[$n] ?? $note)) . "\" style=\"$style\">" . Html::encode($label) . "</button>\n";
            break;

        default:   // Subform, ListBox, CustomControl без данных: пустая рамка на своём месте
            echo "<div class=\"a-list\" id=\"box-$id\" style=\"$style\"></div>\n";
    }
endforeach;
