<?php
namespace app\modules\zakupki\services;

use app\components\UncPath;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Yii;
use yii\web\UploadedFile;

/**
 * «Сроки поставки» во «Входящих счетах» (frmStat.KompelSrokBut_Click): поставщик выбран в frmChoice4InControl 101,
 * файл Excel копируется в \\Tsrv\Docs\Счета на оплату\Загрузка счетов\<папка>\ и в \\TSRV\Basa\Prices\open_zakaz*.xlsx,
 * строки проверяются, затем exec dbo.xp_SchKompelControlSrokLoadFle1 'RepDT', Tip, 'путь'. Для КОМПЭЛ ещё ChangeDta
 * (xp_SchKompelControlSrokNewTZ). Процедура SQL читает файл с \\TSRV сама, поэтому обе копии обязательны.
 */
class SrokService
{
    private const DOCS = '\\\\Tsrv\\Docs\\Счета на оплату\\Загрузка счетов\\';
    private const PRICES = '\\\\TSRV\\Basa\\Prices\\';

    /** Tip (OptionValue кнопки) => [папка в «Загрузка счетов», файл в Prices (null — без копии), подпись в окне выбора файла]. */
    public const TIPS = [
        1  => ['Интэкон',    'open_zakazINT.xlsx',   'ИНТЭКОН'],
        6  => ['Компэл',     'open_zakaz.xlsx',      'КОМПЭЛ'],
        7  => ['МТ',         'open_zakazMT.xlsx',    "'МТ-Систем' ('АТОМА')"],
        8  => ['Стрелой',    'open_zakazStr.xlsx',   "'Стрелой Е-коммерц'"],
        3  => ['АМК ЮНИОН',  'open_zakazUnion.xlsx', "'АМК ЮНИОН'"],
        4  => ['Мамонт',     null,                   "'Мамонт'"],
        9  => ['Витязь',     'open_zakazVit.xlsx',   "'Витязь'"],
        10 => ['Аврора',     'open_zakazAvr.xlsx',   "'Аврора'"],
    ];

    public function load(int $tip, ?UploadedFile $file): array
    {
        if (!isset(self::TIPS[$tip])) return ['ok' => false, 'msg' => 'Неизвестный поставщик'];
        if (!$file || $file->hasError) return ['ok' => false, 'msg' => 'Файл не получен' . ($file ? ' (ошибка ' . $file->error . ')' : '')];
        $name = UncPath::safeName(basename(str_replace('\\', '/', $file->name)));
        if (!preg_match('/(xlsx|\.xls)$/i', $name)) return ['ok' => false, 'msg' => 'Нужен файл Excel', 'icon' => 'warn'];

        try {
            $book = IOFactory::load($file->tempName);
        } catch (\Throwable $e) {
            return ['ok' => false, 'msg' => 'Не удалось прочитать файл Excel', 'icon' => 'warn'];
        }
        $sh = $book->getActiveSheet();
        $repDt = '';
        $err = match ($tip) {
            1  => $this->rows($sh, 2, 'A', fn($i) => !$this->num($sh, "D$i") ? "Ошибка кол-ва в строке $i"
                    : (!$this->date($sh, "F$i") ? "Ошибка даты в строке $i" : (!$this->inYear($sh, "F$i") ? "Ошибка даты в строке $i" : null))),
            6  => $this->kompel($sh, $repDt),
            8  => $this->rows($sh, 2, 'A', fn($i) => !$this->num($sh, "E$i") ? "Ошибка Количества в строке $i"
                    : (!$this->date($sh, "J$i") || !$this->inYear($sh, "J$i") ? "Ошибка даты в строке $i" : null)),
            3  => $this->rows($sh, 2, 'A', fn($i) => !$this->num($sh, "H$i") ? "Ошибка Количества в строке $i"
                    : (!$this->date($sh, "M$i") ? "Ошибка даты в строке $i"
                    : (!$this->inYear($sh, "M$i") ? "Дата меньше текущей или слишком далеко в будущем в строке$i" : null))),
            9  => $this->rows($sh, 2, 'A', fn($i) => !$this->num($sh, "T$i") ? "Ошибка 'Артикул 2' в строке $i"
                    : (!$this->num($sh, "Q$i") ? "Ошибка Цены в строке $i"
                    : (!$this->inYear($sh, "U$i") ? "Дата поставки меньше текущей или слишком большая - в строке$i" : null))),
            10 => $this->rows($sh, $this->firstFilled($sh), 'A', fn($i) => !$this->num($sh, "I$i") ? "Ошибка кол-ва в строке $i"
                    : ($this->str($sh, "L$i") !== '' && !$this->inYear($sh, "L$i") ? "Дата поставки меньше текущей или слишком большая - в строке$i" : null)),
            default => null,
        };
        if ($err !== null) return ['ok' => false, 'msg' => $err, 'icon' => $tip === 6 ? 'error' : 'info'];

        [$dir, $prices] = self::TIPS[$tip];
        $unc = self::DOCS . $dir . '\\' . $name;
        if ($e = UncPath::save($file->tempName, $unc)) return ['ok' => false, 'msg' => $e, 'icon' => 'warn'];
        if ($prices !== null && ($e = UncPath::save($file->tempName, self::PRICES . $prices))) return ['ok' => false, 'msg' => $e, 'icon' => 'warn'];
        if ($tip === 4 && ($e = $this->mamont($book))) return ['ok' => false, 'msg' => $e, 'icon' => 'error'];

        Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_SchKompelControlSrokLoadFle1 ?, ?, ?', [1 => $repDt, 2 => $tip, 3 => $unc])->execute();
        if ($tip === 6) Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_SchKompelControlSrokNewTZ')->execute();   // ChangeDta
        return ['ok' => true, 'msg' => 'Загрузка завершена'];
    }

    /**
     * «Сроки КОМПЭЛ» (frmStat.SpokKompelbtn_Click): та же загрузка, что «Сроки поставки» с Tip 6,
     * затем экран по очереди выбирает резерв для каждой строки (SelResKompel).
     * Возвращает ID строк tmptblKOMPELSrokNew, которым резерв ещё не выбран.
     */
    public function kompelLoad(?UploadedFile $file): array
    {
        $r = $this->load(6, $file);
        if (!$r['ok']) return $r;
        return ['ok' => true, 'msg' => $r['msg'], 'ids' => $this->kompelIds()];
    }

    /** SelResKompel: select ID from dbo.tmptblKOMPELSrokNew with (nolock) Where Idres = -1 and who = CURRENT_USER. */
    public function kompelIds(): array
    {
        return array_map('intval', Yii::$app->db->createCommand(
            'SELECT ID FROM dbo.tmptblKOMPELSrokNew WITH (NOLOCK) WHERE Idres = -1 AND who = CURRENT_USER'
        )->queryColumn());
    }

    /** SetReserv(ID, IDRes): exec dbo.xp_SetReserv4Kompel — без результата. */
    public function kompelReserv(int $id, int $idRes): array
    {
        if ($id <= 0 || $idRes <= 0) return ['ok' => false, 'msg' => 'Не выбран резерв'];
        try {
            Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_SetReserv4Kompel ?, ?', [1 => $id, 2 => $idRes])->execute();
            return ['ok' => true];
        } catch (\yii\db\Exception $e) {
            Yii::error('dbo.xp_SetReserv4Kompel: ' . $e->getMessage(), __METHOD__);
            return ['ok' => false, 'msg' => 'Не удалось сохранить резерв'];
        }
    }

    /** КОМПЭЛ: в A2 в конце дата отчёта (Right(A2, 10)) — она уходит в процедуру как RepDT. */
    private function kompel(Worksheet $sh, string &$repDt): ?string
    {
        $t = mb_substr($this->str($sh, 'A2'), -10);
        if (!$this->isDateStr($t)) return 'В ячейке А2 отсутствует дата отчета';
        $repDt = $t;
        return null;
    }

    /**
     * Мамонт: лист 1 перекладывается в лист ConvertedData (шапка — строка «№ пп», строки счёта и позиций сводятся в одну,
     * дата поставки — столбец N) и сохраняется как \\TSRV\Basa\Prices\open_zakazMamont1.xls (Excel 97).
     */
    private function mamont(Spreadsheet $book): ?string
    {
        $src = $book->getSheetByName('TDSheet') ?? $book->getSheet(0);
        $hds = 0;
        for ($i = 1; $i <= 50; $i++) if ($this->str($src, "A$i") === '№ пп') $hds = $i;
        if ($hds === 0) return 'Не удалось найти начало таблицы данных';
        $s1 = $book->getSheet(0);
        $out = $book->createSheet();
        $out->setTitle('ConvertedData');
        for ($c = 1; $c <= 50; $c++) $out->setCellValue(self::a($c, 1), $s1->getCell(self::a($c, $hds))->getValue());
        $dta = array_fill(0, 14, '');
        $j = 2;
        $flush = function () use (&$dta, &$j, $out) {
            for ($k = 0; $k <= 13; $k++) $out->setCellValue(self::a($k + 1, $j), $dta[$k]);
            $dta[3] = ''; $dta[13] = ''; $j++;
        };
        $v = fn($c, $i) => $s1->getCell(self::a($c, $i))->getValue();
        for ($i = $hds + 1; !($this->str($s1, "B$i") === '' && $this->str($s1, "C$i") === '' && $this->str($s1, "M$i") === '' && $this->str($s1, "N$i") === ''); $i++) {
            if ($this->str($s1, "C$i") !== '') {                         // номер счёта
                if ((string)$dta[3] !== '') $flush();
                $dta[1] = $v(2, $i); $dta[2] = $v(3, $i);
            } elseif ($this->str($s1, "D$i") !== '') {                   // позиция
                if ((string)$dta[3] !== '') $flush();
                $dta[0] = $v(1, $i);
                for ($k = 3; $k <= 12; $k++) $dta[$k] = $v($k + 1, $i);
            } else {                                                     // дата поставки
                $d = $this->dt($s1, "N$i");
                if ($d === null) return "Ошибочная дата в строке $i";
                $cur = (string)$dta[13] === '' ? null : $this->toDate($dta[13]);
                if ($cur === null || ($d->getTimestamp() - $cur->getTimestamp()) / 86400 > 1) $dta[13] = $v(14, $i);
            }
            if ($i > $hds + 100000) break;
        }
        for ($k = 0; $k <= 13; $k++) $out->setCellValue(self::a($k + 1, $j), $dta[$k]);
        $tmp = tempnam(sys_get_temp_dir(), 'mam');
        IOFactory::createWriter($book, 'Xls')->save($tmp);
        $e = UncPath::save($tmp, self::PRICES . 'open_zakazMamont1.xls');
        @unlink($tmp);
        return $e;
    }

    private static function a(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col) . $row;
    }

    /** Цикл While Range(col & i) <> "" — проверка каждой строки; первая ошибка возвращается. */
    private function rows(Worksheet $sh, int $from, string $col, callable $check): ?string
    {
        for ($i = $from; $this->str($sh, "$col$i") !== ''; $i++) {
            if ($e = $check($i)) return $e;
        }
        return null;
    }

    /** Аврора: первая непустая строка в A (не дальше 100). */
    private function firstFilled(Worksheet $sh): int
    {
        for ($i = 1; $i <= 100; $i++) if ($this->str($sh, "A$i") !== '') return $i;
        return 1;
    }

    private function str(Worksheet $sh, string $a): string
    {
        return trim((string)$sh->getCell($a)->getFormattedValue());
    }

    private function num(Worksheet $sh, string $a): bool
    {
        $v = $sh->getCell($a)->getCalculatedValue();
        return is_numeric(is_string($v) ? str_replace([' ', ','], ['', '.'], trim($v)) : $v);
    }

    private function date(Worksheet $sh, string $a): bool
    {
        return $this->dt($sh, $a) !== null;
    }

    /** Дата в ячейке не раньше сегодняшнего момента и не дальше 365 дней (как Range < Now() Or DateDiff("d", Now(), …) > 365). */
    private function inYear(Worksheet $sh, string $a): bool
    {
        $d = $this->dt($sh, $a);
        if ($d === null) return false;
        $now = new \DateTimeImmutable();
        return $d >= $now && $now->diff($d)->days <= 365;
    }

    private function dt(Worksheet $sh, string $a): ?\DateTimeImmutable
    {
        return $this->toDate($sh->getCell($a)->getCalculatedValue());
    }

    private function toDate($v): ?\DateTimeImmutable
    {
        if ($v instanceof \DateTimeInterface) return \DateTimeImmutable::createFromInterface($v);
        if (is_numeric($v)) return $v > 0 ? \DateTimeImmutable::createFromMutable(XlDate::excelToDateTimeObject((float)$v)) : null;
        $t = trim((string)$v);
        return $this->isDateStr($t) ? $this->parseDate($t) : null;
    }

    private function isDateStr(string $t): bool
    {
        return $this->parseDate($t) !== null;
    }

    private function parseDate(string $t): ?\DateTimeImmutable
    {
        foreach (['d.m.Y', 'd.m.y', 'd.m.Y H:i:s', 'd.m.Y H:i', 'Y-m-d', 'Y-m-d H:i:s', 'd/m/Y'] as $f) {
            $d = \DateTimeImmutable::createFromFormat('!' . $f, $t);
            if ($d && $d->format($f) === $t) return $d;
        }
        return null;
    }
}
