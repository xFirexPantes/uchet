<?php
/**
 * frmGetPrcSch — окно «сколько оплатить» поверх «Входящих счетов». Координаты из SaveAsText (твипы / 15).
 *   Режим 0 (＋ у «Проценты/суммы оплат», AddOpl): форма 3831 × 1125 твипов = 255 × 75 px. «процентов» → +целое (до 100),
 *     «рублей» → −сумма и флажок «отключить проверку» (Ovr). Ничего не сохраняет: значение уходит экрану (frmStat!Prc).
 *   Режимы 2 и 4 (＋ у «К оплате», AddOplToDo, счёт на элементы / на детали): раскладка MakeWindowPrcSch,
 *     5200 × 2835 твипов = 347 × 189 px. Строка-заготовка уже создана; «процентов» / «рублей» сохраняют её (act ToDoSave).
 * Подпись кнопки «рублей» — валюта счёта (OldLine "-vl-" & ListSubItems(25)).
 *
 * @var yii\web\View $this
 * @var int        $id    счёт
 * @var int        $mode  0 | 2 | 4
 * @var int        $todo  строка «к оплате» (режимы 2, 4)
 * @var array|null $row   xp_OplPrcToDoRS todo, 0
 * @var string     $unit  валюта счёта
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = ' ';
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/dlg-modern.css');          // корпоративные цвета, когда окно открыто поверх современного вида
$this->registerJsFile('@web/js/access-dialog.js');
// dlg-modern ставится на <html> только если родитель — современный экран (.vh-modern)
$this->registerJs(<<<'JS'
try {
    if (window.parent !== window && window.parent.document.querySelector('.vh-modern')) {
        document.documentElement.classList.add('dlg-modern');
    }
} catch (e) { /* другой сайт или нет доступа — обычный вид Access */ }
JS, \yii\web\View::POS_HEAD);
$big = $mode !== 0;
$unitCap = $unit !== '' ? $unit : 'рублей';
$d = fn($k) => $row && !empty($row[$k]) ? substr((string)$row[$k], 0, 10) : '';
$prc = $big ? (isset($row['Prc']) && $row['Prc'] !== null ? rtrim(rtrim(number_format((float)$row['Prc'], 4, '.', ''), '0'), '.') : '') : '100';
?>
<style>
#frmGetPrcSch, #frmGetPrcSch input, #frmGetPrcSch textarea, #frmGetPrcSch button{font:13px Arial,sans-serif}
#frmGetPrcSch .lbl{position:absolute;overflow:hidden;line-height:1.15}
#frmGetPrcSch input[type=text], #frmGetPrcSch textarea{position:absolute;box-sizing:border-box;border:1px inset #999;padding:0 2px;background:#fff}
#frmGetPrcSch textarea{resize:none}
#frmGetPrcSch input[readonly]{cursor:pointer}
#frmGetPrcSch .chk{position:absolute;margin:0}
#frmGetPrcSch .small{font:11px Tahoma,sans-serif}
</style>
<?php if (!$big): ?>
<div class="access-form" id="frmGetPrcSch" style="width:255px;height:75px">
    <div class="lbl" style="left:3px;top:3px;width:63px;height:28px">Оплатить:</div>
    <input type="checkbox" class="chk" id="AllowOverflow" style="left:172px;top:5px;width:17px;height:16px">
    <label class="lbl small" for="AllowOverflow" style="left:191px;top:3px;width:62px;height:27px">отключить проверку</label>
    <input type="text" id="Sm" value="<?= Html::encode($prc) ?>" style="left:3px;top:33px;width:250px;height:17px">
    <button type="button" class="a-btn" id="Prc" style="left:3px;top:52px;width:83px;height:23px">процентов</button>
    <button type="button" class="a-btn" id="Rb" style="left:87px;top:52px;width:83px;height:23px"><?= Html::encode($unitCap) ?></button>
    <button type="button" class="a-btn" id="Cncl" style="left:172px;top:52px;width:83px;height:23px">отменить</button>
</div>
<?php else: ?>
<div class="access-form" id="frmGetPrcSch" style="width:347px;height:189px">
    <div class="lbl" style="left:4px;top:4px;width:120px;height:17px">Дата оплаты:</div>
    <input type="text" id="Dta2Opl" readonly data-v="<?= Html::encode($d('Dta2Opl')) ?>" title="Двойной щелчок — выбрать дату" style="left:4px;top:23px;width:125px;height:16px">
    <div class="lbl" style="left:215px;top:4px;width:120px;height:17px">Не позднее:</div>
    <input type="text" id="SrokOb" readonly data-v="<?= Html::encode($d('SrokOb')) ?>" title="Двойной щелчок — выбрать дату" style="left:215px;top:23px;width:125px;height:16px">
    <div class="lbl" style="left:4px;top:41px;width:148px;height:17px">Комментарий для этапа</div>
    <textarea id="Prim" style="left:4px;top:60px;width:336px;height:53px"><?= Html::encode((string)($row['Prim'] ?? '')) ?></textarea>
    <div class="lbl" style="left:3px;top:117px;width:63px;height:28px">К оплате</div>
    <input type="text" id="Sm" value="<?= Html::encode($prc) ?>" style="left:3px;top:147px;width:337px;height:17px">
    <button type="button" class="a-btn" id="Prc" style="left:3px;top:166px;width:83px;height:23px">процентов</button>
    <button type="button" class="a-btn" id="Rb" style="left:134px;top:166px;width:83px;height:23px"><?= Html::encode($unitCap) ?></button>
    <button type="button" class="a-btn" id="Cncl" style="left:259px;top:166px;width:83px;height:23px">отменить</button>
</div>
<?php endif ?>
<?php
$actUrl = Json::encode(Url::to(['act', 'id' => $id]));
$big = Json::encode($big);
$todo = (int)$todo;
$this->registerJs(<<<JS
(function () {
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    const big = $big, byId = id => document.getElementById(id), sm = byId('Sm');
    const today = () => { const t = new Date(); return t.getFullYear() + '-' + String(t.getMonth() + 1).padStart(2, '0') + '-' + String(t.getDate()).padStart(2, '0'); };
    const dmy = v => v ? v.slice(8, 10) + '.' + v.slice(5, 7) + '.' + v.slice(0, 4) : '';
    // Sm: Format "General Number", ValidationRule ">=0"
    function num() {
        const s = sm.value.trim().replace(/\s/g, '').replace(',', '.');
        if (s === '') return { v: null };
        const v = Number(s);
        if (!isFinite(v)) return { err: 'Введено значение, не подходящее для данного поля' };
        if (v < 0) return { err: 'Значение должно быть не меньше 0' };
        return { v };
    }
    // CInt: округление к ближайшему, .5 — к чётному
    const cint = v => { const f = Math.floor(v), r = v - f; return r > 0.5 || (r === 0.5 && f % 2) ? f + 1 : f; };
    const post = data => {
        const fd = new FormData(); Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
        if (csrfParam) fd.append(csrfParam, csrfToken);
        return fetch($actUrl, { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })));
    };
    sm.focus(); sm.select();

    if (!big) {
        // Режим 0: значение уходит экрану, ничего не сохраняется
        const ovr = byId('AllowOverflow');
        ovr.addEventListener('change', () => byId('Prc').disabled = ovr.checked);          // AllowOverflow_AfterUpdate
        byId('Prc').addEventListener('click', async () => {
            const n = num(); if (n.err) { await msgBox(n.err, { icon: 'warn' }); return; }
            if (n.v > 100) { await msgBox('Процентов не может быть более 100'); sm.value = 100; return; }
            closeAccessDialog(true, { prc: cint(n.v ?? 0), ovr: 0 });
        });
        byId('Rb').addEventListener('click', async () => {
            const n = num(); if (n.err) { await msgBox(n.err, { icon: 'warn' }); return; }
            closeAccessDialog(true, { prc: -(n.v ?? 0), ovr: ovr.checked ? 1 : 0 });
        });
        byId('Cncl').addEventListener('click', () => closeAccessDialog(false));            // frmStat!Prc = -1
        return;
    }

    // Режимы 2, 4: строка «к оплате»
    const d1 = byId('Dta2Opl'), d2 = byId('SrokOb');
    [d1, d2].forEach(e => e.value = dmy(e.dataset.v));
    d1.addEventListener('dblclick', async () => {                                         // Dta2Opl_DblClick
        const v = await dateBox('Дата оплаты', { value: d1.dataset.v }); if (!v) return;
        if (v < today()) { await msgBox('Вы указываете дату, которая уже прошла'); return; }
        const res = await post({ act: 'ToDoChk', todo: $todo, ymd: v.replace(/-/g, '') });
        if (res.ok) { d1.dataset.v = v; d1.value = dmy(v); } else await msgBox(res.msg || 'Не удалось изменить дату оплаты');
    });
    d2.addEventListener('dblclick', async () => {                                         // SrokOb_DblClick
        const v = await dateBox('Не позднее', { value: d2.dataset.v }); if (!v) return;
        if (v < today()) { await msgBox('Вы указываете дату, которая уже прошла'); return; }
        if (d1.dataset.v && v < d1.dataset.v) { await msgBox("Вы указываете дату 'Не позднее', которая меньше, чем 'Дата оплаты'"); return; }
        d2.dataset.v = v; d2.value = dmy(v);
    });
    async function save(unit) {                                                           // Prc_Click / Rb_Click → FillVl4Cmd2Opl
        const n = num(); if (n.err) { await msgBox(n.err, { icon: 'warn' }); return; }
        if (unit === 0 && n.v > 100) { await msgBox('Процентов не может быть более 100'); sm.value = 100; return; }
        const res = await post({ act: 'ToDoSave', todo: $todo, unit, Prc: n.v ?? '', Prim: byId('Prim').value, Dta2Opl: d1.dataset.v, SrokOb: d2.dataset.v });
        if (res.ok) { closeAccessDialog(true, 'saved'); return; }
        if (res.prc !== undefined) sm.value = res.prc;
        await msgBox(res.msg || 'Не удалось изменить запись');
    }
    byId('Prc').addEventListener('click', () => save(0));
    byId('Rb').addEventListener('click', () => save(1));
    byId('Cncl').addEventListener('click', () => closeAccessDialog(false));               // заготовку удаляет экран (--del--)
})();
JS, View::POS_END);
