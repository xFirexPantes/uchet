<?php
namespace app\components;

use Yii;

/**
 * Окна выбора из Access (frmfndFirm и подобные): одна форма, много режимов по OpenArgs.
 * Режимы описаны в config/pickers/<форма>.php, экран один на все (web/js/picker.js).
 *
 * Как добавить режим: найти в VBA формы Form_Open (Case N: заголовок, подпись), FillList (Case N: процедура
 * и параметры), xList_DblClick/Accpt_Click (что возвращается), и дописать элемент в config/pickers/<форма>.php.
 *
 * Поля режима:
 *   title   — Me.Caption;  label — Me!Nadp.Caption (подпись у поля поиска)
 *   proc    — процедура; ей передаётся строка поиска + '%' (как tmp = "'" + st + "%'")
 *   args    — ещё параметры процедуры после строки поиска, например [0] для «exec X 'st%',0»
 *   minLen  — короче этого (но не пусто) не ищем (UB в FillList)
 *   cols    — [[поле, заголовок, ширина px]]; первая колонка = текст строки
 *   key     — поле-ключ (litem.Key = "k" & UchetID), его и возвращает выбор
 *   (в cols и key вместо имени поля можно дать номер столбца с 0 — как Column(N) у списка Access,
 *    когда имена столбцов процедуры не известны; BoundColumn = N+1)
 *   noHead  — у списка нет заголовков (ColumnHeads = Нет)
 *   free    — текст вопроса, если в списке ничего не нашлось, а введённое можно взять как есть
 *             (fnd_AfterUpdate: «… нет в списке. Всё равно использовать?»), тогда key = text = строка поиска
 */
class Picker
{
    /**
     * Режимы лежат по файлу на форму: config/pickers/<форма>.php → [OpenArgs => описание].
     * У frmfndPR около 200 режимов, поэтому так, а не одним списком здесь.
     */
    public static function mode(string $form, int $mode): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $form)) {
            return null;
        }
        $file = Yii::getAlias("@app/config/pickers/$form.php");
        if (!is_file($file)) {
            return null;
        }
        static $cache = [];
        $cache[$form] ??= require $file;
        return $cache[$form][$mode] ?? null;
    }

    /** Строки списка для строки поиска. */
    public static function rows(array $m, string $st): array
    {
        if ($st !== '' && mb_strlen($st) < ($m['minLen'] ?? 0)) {
            return [];
        }
        $vals = array_merge([$st . '%'], array_values($m['args'] ?? []));
        $bind = [];
        foreach ($vals as $i => $v) $bind[$i + 1] = $v;
        $rows = Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC ' . $m['proc'] . ' ' . implode(', ', array_fill(0, count($vals), '?')), $bind)->queryAll();
        $out = [];
        foreach ($rows as $r) {
            $byNum = array_values($r);
            $get = fn($f) => is_int($f) ? ($byNum[$f] ?? null) : ($r[$f] ?? null);
            $row = ['key' => (string)($get($m['key']) ?? '')];
            foreach ($m['cols'] as [$f]) {
                $row[$f] = AccessFormat::value($get($f));
            }
            $out[] = $row;
        }
        return $out;
    }
}
