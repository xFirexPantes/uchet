<?php
namespace app\modules\sklad\services;

use app\components\FieldLabel;
use Yii;

/**
 * Карточка элемента — форма Access Fobnovlen1.
 *
 * В Access карточку открывали из разных экранов одной командой:
 *   DoCmd.OpenForm "Fobnovlen1", acNormal, , , acFormEdit, acDialog, Код
 * Отдельного права на неё нет: кто видит список (frmSt, счёт, склад), тот открывает карточку.
 * Отрицательный Код — признак «РГМ»: в Form_Open его отбрасывают, а Me!Rgm ставят в 1.
 *
 * @legacy Fobnovlen1: Form_Open (xp_Fobnovlen1Shelf), Form_Current, GetData4Elem, FillLists
 */
class ElemService
{
    /** Подчинённые списки карточки: ключ → подпись вкладки. */
    public const TABS = [
        'history'  => 'История',
        'dogs'     => 'Договоры',
        'naim'     => 'Наименования',
        'tracking' => 'Отслеживание',
        'codes'    => 'Коды',
    ];

    /**
     * Колонки подчинённых форм: [поле => [подпись, ширина в твипах, скрыта, вправо]].
     *
     * Порядок — TabIndex из SaveAsText подформ (subfrmObnovlen1, subfrmFObnovlen1_01, subfrmSkladNaim).
     * Ширина в твипах делится на 15 → px, как в Access.
     *
     * Отличия от дизайна Access:
     *   - история: UPKoef скрыт — Form_Open/SetHistoryRowS скрывает его при обычном открытии (Rgm = False);
     *     ProductDate, WarrantySrok, WarrantyDate, наоборот, включены (Form_Open: ColumnHidden = False);
     *   - договоры: IspID, UchetID, IsActive скрыты (ColumnHidden в дизайне + Form_Open).
     */
    private const COLS = [
        'history' => [
            'Кол'                => ['Кол', 645, false, true],
            'ЦенаОптовая'        => ['Опт, $.', 900, false, true],
            'Цена с НДС (руб)'   => ['Цена с НДС (руб)', 1170, false, true],
            'Организация'        => ['Организация', 1455, false, false],
            'НомерСчета'         => ['№ счёта', 1215, false, false],
            'ДатаСчета'          => ['Дата счёта', 1035, false, false],
            'НомерНакладной'     => ['№ накладной', 1140, false, false],
            'ДатаНакладной'      => ['Дата накладной', 930, false, false],
            'ТочноеНазвание'     => ['Точное название', 4275, false, false],
            'Prim'               => ['Примечание', 0, false, false],
            'IsProblem'          => ['Проблема', 540, false, false],
            'Кто'                => ['Кто', 675, false, false],
            'Когда'              => ['Когда', 720, false, false],
            'План.дата'          => ['План. дата', 885, false, false],
            'Оплачен'            => ['Оплачен', 900, false, false],
            'План.срок'          => ['План. срок', 900, false, false],
            'Реал.срок'          => ['Реал. срок', 900, false, false],
            'DelayedDays'        => ['Задержка, дн.', 0, false, true],
            'ГТД'                => ['ГТД', 2325, false, false],
            'Страна'             => ['Страна', 840, false, false],
            'KolDataCode'        => ['Кол-во кодов', 945, false, true],
            'Ostatok'            => ['Остаток', 885, false, true],
            'ProductDate'        => ['Дата выпуска', 1417, false, false],
            'WarrantySrok'       => ['Срок гарантии', 1417, false, false],
            'WarrantyDate'       => ['Гарантия до', 1417, false, false],
            'FirmaBig'           => ['Фирма', 0, false, false],
            // скрытые
            'UpKoef'             => ['Коэф.', 1417, true, false],
            'Repl4Kfs'           => ['Repl4Kfs', 0, true, false],
            'PredShcID'          => ['PredShcID', 0, true, false],
            'Реал.дата'          => ['Реал. дата', 0, true, false],
            'AllID'              => ['AllID', 0, true, false],
            'kolPhoto'           => ['Фото', 0, true, false],
            'IDSchEl'            => ['IDSchEl', 0, true, false],
            'NedopolByNakl'      => ['Недопол. по накл.', 0, true, false],
            'WarrantyExpired'    => ['Гарантия истекла', 0, true, false],
            'InSklad'            => ['В складе', 0, true, false],
            'ImportDate'         => ['Дата импорта', 0, true, false],
            'AdditFile'          => ['Доп. файл', 0, true, false],
        ],
        'dogs' => [
            'NumDog'   => ['№ дог', 1350, false, false],
            'DataDog'  => ['Дата дог', 885, false, false],
            'SrokWar'  => ['Срок гарантии, мес.', 1230, false, true],
            'Org'      => ['Организация', 2460, false, false],
            'DescrDog' => ['Описание', 1650, false, false],
            'IspID'    => ['IspID', 900, true, false],
            'UchetID'  => ['UchetID', 960, true, false],
            'IsActive' => ['Активен', 0, true, false],
        ],
        'naim' => [
            'fld1'    => ['Серия', 1920, false, false],
            'fld2'    => ['Наименование', 1920, false, false],
            'fld3'    => ['Номинал', 1920, false, false],
            'fld4'    => ['Производитель', 2100, false, false],
            'SkladID' => ['SkladID', 0, true, false],
        ],
        // subfrmDef: в Form_Open родителя FObnovlen1 показаны только Def («Код») и Nme («Значение»),
        // ширины 1400 вместо дизайнерских 4175/3500; служебные поля скрыты.
        'codes' => [
            'Def'         => ['Код', 1400, false, false],
            'Nme'         => ['Значение', 1400, false, false],
            'IDProblem'   => ['IDProblem', 801, true, false],
            'TypeProblem' => ['TypeProblem', 801, true, false],
            'DepInt'      => ['DepInt', 801, true, false],
            'DefID'       => ['DefID', 801, true, false],
            'FlgActive'   => ['Активен', 560, true, false],
        ],
    ];

    /**
     * Запись карточки: exec dbo.xp_Fobnovlen1Shelf <Код>.
     *
     * @param int $id Код элемента; отрицательный — РГМ (как OpenArgs в Access)
     * @return array{row:?array,rgm:bool,id:int}
     */
    public function card(int $id): array
    {
        $rgm = $id < 0;
        $id  = abs($id);

        try {
            $row = $id > 0
                ? $this->exec('dbo.xp_Fobnovlen1Shelf', [$id])->queryOne()
                : false;
        } catch (\Throwable $e) {
            Yii::error('xp_Fobnovlen1Shelf ' . $id . ': ' . $e->getMessage(), __METHOD__);
            $row = false;
        }

        return ['row' => $row ?: null, 'rgm' => $rgm, 'id' => $id];
    }

    /**
     * Строки подчинённого списка карточки.
     *
     * Параметры взяты из VBA карточки:
     *   история        — SetHistoryRowS: xp_HistoryElem ID, Delayed, Abs(FakeSch), Abs(ShowDateImport), fndUpak
     *                    (по умолчанию Delayed = 2, ShowDateImport = False, fndUpak = '')
     *   договоры       — SetRS4DogsElem: xp_FObnovlen1GetDogs '<ГдеКупил>'
     *   наименования   — SetRS4SkladNaim: xp_FObnovlenGetNaim ID, Renew (Renew = 1)
     *   отслеживание   — SetRS4TrackingAnalogs: xp_SkladTackingAnalogs ID
     *   коды           — RS4SkladCodes: xp_SkladCodesRS SkladID, CopyID (CopyID = 0 при открытии)
     *
     * @param array $rec запись карточки (нужны ГдеКупил, FakeSch)
     * @return array{rows:array,error:?string}
     */
    public function tab(string $tab, int $id, array $rec = []): array
    {
        try {
            switch ($tab) {
                case 'history':
                    $rows = $this->exec('dbo.xp_HistoryElem', [
                        $id,
                        2,                                        // Delayed: 2 = обычный список, 1 = задержки
                        abs((int)($rec['FakeSch'] ?? 0)),         // Abs(Me!FakeSch): мнимые счета
                        0,                                        // Abs(Me!ShowDateImport): по умолчанию False
                        '',                                       // Me!fndUpak
                    ])->queryAll();
                    break;

                case 'dogs':
                    $rows = $this->exec('dbo.xp_FObnovlen1GetDogs', [(string)($rec['ГдеКупил'] ?? '')])->queryAll();
                    break;

                case 'naim':
                    $rows = $this->exec('dbo.xp_FObnovlenGetNaim', [$id, 1])->queryAll();
                    break;

                case 'tracking':
                    $rows = $this->exec('dbo.xp_SkladTackingAnalogs', [$id])->queryAll();
                    break;

                case 'codes':
                    // RS4SkladCodes(SkladID, CopyID:=0): при открытии карточки CopyID всегда 0
                    $rows = $this->exec('dbo.xp_SkladCodesRS', [$id, 0])->queryAll();
                    break;

                default:
                    return ['rows' => [], 'error' => 'Неизвестный список карточки.'];
            }
        } catch (\Throwable $e) {
            Yii::error('карточка элемента ' . $id . ', ' . $tab . ': ' . $e->getMessage(), __METHOD__);
            return ['rows' => [], 'error' => 'Не удалось получить список: ' . $e->getMessage()];
        }

        return ['rows' => $rows, 'error' => null];
    }

    /**
     * Колонки подчинённого списка.
     *
     * Если для списка есть разобранный дизайн Access (COLS) — берём порядок, подписи и ширины оттуда,
     * а поля, которых в дизайне не было, дописываем в конец: состав результата у процедур меняется.
     * Для «Отслеживания» дизайна нет — колонки строятся по фактическим полям.
     *
     * @return array<int, array{0:string,1:string,2:int,3:bool}> [поле, подпись, ширина px, вправо]
     */
    public function columns(string $tab, array $rows): array
    {
        $design = self::COLS[$tab] ?? null;

        // Фактические поля результата
        $present = [];
        foreach ($rows as $r) {
            foreach (array_keys($r) as $k) $present[$k] = true;
        }
        $present = array_keys($present);

        if ($design === null) {
            return array_map(static fn($f) => [$f, FieldLabel::get($f), 0, false], $present);
        }

        $out = [];
        foreach ($design as $f => [$label, $twips, $hidden, $right]) {
            if (!in_array($f, $present, true)) continue;
            $present = array_values(array_diff($present, [$f]));
            if ($hidden) continue;                      // ColumnHidden в Access — на экран не выводим
            $out[] = [$f, $label, $twips > 0 ? (int)round($twips / 15) : 0, $right];
        }
        foreach ($present as $f) {
            $out[] = [$f, FieldLabel::get($f), 0, false];
        }
        return $out;
    }

    /** EXEC процедура ?, ?, ... с параметрами по порядку. */
    private function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind  = [];
        foreach (array_values($values) as $i => $v) {
            $bind[$i + 1] = $v;                         // у Yii позиционные параметры считаются с 1
        }
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc $marks", $bind);
    }
}
