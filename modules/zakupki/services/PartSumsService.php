<?php
namespace app\modules\zakupki\services;

use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;

/**
 * «Долги» во «Входящих счетах» (frmStat.GetPartSums_Click): после выбора покупателя (frmFndFirm 9)
 * exec dbo.xp_GetPartSums 'UchetID', 0 → книга Excel. В Access книга открывалась в Excel; здесь — файл .xlsx на скачивание.
 * Как в VBA: строка 1 — имена полей на сером фоне (12632256), данные с A2 (CopyFromRecordset), ширина столбцов по
 * содержимому, под данными в столбце F — =SUM(F2:F…) жирным.
 */
class PartSumsService
{
    /** @return array{0: string[], 1: array, 2: string[]} имена полей, строки, тип столбца (num|date|text) */
    public function data(string $uchet): array
    {
        $st = Yii::$app->db->pdo->prepare("SET NOCOUNT ON; EXEC dbo.xp_GetPartSums ?, 0");
        $st->execute([$uchet]);
        $cols = $types = [];
        for ($i = 0; $i < $st->columnCount(); $i++) {
            $m = $st->getColumnMeta($i) ?: [];
            $cols[] = (string)($m['name'] ?? ('F' . ($i + 1)));
            $t = strtolower((string)($m['sqlsrv:decl_type'] ?? $m['native_type'] ?? ''));
            $types[] = preg_match('/int|money|decimal|numeric|float|real/', $t) ? 'num'
                : (preg_match('/date|time/', $t) ? 'date' : 'text');
        }
        return [$cols, $st->fetchAll(\PDO::FETCH_NUM), $types];
    }

    /** Книга .xlsx в строку (для ответа на скачивание). */
    public function xlsx(array $cols, array $rows, array $types): string
    {
        $book = new Spreadsheet();
        $sh = $book->getActiveSheet();
        $sh->setTitle('Лист1');
        foreach ($cols as $c => $name) {
            $sh->setCellValue([$c + 1, 1], $name);
        }
        if ($cols) {
            $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($cols));
            $sh->getStyle("A1:{$last}1")->getFill()->setFillType('solid')->getStartColor()->setRGB('C0C0C0');
        }
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $v) {
                if ($v === null) continue;
                $cell = [$c + 1, $r + 2];
                if ($types[$c] === 'num' && is_numeric($v)) {
                    $sh->setCellValue($cell, (float)$v);
                } elseif ($types[$c] === 'date' && ($d = date_create((string)$v))) {
                    $sh->setCellValue($cell, XlDate::PHPToExcel($d));
                    $sh->getStyle($cell)->getNumberFormat()->setFormatCode(
                        $d->format('His') === '000000' ? 'dd.mm.yyyy' : 'dd.mm.yyyy hh:mm'
                    );
                } else {
                    $sh->setCellValueExplicit($cell, (string)$v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }
        $sum = count($rows) + 2;                                    // .Cells(6).Cells(i).formula = "=SUM(F2:F" & i - 1 & ")"
        $sh->setCellValue([6, $sum], '=SUM(F2:F' . ($sum - 1) . ')');
        $sh->getStyle([6, $sum])->getFont()->setBold(true);
        foreach (array_keys($cols) as $c) {
            $sh->getColumnDimensionByColumn($c + 1)->setAutoSize(true);
        }
        ob_start();
        (new Xlsx($book))->save('php://output');
        return (string)ob_get_clean();
    }
}
