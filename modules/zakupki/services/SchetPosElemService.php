<?php
namespace app\modules\zakupki\services;

use app\components\AccessFormat as F;
use app\services\LegacySp;
use Yii;

/**
 * Окно позиции счёта на элементы (frmPosInput): режимы 3 (правка), 7 (по позиции уже есть накладная:
 * менять можно только цену) и 5 (добавление элемента, выбранного в облегчённом поиске frmFndElem).
 * Добавление из полного поиска fObnov (режим 2) ждёт переноса fObnov.
 *
 * В Access поля окна свободные (не привязаны к таблице), привязанные поля склада заблокированы, поэтому
 * состояние окна живёт на экране, а сервер считает и сохраняет теми же процедурами:
 *   xp_ZakasElSetSumsNew2 — суммы (SetSums4Pos), xp_NDSPosInputCheck — смена ставки (CheckNDS),
 *   xp_UpdOLEIInScet / xp_UpdOLEIInScet2 — единицы поставщика, xp_GetPriceEl / xp_KolDolgEl — предупреждения
 *   TestPrice / TestKol, xp_MakePosSchNew4 — сохранение (butSave_Click), xp_GetFirstGuiltyRes — IsExistGuiltyRes.
 */
class SchetPosElemService
{
    private SchetSaveService $save;

    public function __construct()
    {
        $this->save = new SchetSaveService();
    }

    /**
     * Кнопка15_Click: какой режим окна. Нет накладной → 3; есть → вопрос, затем 7.
     * (Режим 8 — правка количества при накладной с правом ChgKolSch — открывается из подформы, пока не перенесён.)
     */
    public function mode(int $id, int $allId, array $answers): array
    {
        $rec = $this->save->rec($id);
        if (F::bool($rec['IsClosed'] ?? 0)) {
            return ['ok' => false, 'msg' => 'Счёт закрыт'];
        }
        if (!$this->row($id, $allId)) {
            return ['ok' => false, 'msg' => 'Выберите элемент для редактирования'];
        }
        if (LegacySp::sp1($allId, 'dbo.xp_fn_ExistNakl', 0) <= 0) {
            return ['ok' => true, 'mode' => 3];
        }
        if (!isset($answers['nakl'])) {
            return ['ask' => ['key' => 'nakl', 'text' => 'По данному элементу уже есть накладная. Всё равно хотите отредактировать?']];
        }
        return $answers['nakl'] ? ['ok' => true, 'mode' => 7] : ['ok' => true];
    }

    /** Form_Load, режимы 3, 7 (правка позиции $allId) и 5 (новая позиция с элементом $skladId): начальные значения окна. */
    public function open(int $id, int $allId, int $mode, int $skladId = 0): array
    {
        $rec = $this->save->rec($id);
        if (F::bool($rec['IsClosed'] ?? 0)) throw new \yii\web\ForbiddenHttpException('Счёт закрыт');
        if ($mode === 5) return $this->openNew($id, $rec, $skladId);
        $row = $this->row($id, $allId);
        if (!$row) throw new \yii\web\NotFoundHttpException('Позиция не найдена в этом счёте');
        $nakl = LegacySp::sp1($allId, 'dbo.xp_fn_ExistNakl', 0) > 0;
        if ($mode !== 3 && $mode !== 7) $mode = 3;
        if ($mode === 3 && $nakl) $mode = 7;                         // по позиции с накладной — только режим 7
        $skladId = (int)$row['SkladID'];
        $p = $this->exec('dbo.xp_GetPosSkladNew4', [$skladId, $id, $allId])->queryOne();
        if (!$p) throw new \yii\web\NotFoundHttpException('Элемент склада не найден');

        $inUE = (int)($p['InUE'] ?? 0);
        $kol  = (float)($p['KolPOst'] ?? 0);
        $v = [
            'mode' => $mode, 'id' => $id, 'allId' => $allId, 'skladId' => $skladId, 'inUE' => $inUE,
            'kol' => $kol, 'priceRub' => (float)($p['PrcPost'] ?? 0), 'kol2' => (int)($p['kol2'] ?? 1),
            'okei' => (int)($p['PostOKEI'] ?? 796), 'okei2' => (int)($p['PostOKEI2'] ?? 796),
            'incNds' => F::bool($p['IncNDS'] ?? 0), 'upKoef' => (string)($p['UPKoef'] ?? 1),
            'manualFlg' => F::bool($p['ManualFlg'] ?? 0), 'manualKol' => (string)($p['ManualKol'] ?? ''), 'manualNme' => (string)($p['ManualNme'] ?? ''),
            'kurs' => (float)($p['Kurs'] ?? 0), 'nsh' => (string)($p['NSh'] ?? ''),
            'info' => (string)($row['Информация'] ?? ''), 'nmeCat' => (string)($row['NmeCat'] ?? ''),
            'mnlSm' => false, 'wasChg' => 0,
        ];
        // txtNDS: в режиме 3 — ставка строки, в 7 — startNDS (как в VBA)
        $v['nds'] = (float)($mode === 3 ? ($p['NDSVl'] ?? 0) : ($p['startNDS'] ?? 0));
        $v['oldNds'] = $v['nds'];
        // «Поставка» из списка: «дд.мм.гггг» и, может быть, через пробел-скобку вторая дата (первая поставка)
        preg_match_all('/(\d{2})\.(\d{2})\.(\d{4})/', (string)($row['Поставка'] ?? ''), $m, PREG_SET_ORDER);
        $v['dtaPost']   = isset($m[0]) ? "{$m[0][3]}-{$m[0][2]}-{$m[0][1]}" : '';
        $v['firstPost'] = isset($m[1]) ? "{$m[1][3]}-{$m[1][2]}-{$m[1][1]}" : '';
        if ($inUE === 0) {
            $v['priceOpt'] = (float)($mode === 3 ? ($p['ЦенаОптовая'] ?? 0) : ($row['ЦенаОптовая'] ?? 0));
            if ($mode === 3) {
                $v['sumNds'] = (float)($p['NDS'] ?? 0); $v['sumWNds'] = (float)($p['SumWNDS'] ?? 0); $v['prcNds'] = (float)($p['PriceWNDS'] ?? 0);
            } else {
                $v['sumNds'] = (float)($row['Сумма НДС'] ?? 0); $v['vsego'] = (float)($row['Всего'] ?? 0);
            }
        } else {
            $v['priceOpt'] = (float)($p['ЦенаОптовая'] ?? 0);
            $v['sumWNds'] = (float)($p['SmUE'] ?? 0);
            $v['sumNds'] = $v['sumWNds'] * (float)($p['NDSVl'] ?? 0) / 100;
            $v['prcNds'] = $kol != 0 ? $v['sumWNds'] / $kol : 0;
            if ($mode === 7) $v['vsego'] = (float)($p['PrcUE'] ?? 0) * $kol;
        }
        if ($v['kurs'] == 0) $v['kurs'] = $this->kursByVal($inUE);
        if ($mode === 3) $v = array_merge($v, $this->sums($v));       // Call SetSums4Pos
        $v += ['vsego' => 0.0, 'sumWNds' => 0.0, 'prcNds' => 0.0];

        $stock = [   // заблокированные поля склада (справа), только показ
            'Количество' => $p['Количество'] ?? null, 'Поле70' => $p['Заказано'] ?? null, 'Поле72' => $p['ПримечаниеДолг'] ?? null,
            'Поле74' => $p['МинКоличество'] ?? null, 'ЦенаОптовая' => $p['ЦенаОптовая'] ?? null, 'Поле78' => $p['ЦенаРозничная'] ?? null,
            'SkladIDAdd' => $skladId, 'txtShcID' => $id, 'txtDT' => $p['DT'] ?? null, 'txtUserCur' => $p['UserCur'] ?? null,
            'SchWithNDS' => $p['SchWithNDS'] ?? 0, 'PrcWithNDS' => $p['PrcWithNDS'] ?? 0,
        ];
        return [
            'v' => $v, 'stock' => $stock,
            'ndsList' => $this->list('dbo.xp_GetNDSList', [(string)($rec['INN'] ?? ''), 0, $skladId]),
            'okeiList' => $this->list('dbo.xp_DataEnterKolValLst', []),
            'okei2List' => $v['kol2'] > 1 ? $this->list('dbo.xp_KolvoDopInfoChildsRS', [$v['okei']]) : [],
            'reserv' => LegacySp::sp1($skladId, 'dbo.xp_GetResByPos', 0),
        ];
    }

    /**
     * Form_Load, Case 5: новая позиция. xp_GetPosSkladNew4 (SkladID, ID, 0); ставка — startNDS; каталог — xp_PosInputGetNmeCat;
     * «Без вопросов» (WOQuest) — как на карточке: при облегчённом поиске он включается. Суммы до первого изменения не считаются.
     */
    private function openNew(int $id, array $rec, int $skladId): array
    {
        if ($skladId <= 0) throw new \yii\web\BadRequestHttpException('Не выбран элемент');
        $p = $this->exec('dbo.xp_GetPosSkladNew4', [$skladId, $id, 0])->queryOne();
        if (!$p) throw new \yii\web\NotFoundHttpException('Элемент склада не найден');
        $inUE = (int)($p['InUE'] ?? 0);
        $v = [
            'mode' => 5, 'id' => $id, 'allId' => 0, 'skladId' => $skladId, 'inUE' => $inUE,
            'kol' => (float)($p['KolPOst'] ?? 0), 'priceRub' => (float)($p['PrcPost'] ?? 0), 'kol2' => (int)($p['kol2'] ?? 1),
            'okei' => (int)($p['PostOKEI'] ?? 796), 'okei2' => (int)($p['PostOKEI2'] ?? 796),
            'incNds' => F::bool($p['IncNDS'] ?? 0), 'upKoef' => '1',                 // UPKoef в режиме 5 не берётся (закомментировано в VBA)
            'manualFlg' => F::bool($p['ManualFlg'] ?? 0), 'manualKol' => (string)($p['ManualKol'] ?? ''), 'manualNme' => (string)($p['ManualNme'] ?? ''),
            'kurs' => (float)($p['Kurs'] ?? 0), 'nsh' => (string)($p['NSh'] ?? ''),
            'info' => '', 'nmeCat' => LegacySp::sp2($skladId, 'dbo.xp_PosInputGetNmeCat', '', 30),
            'mnlSm' => false, 'wasChg' => 0, 'woQuest' => true,
            'nds' => (float)($p['startNDS'] ?? 0), 'dtaPost' => '', 'firstPost' => '',
            'priceOpt' => 0.0, 'sumNds' => 0.0, 'vsego' => 0.0, 'sumWNds' => 0.0, 'prcNds' => 0.0,
        ];
        $v['oldNds'] = $v['nds'];
        if ($inUE > 0 && $v['kurs'] == 0) $v['kurs'] = $this->kursByVal($inUE);
        return [
            'v' => $v, 'stock' => $this->stock($p, $skladId, $id),
            'ndsList' => $this->list('dbo.xp_GetNDSList', [(string)($rec['INN'] ?? ''), 0, $skladId]),
            'okeiList' => $this->list('dbo.xp_DataEnterKolValLst', []),
            'okei2List' => $v['kol2'] > 1 ? $this->list('dbo.xp_KolvoDopInfoChildsRS', [$v['okei']]) : [],
            'reserv' => LegacySp::sp1($skladId, 'dbo.xp_GetResByPos', 0),
        ];
    }

    private function stock(array $p, int $skladId, int $id): array
    {
        return [   // заблокированные поля склада (справа), только показ
            'Количество' => $p['Количество'] ?? null, 'Поле70' => $p['Заказано'] ?? null, 'Поле72' => $p['ПримечаниеДолг'] ?? null,
            'Поле74' => $p['МинКоличество'] ?? null, 'ЦенаОптовая' => $p['ЦенаОптовая'] ?? null, 'Поле78' => $p['ЦенаРозничная'] ?? null,
            'SkladIDAdd' => $skladId, 'txtShcID' => $id, 'txtDT' => $p['DT'] ?? null, 'txtUserCur' => $p['UserCur'] ?? null,
            'SchWithNDS' => $p['SchWithNDS'] ?? 0, 'PrcWithNDS' => $p['PrcWithNDS'] ?? 0,
        ];
    }

    /**
     * frmFndElem.FndElemByStr (облегчённый поиск): xp_ReplacePtrnsFndEl 'st', IsDav, 0, lstPrin.
     * Число ищется как «st#^» (по коду), IsDav = 2 (группа «Давальческое» в этом режиме скрыта, значение по умолчанию).
     */
    public function fndElem(int $id, string $st, int $prin): array
    {
        $this->save->rec($id);
        $st = mb_substr(trim($st), 0, 100);
        if ($st !== '' && is_numeric($st)) $st .= '#^';
        $rows = $this->exec('dbo.xp_ReplacePtrnsFndEl', [$st, 2, 0, $prin])->queryAll();
        foreach ($rows as &$r) {
            unset($r['PriceOpt'], $r['PriceRoz']);                     // в этом режиме столбцы скрыты (ColumnHidden)
            foreach ($r as $k => $x) $r[$k] = F::value($x);
        }
        return $rows;
    }

    /** Принадлежность (lstPrin): exec dbo.xp_GetWhatFirm11 1 — [[код, название]]. */
    public function prinList(): array
    {
        return $this->list('dbo.xp_GetWhatFirm11', [1]);
    }

    /**
     * butDob_Click после выбора элемента: CommonStartSP4(SkladID, xp_GetWhatFirmSch, TP, -255) = 0 —
     * «Закупка данного элемента должна производиться на другую фирму» (только предупреждение, окно всё равно открывается);
     * затем IsExistPrimEl: xp_IsExistPrimEl (SkladID, res nvarchar(50)) не пусто → вопрос, Да → xp_DelPrimEl (SkladID) > 0.
     */
    public function checkNew(int $id, int $skladId, array $answers = []): array
    {
        $rec = $this->save->rec($id);
        if (F::bool($rec['IsClosed'] ?? 0)) return ['ok' => false, 'msg' => 'Счёт закрыт'];
        if ($skladId <= 0) return ['ok' => false, 'msg' => 'Не выбран элемент'];
        $msgs = [];
        if (!isset($answers['prim'])) {                                // предупреждение показываем один раз, до вопроса
            $r = LegacySp::sp4($skladId, 'dbo.xp_GetWhatFirmSch', $this->save->ispOf($rec['TP'] ?? null), -255, 'int');   // TP.Column(0)
            if ((int)$r === 0) $msgs[] = 'Закупка данного элемента должна производиться на другую фирму';
        }
        // IsExistPrimEl: есть примечание у элемента — предложить удалить (по умолчанию «Нет»)
        $prim = LegacySp::sp2($skladId, 'dbo.xp_IsExistPrimEl', '', 50);
        if ($prim !== '') {
            if (!isset($answers['prim'])) {
                return ['ask' => ['key' => 'prim', 'text' => "Имеется запись '$prim'\nУдалить примечание?"],
                        'msg' => $msgs ? implode("\n", $msgs) : null];
            }
            if ($answers['prim']) {
                $msgs[] = LegacySp::sp1($skladId, 'dbo.xp_DelPrimEl', 0) > 0 ? 'Примечание удалено' : 'Не удалось удалить примечание';
            }
        }
        return ['ok' => true, 'sklad' => $skladId, 'msg' => $msgs ? implode("\n", $msgs) : null];
    }

    /** SetSums4Pos: xp_ZakasElSetSumsNew2 (Prc, Sm, IncNDS, NDSVl, SumNDS OUT, Vsego OUT, PrcNDS OUT). SumWNDS = Vsego - SumNDS. */
    public function sums(array $v): array
    {
        $prc = round((float)$v['priceRub'], 2);
        $r = Yii::$app->db->createCommand(
            'SET NOCOUNT ON; DECLARE @s nvarchar(255), @v nvarchar(255), @p nvarchar(255);
             EXEC dbo.xp_ZakasElSetSumsNew2 ?, ?, ?, ?, @s OUTPUT, @v OUTPUT, @p OUTPUT; SELECT @s AS s, @v AS v, @p AS p',
            [1 => $prc, 2 => round($prc * (float)$v['kol'], 2), 3 => !empty($v['incNds']) ? 1 : 0, 4 => (int)round((float)$v['nds'])]
        )->queryOne();
        $num = fn($x) => (float)str_replace([',', ' '], ['.', ''], (string)$x);
        $s = $num($r['s'] ?? 0); $vs = $num($r['v'] ?? 0);
        return ['sumNds' => $s, 'vsego' => $vs, 'prcNds' => $num($r['p'] ?? 0), 'sumWNds' => $vs - $s];
    }

    /** CheckNDS: xp_NDSPosInputCheck (SchID, NDS, res OUT): -1 спросить, -2 / -3 нельзя. */
    public function checkNds(int $id, float $nds): int
    {
        $this->save->rec($id);
        return (int)$this->scalar('DECLARE @r int; EXEC dbo.xp_NDSPosInputCheck ?, ?, @r OUTPUT; SELECT @r', [$id, (int)round($nds)]);
    }

    /** UpdOKEI / UpdOKEI2: xp_UpdOLEIInScet (0, SkladID, OKEI, res) / xp_UpdOLEIInScet2 (0, SkladID, OKEI2, kol, res). */
    public function okei(int $id, int $skladId, int $okei, ?int $okei2, ?int $kol2): array
    {
        $this->save->rec($id);
        if ($okei2 === null) {
            $res = (int)$this->scalar('DECLARE @r int; EXEC dbo.xp_UpdOLEIInScet 0, ?, ?, @r OUTPUT; SELECT @r', [$skladId, $okei]);
            return ['res' => $res, 'okei2List' => $res === 2 ? $this->list('dbo.xp_KolvoDopInfoChildsRS', [$okei]) : []];
        }
        return ['res' => (int)$this->scalar('DECLARE @r int; EXEC dbo.xp_UpdOLEIInScet2 0, ?, ?, ?, @r OUTPUT; SELECT @r', [$skladId, $okei2, (int)$kol2])];
    }

    /** TestPrice(SkladID, Stoim, 0) — текст предупреждения или ''. */
    public function testPrice(int $skladId, float $stoim): string
    {
        $opt = (float)$this->scalar('DECLARE @p float; EXEC dbo.xp_GetPriceEl ?, @p OUTPUT; SELECT @p', [$skladId]);
        $d = round($stoim - $opt, 4);
        $txt = ($opt * 1.05 < $stoim ? 'Разница ПРЕВЫШАЕТ 5% и составляет ' : 'Разница менее 5% и составляет ') . self::n($d);
        if ($opt < $stoim) {
            $txt = 'Средняя цена элемента - ' . self::n($opt) . "\nВы вводите цену - " . self::n($stoim) . "\n" . $txt;
        }
        return $txt;
    }

    /** TestKol: xp_KolDolgEl; предупреждение, если Kol < долга или > долга * 1,1. */
    public function testKol(int $skladId, float $kol): string
    {
        $dlg = LegacySp::sp1($skladId, 'dbo.xp_KolDolgEl', 0);
        return ($kol < $dlg || $kol > $dlg * 1.1) ? "Кол-во '[Долг]-[Недополучено]' = $dlg\nВы вводите " . self::n($kol) : '';
    }

    /**
     * butSave_Click (режимы 3, 7): проверки, xp_MakePosSchNew4 в транзакции, затем IsExistGuiltyRes.
     * $reason — код причины (frmfndZac 19) для «10.1 Сырье и материалы» при изменении, $comm — комментарий.
     */
    public function savePos(array $v, ?string $reason, ?string $comm, array $answers = []): array
    {
        $id = (int)$v['id']; $allId = (int)$v['allId']; $mode = (int)$v['mode'];
        $rec = $this->save->rec($id);
        if (F::bool($rec['IsClosed'] ?? 0)) return ['ok' => false, 'msg' => 'Счёт закрыт'];
        if ($mode === 5) {                                             // новая позиция (Case 2, 5 в butSave_Click)
            $allId = 0;
            $skladId = (int)($v['skladId'] ?? 0);
            if ($skladId <= 0) return ['ok' => false, 'msg' => 'Не выбран элемент'];
            if ((float)$v['kol'] == 0) return ['ok' => true, 'close' => true];   // If txtKolich = 0 Then GoTo ex: окно закрывается без записи
            // «Не указана дата поставки. Указать?» — Да: курсор в дату (в Access запись шла дальше, здесь ждём дату)
            if (trim((string)$v['dtaPost']) === '') {
                if (!isset($answers['dta'])) return ['ask' => ['key' => 'dta', 'text' => 'Не указана дата поставки. Указать?']];
                if ($answers['dta']) return ['ok' => false, 'focus' => 'DtaPost'];
            }
        } else {
            $row = $this->row($id, $allId);
            if (!$row) return ['ok' => false, 'msg' => 'Позиция не найдена в этом счёте'];
            $skladId = (int)$row['SkladID'];
        }
        if ($mode === 7 && LegacySp::sp1($allId, 'dbo.xp_fn_ExistNakl', 0) <= 0) $mode = 3;
        $info = [];
        $kol = (float)$v['kol']; $nds = (float)$v['nds']; $priceRub = (float)$v['priceRub'];
        if (!empty($v['manualFlg'])) {
            if (trim((string)$v['manualNme']) === '' || trim((string)$v['manualKol']) === '') return ['ok' => false, 'msg' => 'Не заполнены поля ручного ввода'];
            if (!is_numeric(str_replace(',', '.', (string)$v['manualKol'])) || (float)str_replace(',', '.', (string)$v['manualKol']) <= 0) return ['ok' => false, 'msg' => 'Указано не верное кол-во'];
        }
        $s = $this->sums($v);                                          // Call SetSums4Pos (+ SetOptPrice на экране)
        if ($kol == 0 || (float)$v['priceOpt'] == 0) $info[] = 'Количество или цена равны нулю.';
        // каталог обязателен для закупки деталей (не операций 10.2)
        if (stripos((string)$v['nsh'], '10.2 ') === false && trim((string)$v['nmeCat']) === '') {
            if ((int)LegacySp::sp7(-$skladId, 'dbo.xp_IsExistNmeCatNew', self::n($kol), '0', 50, 255) === 1) {
                return ['ok' => false, 'msg' => 'Не указан каталог'];
            }
        }
        // суммы без НДС и цена без НДС, как в VBA (MnlSm в окне не включается: Vsego > 0 после SetSums4Pos)
        $vsego = $s['vsego'];
        if (empty($v['incNds'])) {
            $sumWNds = $vsego > 0 ? $vsego : $priceRub * $kol + $s['sumNds'];
            $prcRub = $priceRub;
        } else {
            $sumWNds = $vsego > 0 ? 100 * $vsego / (100 + $nds) : 100 * $priceRub * $kol / (100 + $nds);
            $prcRub = 100 * $priceRub / (100 + $nds);
        }
        // PriceOptT: в рублёвом счёте — опт в $, в валютном — цена (евро, фунты, юани пересчитываются в $)
        $inUE = (int)$v['inUE']; $kurs = (float)$v['kurs'];
        $usd = $this->kursByVal(840);
        $priceOptT = match (true) {
            $inUE === 0 => (float)$v['priceOpt'],
            in_array($inUE, [1, 840], true) => $priceRub,
            in_array($inUE, [2, 978], true) => $usd ? round($this->kursByVal(978) / $usd * $priceRub, 4) : 0,
            default => $usd ? round($kurs / $usd * $priceRub, 4) : 0,
        };
        $reasonChg = 0; $reasonComm = '';
        if (!empty($v['wasChg'])) {
            if ($v['nsh'] === '10.1 Сырье и материалы') {
                if ($reason === null) return ['pick' => ['form' => 'frmfndZac', 'mode' => 19, 'field' => 'reason', 'empty' => 'Не указана причина'], 'comment' => true];
                if (trim($reason) === '') return ['ok' => false, 'msg' => 'Не указана причина'];
                $reasonChg = (int)$reason; $reasonComm = mb_substr((string)$comm, 0, 255);
            } elseif ($v['nsh'] === '003 Материалы, принятые в переработку') {
                $reasonComm = 'Давальческое сырье';
            }
        }
        $date = fn($d) => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$d, $m) ? "$m[1]$m[2]$m[3]" : '18991230';   // пусто = 0 в ADO
        $params = [
            $mode, round($kol, 4), rtrim(rtrim(number_format($priceOptT, 4, '.', ''), '0'), '.') ?: '0', mb_substr(trim((string)$v['info']) === '' ? '' : (string)$v['info'], 0, 255),
            $skladId, $id, round($prcRub, 4), round($sumWNds, 4), round($s['sumNds'], 4), (int)round($nds), round($s['prcNds'], 4), round($vsego, 4),
            round($kurs, 4), mb_substr((string)$v['nmeCat'], 0, 30), $allId, $date($v['dtaPost']), $date($v['firstPost']),
            $reasonChg, $reasonComm, mb_substr((string)$v['upKoef'], 0, 20), (int)$v['kol2'], (int)$v['okei'], (int)$v['okei2'],
            !empty($v['incNds']) ? 1 : 0, round($priceRub, 4), !empty($v['manualFlg']) ? 1 : 0,
            (float)str_replace(',', '.', (string)($v['manualKol'] ?: 0)), mb_substr((string)$v['manualNme'], 0, 255),
        ];
        $db = Yii::$app->db;
        $t = $db->beginTransaction();
        try {
            $bind = [];
            foreach ($params as $i => $p) $bind[$i + 1] = $p;
            $res = (string)$db->createCommand(
                'SET NOCOUNT ON; DECLARE @r nvarchar(255); EXEC dbo.xp_MakePosSchNew4 ' . implode(', ', array_fill(0, count($params), '?')) . ', @r OUTPUT; SELECT @r',
                $bind
            )->queryScalar();
            if ($res !== '' && $res[0] === '3') {
                $t->rollBack();
                return ['ok' => false, 'msg' => 'Количество в накладной (' . mb_substr($res, 1) . ') не совпало с количеством в счёте. Изменения не были внесены'];
            }
            if ($res !== '' && $res[0] === '2') {
                $t->rollBack();
                return ['ok' => false, 'msg' => mb_substr($res, 1)];
            }
            $t->commit();
        } catch (\Throwable $e) {
            if ($t->isActive) $t->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            return ['ok' => false, 'msg' => "Ошибка сохранения. Действия отменены.\n" . $e->getMessage()];
        }
        if ($mode !== 7) {   // IsExistGuiltyRes
            $d = $v['dtaPost'] ?: $v['firstPost'];
            if (!$d) {
                $info[] = 'Не указана дата поставки';
            } else {
                $g = (string)$this->scalar('DECLARE @r nvarchar(1024); EXEC dbo.xp_GetFirstGuiltyRes ?, ?, @r OUTPUT; SELECT @r', [$skladId, $date($d)]);
                if ($g !== '') $info[] = $g;
            }
        }
        return ['ok' => true, 'close' => true, 'msg' => $info ? implode("\n\n", $info) : null];
    }

    /** Курс валюты: xp_GetKursByVal (код, res OUTPUT). 840 — доллар (KursUSD главной формы), 978 — евро. */
    public function kursByVal(int $inUE): float
    {
        $code = match (true) { in_array($inUE, [0, 1, 840], true) => 840, in_array($inUE, [2, 978], true) => 978, default => $inUE };
        return (float)str_replace(',', '.', LegacySp::sp2($code, 'dbo.xp_GetKursByVal', '0', 50));
    }

    /** Число как в CStr() на русской Windows: запятая, без лишних нулей. */
    public static function n(float $x): string
    {
        $s = rtrim(rtrim(number_format($x, 4, ',', ''), '0'), ',');
        return $s === '-0' ? '0' : $s;
    }

    /** SkladID позиции этого счёта (для расчётов окна берём с сервера, а не с экрана). */
    public function skladOf(int $id, int $allId, int $newSklad = 0): int
    {
        $this->save->rec($id);
        if ($allId === 0 && $newSklad > 0) return $newSklad;          // режим 5: элемент ещё не в счёте
        $r = $this->row($id, $allId);
        if (!$r) throw new \yii\web\NotFoundHttpException('Позиция не найдена в этом счёте');
        return (int)$r['SkladID'];
    }

    private function row(int $id, int $allId): ?array
    {
        foreach ($this->save->exec('dbo.xp_LstMassInputRowS1', [$id])->queryAll() as $r) {
            if ((int)$r['AllID'] === $allId) return $r;
        }
        return null;
    }

    /** Список для поля со списком: [[значение, текст]]. */
    private function list(string $proc, array $args): array
    {
        try {
            return array_map(fn($r) => [(string)$r[0], (string)($r[1] ?? $r[0])], $this->exec($proc, $args)->queryAll(\PDO::FETCH_NUM));
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return [];
        }
    }

    private function scalar(string $sql, array $values)
    {
        $bind = [];
        foreach (array_values($values) as $i => $v) $bind[$i + 1] = $v;
        try {
            return Yii::$app->db->createCommand('SET NOCOUNT ON; ' . $sql, $bind)->queryScalar();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return null;
        }
    }

    private function exec(string $proc, array $values)
    {
        $marks = implode(', ', array_fill(0, count($values), '?'));
        $bind  = [];
        foreach (array_values($values) as $i => $v) $bind[$i + 1] = $v;
        return Yii::$app->db->createCommand("SET NOCOUNT ON; EXEC $proc $marks", $bind);
    }
}
