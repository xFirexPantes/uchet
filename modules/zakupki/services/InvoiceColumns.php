<?php

declare(strict_types=1);

namespace app\modules\zakupki\services;

/**
 * Колонки подформы SubFrmInvoices формы frmSt (список предложений поставщиков).
 *
 * Подписи и ширины — Caption/ColumnWidth из макета Forms/SubFrmInvoices.txt
 * (ширины пересчитаны в px). Порядок — ColumnOrder формы с учётом Form_Open:
 * PrcY.ColumnOrder = 9, PrcWONDS.ColumnOrder = 16.
 *
 * «Проект» в Access — CheckBox с источником =[ProjectPrice]>0, «В расчёт» и
 * «Активный» — тоже CheckBox'ы, поэтому в вебе это галки только для просмотра.
 */
final class InvoiceColumns
{
    /** Подписи, ширины и выравнивание вправо: [подпись, ширина, вправо]. */
    public const META = [
        'Post'         => ['Поставщик', 220, false],
        'DtaAdv'       => ['Дата', 82, false],
        'Srok'         => ['Срок,недель', 90, true],
        'Kolvo'        => ['Запр.кол.', 80, true],
        'Prc'          => ['Цена,$', 85, true],
        'PrcE'         => ['Цена,€', 85, true],
        'PrcY'         => ['Цена,¥', 85, true],
        'PrcRub'       => ['Цена,р с НДС', 95, true],
        'Prim'         => ['Примечание', 220, false],
        'TotalUSD'     => ['Сумма,$', 90, true],
        'TotalRUR'     => ['Ст-ть,р с НДФC', 110, true],
        'MaxKol'       => ['Мах', 70, true],
        'UserCur'      => ['Кто', 90, false],
        'email'        => ['email', 160, false],
        'PrcWONDS'     => ['без НДС', 85, true],
        'Analog'       => ['Наименование', 240, false],
        'InPivot'      => ['В расчёт', 75, false],
        'AnalogActive' => ['Активный', 75, false],
        'NumTbl'       => ['Св.табл', 70, true],
        'ProjectPrice' => ['Проект', 65, false],
        'CodeCat'      => ['Артикул(код)', 100, false],
        'NmeCat'       => ['Источник', 130, false],
        'RateTxt'      => ['Рейтинг', 90, false],
        'SostAgreed'   => ['Согласование', 100, false],
        'MOQ'          => ['MOQ', 60, true],
        'MPQ'          => ['MPQ', 60, true],
        'DC'           => ['Дата код', 80, false],
        'PrcEWONDS'    => ['Цена EUR без НДС', 105, true],
        'PriceUWONDS'  => ['Цена USD без НДС', 105, true],
    ];

    /** Порядок колонок — ColumnOrder подформы после Form_Open. */
    public const ORDER = [
        'Post', 'DtaAdv', 'Srok', 'Kolvo', 'Prc', 'PrcE', 'PrcY', 'PrcRub', 'Prim',
        'TotalUSD', 'TotalRUR', 'MaxKol', 'UserCur', 'email', 'PrcWONDS', 'Analog',
        'InPivot', 'AnalogActive', 'NumTbl', 'ProjectPrice', 'CodeCat', 'NmeCat',
        'RateTxt', 'SostAgreed', 'MOQ', 'MPQ', 'DC', 'PrcEWONDS', 'PriceUWONDS',
    ];

    /**
     * Служебные поля набора.
     *
     * В frmSt скрыты PostID и NSchByInvoice (fnRepaintFormStat); ID, SkladID, UchetID,
     * DT, MntAnalog, PrcOpt скрыты в Form_Open подформы. Остальные — технические
     * (обработка «Вид обработки», ключи) и в списке не нужны.
     */
    public const HIDDEN = [
        'ID', 'SkladID', 'UchetID', 'DT', 'MntAnalog', 'PrcOpt', 'PostID', 'NSchByInvoice',
        'ExistCat', 'IsCurTbl', 'IsAgreed', 'Manuf', 'oper', 'suboper', 'hrs', 'absid',
        'hrsprc', 'TypeVl', 'SrcLT',
    ];

    /** Поля-галки (в Access CheckBox). */
    public const CHECKBOX = ['InPivot', 'AnalogActive', 'ProjectPrice'];

    /** Поля-даты в формате Access dd.mm.yy. */
    public const DATES = ['DtaAdv', 'DC'];

    /**
     * Колонки по фактическим полям результата xp_GetInvoices1.
     *
     * @param array $rows строки источника
     * @return array<int, array{0:string,1:string,2:int,3:bool}> [поле, подпись, ширина, вправо]
     */
    public static function columns(array $rows): array
    {
        $fields = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) {
                if (!in_array($k, self::HIDDEN, true)) {
                    $fields[$k] = true;
                }
            }
        }

        $out = [];
        foreach (self::ORDER as $f) {
            if (!isset($fields[$f])) {
                continue;
            }
            [$title, $width, $right] = self::META[$f] ?? [$f, 0, false];
            $out[] = [$f, $title, $width, $right];
            unset($fields[$f]);
        }

        // Поля, которых нет в макете (например, новые из процедуры), — в конце
        foreach (array_keys($fields) as $f) {
            $out[] = [$f, $f, 0, false];
        }

        return $out;
    }
}
