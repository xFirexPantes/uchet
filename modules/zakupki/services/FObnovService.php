<?php
namespace app\modules\zakupki\services;

use app\components\AccessFormat as F;
use app\services\LegacySp;
use Yii;

/**
 * Полный поиск компонента fObnov («Склад»): дерево слева, список справа, поиск по строке и маркировке.
 * Перенесены два вызова: ＋ в карточке счёта на элементы (frmMassInputSch, ответ «Нет» на «облегчённую версию»)
 * и двойной щелчок по «Номенклатуре» в позиции счёта на детали (frmScetaDetalAdd). Остальные вызовы fObnov
 * (резервы, 1С, давальческое, прайсы поставщиков, карточка элемента) не переносились.
 *
 * Состояние окна ($s) приходит от клиента: TypeSel, IsShow, grpCompany, lstPrin, флажки IsEFind, IsVP5, NoAnalog, Nelikvid, IsPG.
 * NoAssign («Не определены для 1С») в этих вызовах скрыт и всегда 0.
 */
class FObnovService
{
    /** Уровни дерева (LstNme в xTree_NodeClick). Корень «Склад» → NSh → … → Изготовитель. */
    public const LEVELS = ['NSh', 'Элемент', 'Серия', 'Наименование', 'Номинал', 'Корпус', 'Изготовитель'];

    /** Столбцы xList (ListView): поле, заголовок, ширина px, выравнивание. Заголовки в выгрузке формы не видны — названы по полям. */
    public const COLS = [
        ['Ser', 'Серия', 100], ['Naim', 'Наименование', 140], ['Nom', 'Номинал', 80], ['Krp', 'Корпус', 70], ['Izg', 'Изготовитель', 90],
        ['FullNmeIzg', 'Полное наименование', 150], ['Количество', 'Кол-во', 60, 'r'], ['Minimum', 'Мин.', 50, 'r'],
        ['ЦенаОптовая', 'Цена опт.', 65, 'r'], ['ЦенаРозничная', 'Цена розн.', 65, 'r'], ['Стеллаж', 'Стеллаж', 27], ['Shelf', 'Полка', 27],
        ['НомерЯчейки', 'Ячейка', 47], ['Kat', 'Кат', 47], ['IsVP5', 'ВП5', 35], ['IsNoAnalog', 'Без аналогов', 40], ['PG', 'ПГ', 30],
    ];

    /** Rgm по TypeSel (FillTree2 / xTree_NodeClick). 3 — NoAssign (0). 4 «Склады поставщиков» не перенесён. */
    public static function rgm(array $s): int
    {
        $t = (int)($s['TypeSel'] ?? 1);
        return match ($t) { 1 => 2, 2 => 3, 3 => 0, default => $t };
    }

    /** strflt = IsEFind IsVP5 NoAnalog Nelikvid IsPG, по символу 0/1. */
    public static function strflt(array $s): string
    {
        $o = '';
        foreach (['IsEFind', 'IsVP5', 'NoAnalog', 'Nelikvid', 'IsPG'] as $f) $o .= !empty($s[$f]) && $s[$f] !== '0' ? '1' : '0';
        return $o;
    }

    /** FillTree2: узлы первого уровня под «Склад» — xp_Sklad4TreeViewHeadersNew Rgm, IsShow, 0, strflt, grpCompany, lstPrin. */
    public function headers(array $s): array
    {
        $rows = $this->exec('dbo.xp_Sklad4TreeViewHeadersNew',
            [self::rgm($s), (int)($s['IsShow'] ?? 0), 0, self::strflt($s), (int)($s['grpCompany'] ?? 2), (int)($s['lstPrin'] ?? 0)])->queryAll(\PDO::FETCH_NUM);
        return array_values(array_filter(array_map(fn($r) => $r[0], $rows), fn($v) => $v !== null));
    }

    /**
     * xTree_NodeClick: дочерние узлы по пути (значения уровней после «Склад»).
     * xp_Sklad4TreeViewNew Rgm, IsShow, strflt, " and NSh='…' and Элемент='…'", UB (UB = число уровней в пути).
     * Возвращает [[text, hasKids]]; на 7-м уровне (Изготовитель) детей нет.
     */
    public function children(array $s, array $path): array
    {
        $path = array_values(array_map('strval', $path));
        $ub = count($path);
        if ($ub === 0) return array_map(fn($v) => [$v, true], $this->headers($s));
        if ($ub >= count(self::LEVELS)) return [];
        $where = '';
        foreach ($path as $i => $v) $where .= ' and ' . self::LEVELS[$i] . "='" . str_replace("'", "''", $v) . "'";
        $rows = $this->exec('dbo.xp_Sklad4TreeViewNew', [self::rgm($s), (int)($s['IsShow'] ?? 0), self::strflt($s), $where, $ub])->queryAll(\PDO::FETCH_NUM);
        $out = [];
        foreach ($rows as $r) if ($r[0] !== null) $out[] = [(string)$r[0], $ub < 6];
        return $out;
    }

    /**
     * FillList: список по выбранному узлу. xp_Sklad4ListView2Shelf IsShow, NoAssign, NEl, strflt, 'ур1', 'ур2', …
     * NEl = 1 (ключи узлов «Склад\…»), -2 при «Вывести все». Пустой путь (корень) — '-1', пустой список.
     */
    public function list(array $s, array $path, bool $showAll): array
    {
        $path = array_values(array_map('strval', $path)) ?: ['-1'];
        $rows = $this->exec('dbo.xp_Sklad4ListView2Shelf', array_merge([(int)($s['IsShow'] ?? 0), 0, $showAll ? -2 : 1, self::strflt($s)], $path))->queryAll();
        return array_map([$this, 'row'], $rows);
    }

    /**
     * FillListNew: поиск. Строка вида «x$y$z$w» (4 части, «$» не раньше 4-го символа) — поиск упаковки «--Upak--y».
     * xp_FindElem s, Rgm = NoAssign, Show = IsShow, Dv = TypeSel, flt = strflt, IsFullNmeIzg = 0, Prin = lstPrin, mark, Company = grpCompany.
     * Одна найденная строка — она выбирается (SkladID), а для упаковки сразу «выбрать» (xList_DblClick).
     */
    public function find(array $s, string $st, string $mark): array
    {
        $upak = false;
        if ($st !== '' && mb_strpos($st, '$', 3) !== false) {
            $p = explode('$', $st);
            if (count($p) === 4) { $st = '--Upak--' . $p[1]; $upak = true; }
        }
        $rows = $this->exec('dbo.xp_FindElem', [mb_substr($st, 0, 100), 0, (int)($s['IsShow'] ?? 0), (int)($s['TypeSel'] ?? 1),
            self::strflt($s), 0, (int)($s['lstPrin'] ?? 0), mb_substr($mark, 0, 255), (int)($s['grpCompany'] ?? 2)])->queryAll();
        $rows = array_map([$this, 'row'], $rows);
        $res = ['rows' => $rows];
        if (count($rows) === 1) {
            $res['single'] = $rows[0]['id'];
            if ($upak) $res['upak'] = true;
        }
        return $res;
    }

    /** xTree4KodnullEl: путь к элементу в дереве — xp_Sklad4TreeViewNewCurrentCode ID (значения 7 уровней). */
    public function path(int $sklad): array
    {
        $r = $this->exec('dbo.xp_Sklad4TreeViewNewCurrentCode', [$sklad])->queryOne(\PDO::FETCH_NUM);
        if (!$r) return [];
        $out = [];
        foreach (array_slice($r, 0, 7) as $v) { if ($v === null) break; $out[] = (string)$v; }
        return $out;
    }

    /** ShowMaxItems / MaxItems_AfterUpdate: xp_ListViewMaxItemsGet (0 — прочитать, иначе записать; ответ — что стало). */
    public function maxItems(int $set = 0): int
    {
        return (int)LegacySp::sp1($set, 'dbo.xp_ListViewMaxItemsGet', $set ? 0 : 500);
    }

    /**
     * ButEl_Click. Счёт на элементы (id > 0): элемент возвращается в карточку, дальше её проверки (xp_GetWhatFirmSch, IsExistPrimEl) и frmPosInput.
     * Счёт на детали (id < 0, ScID): нужен элемент; xp_ProdPlanSchExistElem (ScID, SkladID) > 0 — «уже имеется в счёте».
     */
    public function choose(int $id, int $sklad): array
    {
        if ($id < 0) {
            if ($sklad <= 0) return ['ok' => false, 'msg' => 'Выберите элемент'];
            if ((int)LegacySp::sp4($id, 'dbo.xp_ProdPlanSchExistElem', $sklad, 0, 'int', 'int') > 0) {
                return ['ok' => false, 'msg' => 'Данный элемент уже имеется в счёте'];
            }
        } elseif ($sklad <= 0) {
            return ['ok' => false, 'msg' => 'Выберите элемент'];
        }
        return ['ok' => true, 'value' => $sklad];
    }

    /** Строка списка: значения как текст ListView; «Кат» = IsKat да/нет (да — синим), ВП5 и «Без аналогов» «да» — синим. */
    private function row(array $r): array
    {
        $o = ['id' => (int)($r['Код'] ?? 0)];
        foreach (self::COLS as [$f]) {
            $o[$f] = $f === 'Kat' ? ((int)($r['IsKat'] ?? 0) === 0 ? 'нет' : 'да') : F::value($r[$f] ?? null);
        }
        return $o;
    }

    private function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind  = [];
        foreach (array_values($values) as $i => $v) $bind[$i + 1] = $v;
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc $marks", $bind);
    }
}
