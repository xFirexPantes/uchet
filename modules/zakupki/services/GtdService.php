<?php
namespace app\modules\zakupki\services;

use app\components\LegacyMail;
use app\services\LegacySp;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Yii;

/**
 * Загрузка ГТД из файла поставщика (frmLoadKompel), окно поверх накладной (кнопка «ГТД», LoadGTD_Click).
 *
 * Как в Access:
 *   номер разборщика (Rgm)   — xp_frmMassInputSchExistFile (PrSchID) = tblFirmaAppendix.ExistXlGTD поставщика, < 0 — кнопка ничего не делает;
 *   подпись «Позиции счёта»  — xp_LoadCompelClear (IDSch) (Form_Open, Case Else);
 *   позиции счёта (lstSch)   — exec xp_LstMassInputRowS1 IDSch (SetRS4PosSchKompel);
 *   позиции в файле (lstNakl) — exec xp_LoadNaklKompelGetRSNakl (SetRS4PosNaklKompel), временная таблица пользователя;
 *   «Загрузить файл»          — xp_LoadCompelClear 0, затем GetGTD<Поставщик>: строки Excel по одной в xp_LoadNakl<…>;
 *   «Сопоставить»             — xp_LoadKompelAssignEl (AllID, IDStr) = 1; уже сопоставлена: «Да» — xp_LoadNaklKompelCopyStr (ещё строка);
 *   «Удалить сопоставление»   — xp_LoadKompelAssignEl (-AllID, IDStr) = 1;
 *   «Выгрузить в накладную»   — xp_LoadKompelPutNakl (IDNakl) > 0 — выгружено; -1 — есть несопоставленные; -2 — больше, чем в счёте (вопрос, затем -IDNakl).
 *
 * Отличия от Access: файл выбирается в браузере и читается на сервере (PhpSpreadsheet), Excel на компьютере не нужен.
 * Пометку «загружено» в файл пользователя записать нельзя — её нет. Вопроса «Использовать файл …?» (txt4GTD) нет: файл каждый раз выбирают.
 */
class GtdService
{
    /** Разборщики, которые читают Excel построчно (вид А). Остальные (6, 7, 8, 11, 12) кладут файл в \\TSRV\Basa\Prices — ещё не перенесены. */
    public const ROWWISE = [0, 1, 2, 3, 4, 5, 9];
    public const NAMES = [0 => 'Компэл', 1 => 'Акрол', 2 => 'Витал', 3 => 'Алеф', 4 => 'Элсио', 5 => 'Алкон', 6 => 'Симметрон',
        7 => 'Стрела', 8 => 'SkyParts', 9 => 'Алкон', 11 => 'Торекс', 12 => 'Радио'];

    /** LoadGTD_Click накладной: проверки и номер разборщика. */
    public function check(array $rec): array
    {
        if (empty($rec['ShcID'])) return ['ok' => false];
        if (trim((string)($rec['НомерНакладной'] ?? '')) === '') return ['ok' => false, 'msg' => 'Не указан номер накладной'];
        if (trim((string)($rec['Org'] ?? '')) === '') return ['ok' => false, 'msg' => 'Не указан продавец'];   // поле «Организация» накладной = столбец Org
        $rgm = LegacySp::sp1((int)($rec['PrSchID'] ?? 0), 'dbo.xp_frmMassInputSchExistFile', -1);
        if ($rgm < 0) return ['ok' => true];                                   // нет разборщика — как в Access, ничего
        if (!in_array($rgm, self::ROWWISE, true)) {
            $who = self::NAMES[$rgm] ?? "режим $rgm";
            return ['ok' => false, 'msg' => "Загрузка ГТД для поставщика «{$who}» ещё не перенесена в веб."
                . (isset(self::NAMES[$rgm]) ? "\nЕй нужна папка \\\\TSRV\\Basa\\Prices на сервере." : '')];
        }
        return ['ok' => true, 'gtd' => $rgm];
    }

    /** Данные окна (Form_Open, Case Else). */
    public function view(array $rec): array
    {
        $ids = $this->ids($rec);
        $schCard = (new SchetService())->getElem($ids['sch']);
        return $ids + [
            'caption' => 'Позиции счёта ' . LegacySp::sp2($ids['sch'], 'dbo.xp_LoadCompelClear', '', 20),
            'cardRec' => $schCard['rec'] ?? [],
            'sch_rows' => $this->schRows($ids['sch']),
            'file_rows' => [],                     // Form_Open подформу не заполняет (RecordSource пуст) — список появляется после загрузки файла
        ];
    }

    /** IDSch, NumNakl, IDNakl, Rgm окна (OpenArgs и OldLine 'номер;код' в Access). */
    public function ids(array $rec): array
    {
        $sch = (int)($rec['PrSchID'] ?? 0);
        return ['sch' => $sch, 'nakl' => (int)$rec['ShcID'], 'num' => (string)($rec['НомерНакладной'] ?? ''),
                'rgm' => LegacySp::sp1($sch, 'dbo.xp_frmMassInputSchExistFile', -1)];
    }

    public function schRows(int $sch): array
    {
        return $this->safe(fn() => $this->exec('dbo.xp_LstMassInputRowS1', [$sch])->queryAll(), []);
    }

    public function fileRows(): array
    {
        return $this->safe(fn() => $this->exec('dbo.xp_LoadNaklKompelGetRSNakl', [])->queryAll(), []);
    }

    // ------------------------------------------------------------------ загрузка файла (LoadKompelXl_Click)

    /**
     * $path — загруженный файл, $name — его имя у пользователя. Ответ: ok, msg.
     * Как в VBA: проверка расширения, «это не документ …», затем строки, у которых номер накладной (столбец GetAdr(N)) = номеру нашей,
     * по одной в процедуру. Цикл идёт, пока в этом столбце не пусто. Ошибка SQL останавливает загрузку (On Error → MsgBox).
     */
    public function load(array $ids, string $path, string $name): array
    {
        $ext = strtolower(substr($name, -3));
        if ($ext !== 'xls' && $ext !== 'lsx') return ['ok' => false, 'msg' => 'Это не xls-файл'];
        $rgm = $ids['rgm'];
        if (!in_array($rgm, self::ROWWISE, true)) return ['ok' => false, 'msg' => 'Этот поставщик ещё не перенесён'];
        LegacySp::sp2(0, 'dbo.xp_LoadCompelClear', '', 20);
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly($rgm !== 5 && $rgm !== 9);              // в режиме «только данные» не читается активный лист — он нужен Алкону
            $book = $reader->load($path);
        } catch (\Throwable $e) {
            Yii::warning('GTD: ' . $e->getMessage(), __METHOD__);
            return ['ok' => false, 'msg' => 'Не удалось прочитать файл: ' . $e->getMessage()];
        }
        try {
            return match ($rgm) {
                0       => $this->kompel($book, $ids),
                1, 2, 3 => $this->acrolVitalAlef($book, $ids, $rgm),
                4       => $this->elsio($book, $ids),
                5, 9    => $this->alkon($book, $ids),
            };
        } catch (\yii\db\Exception $e) {
            Yii::error('GTD: ' . $e->getMessage(), __METHOD__);
            return ['ok' => false, 'msg' => 'Ошибка при загрузке строки: ' . $e->getMessage()];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    /** GetGTDKompel: лист «Лист1», E5 = «Наименование», строки с 6-й, номер накладной — столбец GetAdr(71). */
    private function kompel($book, array $ids): array
    {
        $sh = $book->getSheetByName('Лист1');
        if (!$sh || $this->str($sh, 5, 5) !== 'Наименование') return ['ok' => false, 'msg' => 'Это не документ Компэла'];
        return $this->rows($sh, 6, (int)LegacyMail::adr(71), 'dbo.xp_LoadNaklKompel', $ids, fn($i) => [
            'Naim' => [$this->str($sh, 5, $i), 99], 'Primen' => [$this->str($sh, 6, $i), 255], 'Izg' => [$this->str($sh, 7, $i), 50],
            'Kol' => $this->int($sh, 9, $i), 'Prc' => [$this->str($sh, 10, $i), 11], 'Sm' => [$this->str($sh, 12, $i), 14],
            'GTD' => [$this->str($sh, 22, $i), 255], 'IDCountry' => $this->int($sh, 24, $i),
        ]);
    }

    /**
     * GetGTDAcrol / GetGTDVital / GetGTDAlef: первый лист, в C2 «АКРОЛ» или «форвард» / «Витал» / «Алеф», строки со 2-й,
     * номер накладной — GetAdr(72) у Акрола, GetAdr(73) у Витала и Алефа. Сумма = Round(Кол × Цена, 2).
     */
    private function acrolVitalAlef($book, array $ids, int $rgm): array
    {
        $sh = $book->getSheet(0);
        $c2 = $this->str($sh, 3, 2);
        $has = fn(string $s) => mb_stripos($c2, $s) !== false;             // InStr при Option Compare Database — без учёта регистра
        [$ok, $err, $adr, $proc] = match ($rgm) {
            1 => [$has('АКРОЛ') || $has('форвард'), 'Это не документ Форварда', 72, 'dbo.xp_LoadNaklAcrol'],
            2 => [$has('Витал'), 'Это не документ Витала', 73, 'dbo.xp_LoadNaklVital'],
            3 => [$has('Алеф'), 'Это не документ Алеф Компонентс', 73, 'dbo.xp_LoadNaklAlef'],
        };
        if (!$ok) return ['ok' => false, 'msg' => $err];
        $r = $this->rows($sh, 2, (int)LegacyMail::adr($adr), $proc, $ids, fn($i) => [
            'Naim' => [$this->str($sh, 7, $i), 50], 'Primen' => [$this->str($sh, 8, $i), 255], 'Izg' => ['', 50],
            'Kol' => $this->int($sh, 12, $i), 'Prc' => [$this->str($sh, 11, $i), 11],
            'Sm' => [$this->cstr(round($this->num($sh, 12, $i) * $this->num($sh, 11, $i), 2)), 14],
            'GTD' => [$this->str($sh, 13, $i), 255], 'IDCountry' => $this->int($sh, 15, $i),
        ]);
        // у Витала и Алефа — сообщение, если нужной накладной в файле нет
        if ($r['ok'] && $r['found'] === 0 && $rgm !== 1) $r['msg'] = 'Не найдена указанная накладная';
        return $r;
    }

    /** GetGTDElsio: первый лист, первая непустая ячейка столбца E (до 100-й строки) = «Наименование», строки ниже, GetAdr(122). */
    private function elsio($book, array $ids): array
    {
        $sh = $book->getSheet(0);
        $i = $this->firstFilled($sh, 5);
        if ($this->str($sh, 5, $i) !== 'Наименование') return ['ok' => false, 'msg' => 'Это не документ Элсио'];
        return $this->rows($sh, $i + 1, (int)LegacyMail::adr(122), 'dbo.xp_LoadNaklKompel', $ids, fn($i) => [
            'Naim' => [$this->str($sh, 5, $i), 50], 'Primen' => [$this->str($sh, 6, $i), 255], 'Izg' => [$this->str($sh, 7, $i), 50],
            'Kol' => $this->int($sh, 9, $i), 'Prc' => [$this->str($sh, 10, $i), 11], 'Sm' => [$this->str($sh, 12, $i), 14],
            'GTD' => [$this->str($sh, 20, $i), 255], 'IDCountry' => $this->int($sh, 22, $i),
        ]);
    }

    /** GetGTDAlkon: активный лист, в C2 «Алкон», данные ниже первой непустой ячейки столбца C, GetAdr(123), страна — xp_GetIDCountry по названию. */
    private function alkon($book, array $ids): array
    {
        $sh = $book->getActiveSheet();
        $i = $this->firstFilled($sh, 3);
        if (mb_stripos($this->str($sh, 3, 2), 'Алкон') === false) return ['ok' => false, 'msg' => 'Это не документ Алкон'];
        return $this->rows($sh, $i + 1, (int)LegacyMail::adr(123), 'dbo.xp_LoadNaklKompel', $ids, fn($i) => [
            'Naim' => [$this->str($sh, 8, $i), 50], 'Primen' => ['', 255], 'Izg' => ['', 50],
            'Kol' => $this->int($sh, 12, $i), 'Prc' => [$this->str($sh, 11, $i), 50], 'Sm' => [$this->str($sh, 13, $i), 50],
            'GTD' => [$this->str($sh, 15, $i), 255],
            'IDCountry' => LegacySp::sp6(0, 'dbo.xp_GetIDCountry', $this->str($sh, 16, $i), 0, 255),
        ]);
    }

    /**
     * Общий цикл GetGTD*: пока в столбце $col (номер накладной) не пусто; строки нашей накладной — в процедуру
     * (IDStr, Naim, Primen, Izg, Kol, Prc, Sm, GTD, IDCountry, IDSch, NumNakl, res OUT).
     * NumNakl, как в VBA, — номер из первой строки данных (параметр задаётся один раз до цикла).
     */
    private function rows(Worksheet $sh, int $i, int $col, string $proc, array $ids, callable $row): array
    {
        if ($col <= 0) return ['ok' => false, 'msg' => 'Не задан столбец номера накладной (xp_GetAdr)'];
        $first = $nakl = $this->str($sh, $col, $i);
        $found = $loaded = 0;
        $last = $sh->getHighestDataRow();
        $cmd = Yii::$app->db->createCommand("SET NOCOUNT ON; DECLARE @r int; EXEC $proc ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @r OUTPUT; SELECT @r");
        while ($nakl !== '' && $i <= $last + 1) {
            if ($nakl === $ids['num']) {
                $found++;
                $v = $row($i);
                $cut = fn($p) => mb_substr((string)$p[0], 0, $p[1]);
                $cmd->bindValues([
                    1 => $i, 2 => $cut($v['Naim']), 3 => $cut($v['Primen']), 4 => $cut($v['Izg']), 5 => $v['Kol'],
                    6 => $cut($v['Prc']), 7 => $cut($v['Sm']), 8 => $cut($v['GTD']), 9 => (int)$v['IDCountry'],
                    10 => $ids['sch'], 11 => mb_substr($first, 0, 50),
                ]);
                if ((int)$cmd->queryScalar() === 1) $loaded++;
            }
            $i++;
            $nakl = $this->str($sh, $col, $i);
        }
        return ['ok' => true, 'found' => $found, 'loaded' => $loaded];
    }

    /** Do While Cells(col).Cells(i) = "": i = i + 1: If i > 100 Then Exit Do */
    private function firstFilled(Worksheet $sh, int $col): int
    {
        $i = 1;
        while ($this->str($sh, $col, $i) === '') {
            $i++;
            if ($i > 100) break;
        }
        return $i;
    }

    // ------------------------------------------------------------------ ячейки (Cells(столбец).Cells(строка) в VBA)

    private function raw(Worksheet $sh, int $col, int $row)
    {
        $addr = Coordinate::stringFromColumnIndex($col) . $row;
        if (!$sh->cellExists($addr)) return null;
        $c = $sh->getCell($addr);
        if ($c->isFormula()) {
            $v = $c->getOldCalculatedValue();                                // значение, сохранённое Excel
            if ($v === null) { try { $v = $c->getCalculatedValue(); } catch (\Throwable $e) { $v = null; } }
            return $v;
        }
        $v = $c->getValue();
        return $v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText ? $v->getPlainText() : $v;
    }

    /** Значение ячейки строкой, как CStr в русской Windows: целые без дробной части, дробные — с запятой. */
    private function str(Worksheet $sh, int $col, int $row): string
    {
        return $this->cstr($this->raw($sh, $col, $row));
    }

    private function cstr($v): string
    {
        if ($v === null) return '';
        if (is_bool($v)) return $v ? 'Истина' : 'Ложь';
        if (is_int($v)) return (string)$v;
        if (is_float($v)) {
            if (floor($v) === $v && abs($v) < 1e15) return (string)(int)$v;
            $s = sprintf('%.15G', $v);                                       // CStr: до 15 значащих цифр
            if (stripos($s, 'E') !== false) $s = rtrim(rtrim(number_format($v, 15, '.', ''), '0'), '.');
            return str_replace('.', ',', $s);
        }
        return (string)$v;
    }

    private function num(Worksheet $sh, int $col, int $row): float
    {
        $v = $this->raw($sh, $col, $row);
        if (is_int($v) || is_float($v)) return (float)$v;
        $s = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], (string)$v);
        return is_numeric($s) ? (float)$s : 0.0;
    }

    /** Присвоение ячейки параметру adInteger: округление. */
    private function int(Worksheet $sh, int $col, int $row): int
    {
        return (int)round($this->num($sh, $col, $row));
    }

    // ------------------------------------------------------------------ сопоставление и выгрузка

    /**
     * AssignElKompel_Click. $copy: ответ на «Данный элемент файла уже сопоставлен… Завести ещё строку?» —
     * 'yes' (xp_LoadNaklKompelCopyStr), 'no' (заменить), null — ещё не спрашивали. Подтверждение «Сопоставить …?» делает окно.
     */
    public function assign(int $allId, int $idStr, ?string $copy): array
    {
        $row = $this->fileRow($idStr);
        if (!$row) return ['ok' => false, 'msg' => 'Не выбран элемент файла для сопоставления'];
        if ((int)($row['IDSchEl'] ?? 0) > 0 && $copy === 'yes') {
            $res = LegacySp::sp7((int)$row['IDSchEl'], 'dbo.xp_LoadNaklKompelCopyStr', (string)$allId, '  ', 50, 255);
            return match (substr($res, 0, 2)) {
                'ok' => ['ok' => true, 'selSch' => $allId, 'selFile' => (int)substr($res, 2)],
                'er' => ['ok' => false, 'msg' => substr($res, 2)],
                default => ['ok' => false, 'msg' => 'Не удалось разделить строку'],
            };
        }
        $r = LegacySp::sp4($allId, 'dbo.xp_LoadKompelAssignEl', $idStr, 0, 'int', 'int');
        return $r === 1 ? ['ok' => true, 'selSch' => $allId, 'selFile' => $idStr] : ['ok' => false, 'msg' => 'Не удалось выполнить сопоставление'];
    }

    /** DelAssign_Click: AllID текущей позиции счёта со знаком минус. */
    public function unassign(int $allId, int $idStr): array
    {
        $row = $this->fileRow($idStr);
        if (!$row) return ['ok' => false];
        if ((int)($row['IDSchEl'] ?? 0) === 0) return ['ok' => false, 'msg' => 'Компонент не сопоставлен. Удалять нечего'];
        $r = LegacySp::sp4(-$allId, 'dbo.xp_LoadKompelAssignEl', $idStr, 0, 'int', 'int');
        return $r === 1 ? ['ok' => true, 'selFile' => $idStr] : ['ok' => false, 'msg' => 'Не удалось удалить сопоставление'];
    }

    /** Put2Nakl_Click. answers['more'] — ответ «Да» на «…больше, чем в счёте. Всё равно загрузить накладную?». */
    public function put(int $nakl, array $answers): array
    {
        if (array_key_exists('more', $answers) && !$answers['more']) return ['ok' => true];   // «Нет» — Exit Sub
        $r = LegacySp::sp1(isset($answers['more']) ? -$nakl : $nakl, 'dbo.xp_LoadKompelPutNakl', 0);
        if ($r === -2 && !isset($answers['more'])) {
            return ['ok' => true, 'ask' => ['key' => 'more', 'text' => 'Есть строки в накладной, кол-во элемента в которых больше, чем в счёте. Всё равно загрузить накладную?']];
        }
        return match (true) {
            $r > 0    => ['ok' => true, 'msg' => 'Выгружено записей - ' . $r, 'close' => true],
            $r === -1 => ['ok' => false, 'msg' => 'Имеются несопоставленные строки', 'red' => true],
            default   => ['ok' => false, 'msg' => 'Не удалось выгрузить записи'],
        };
    }

    private function fileRow(int $idStr): ?array
    {
        foreach ($this->fileRows() as $r) if ((int)($r['IDStr'] ?? 0) === $idStr) return $r;
        return null;
    }

    private function safe(callable $f, $dflt)
    {
        try {
            return $f();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return $dflt;
        }
    }

    private function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind  = [];
        foreach (array_values($values) as $i => $v) $bind[$i + 1] = $v;
        return Yii::$app->db->createCommand(trim("SET NOCOUNT ON; EXEC $proc $marks"), $bind);
    }
}
