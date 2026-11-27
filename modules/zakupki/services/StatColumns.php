<?php
namespace app\modules\zakupki\services;

/**
 * Колонки списка «Статистики» по фактическим полям результата.
 *
 * В Access состав и подписи колонок задавала подформа frmMinimum, а fnRepaintFormStat
 * при смене «Вида» правила:
 *   - Caption подписей (Надпись19 у txtSV, МинКоличество_Надпись, Надпись42 у LowR,
 *     FZ275Kol_N, Надпись57 у OrderKat, Надпись51 у SostProblEl, Надпись70 у DfltIsp);
 *   - Visible у части колонок (FZ275Kol — только виды 1 и 3; вид 5 скрывает
 *     МинКоличество и txtSV; вид b прячет KolPok/NotRec/VP5/NoAnalog).
 *
 * Значения в CAPTIONS — дословно из VBA. Вид, которого нет в списке, оставляет
 * подпись по умолчанию из META.
 */
class StatColumns
{
    /** Служебные поля подформы: в Access у них Visible = False либо это технические ключи. */
    public const HIDDEN = [
        'IDCursor', 'reqSchColor', 'chk', 'IDInvoice', 'Colr', 'IDRes', 'IsBoth', 'TakeMe4St',
        'RequestSch', 'MainPlace',
    ];

    /** [подпись, ширина px, выравнивание вправо]. Поля, которых здесь нет, выводятся как есть. */
    public const META = [
        'Код'           => ['Код', 70, false],
        'Наименование'  => ['Наименование', 300, false],
        'Analog'        => ['Аналог', 200, false],
        'Страна'        => ['Страна', 110, false],
        'Производитель' => ['Производитель', 150, false],
        'Движение с'    => ['Движение с', 100, false],
        'Кратко'        => ['Кратко', 150, false],
        'Кол-во'        => ['Кол-во', 80, true],
        'txtSV'         => ['Дефицит', 80, true],
        'Примечание'    => ['Примечание', 260, false],
        'Исполнитель'   => ['Исполнитель', 120, false],
        'LowR'          => ['Сумма,$', 100, true],
        'NotRec'        => ['Не рек.', 60, false],
        'VP5'           => ['ВП5', 50, false],
        'NoAnalog'      => ['Нет аналога', 80, false],
        'KolPok'        => ['Кол-во пок.', 90, true],
        'FZ275Kol'      => ['ФЗ-275', 70, true],
    ];

    /**
     * Колонки, которые Access скрывает в отдельных видах (fnRepaintFormStat).
     *
     * Вид 5: `fBl = Not lgOp = "5"` — МинКоличество и txtSV (и их подписи) скрыты.
     * Вид b: в Case "b" явно KolPok/NotRec/VP5/NoAnalog.Visible = False, FZ275Kol там тоже False.
     */
    public const HIDDEN_IN = [
        '5' => ['МинКоличество', 'txtSV'],
        'b' => ['KolPok', 'NotRec', 'VP5', 'NoAnalog', 'FZ275Kol'],
    ];

    /**
     * Колонки, которые в остальных видах скрыты, а в перечисленных показываются.
     *
     * FZ275Kol: Visible = True только в ветках 1 и 3.
     * OrderKat: Надпись57.Visible = lgOp = "1", OrderKat.Visible = Надпись57.Visible;
     *           в Case "b" OrderKat.Width = 900 и подпись «Ближ. поставка».
     * SostProblEl, DfltIsp: в Case "b" явно Visible = True.
     */
    public const SHOWN_IN = [
        'FZ275Kol'    => ['1', '3'],
        'OrderKat'    => ['1', 'b'],
        'SostProblEl' => ['b'],
        'DfltIsp'     => ['b'],
    ];

    /** Подписи колонок, которые fnRepaintFormStat меняет при смене вида (дословно из VBA). */
    public const CAPTIONS = [
        'txtSV' => [
            '1' => 'Заказать', '2' => 'Заказать', '3' => 'Долг', '4' => 'Заказать',
            '7' => 'Заказать', '9' => 'Заказать', 'a' => 'Заказать', 'b' => 'Миним. сработал',
        ],
        'МинКоличество' => [
            '1' => 'Долг', '2' => 'Минимум', '3' => 'Минимум', '4' => 'Резерв',
            '9' => 'Мин.ПАНТЕС', 'a' => 'Мин.ТОРЭКС',
        ],
        'LowR'        => ['7' => 'Дешевле,$', 'b' => 'Бл.старт заказа'],
        'MinStartZak' => ['b' => 'Бл.старт заказа'],
        'FZ275Kol'    => ['1' => 'Заказать', '3' => 'Заказать', 'b' => 'Мин.'],
        'OrderKat'    => ['1' => 'Ближ. поставка', 'b' => 'Ближ. поставка'],
        'SostProblEl' => ['b' => 'Ком.произв.'],
        'DfltIsp'     => ['b' => 'Информация'],
        'Analog'      => ['b' => 'Серия'],
    ];

    /**
     * Колонки по фактическим полям результата: у каждого вида своя процедура,
     * поэтому состав колонок берём из данных, а не из фиксированного списка.
     *
     * @param array  $rows строки списка
     * @param string $vid  вид списка (1…b); пусто — без правок по виду
     * @return array<int, array{0:string,1:string,2:int,3:bool}> [поле, подпись, ширина, вправо]
     */
    public static function build(array $rows, string $vid = ''): array
    {
        $fields = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) {
                if (!in_array($k, self::HIDDEN, true)) $fields[$k] = true;
            }
        }

        $out = [];
        foreach (array_keys($fields) as $f) {
            // Скрыто целиком в этом виде (вид 5 — МинКоличество и txtSV)
            if (in_array($f, self::HIDDEN_IN[$vid] ?? [], true)) continue;
            // Показывается только в перечисленных видах (FZ275Kol, OrderKat, SostProblEl, DfltIsp)
            if (isset(self::SHOWN_IN[$f]) && $vid !== '' && !in_array($vid, self::SHOWN_IN[$f], true)) continue;

            [$title, $width, $right] = self::META[$f] ?? [$f, 0, false];
            // Caption по виду перекрывает подпись по умолчанию
            $title = self::CAPTIONS[$f][$vid] ?? $title;
            $out[] = [$f, $title, $width, $right];
        }
        return $out;
    }
}
