<?php
/**
 * Карточка элемента (форма Access Fobnovlen1) — просмотр.
 *
 * Что уже есть: шапка, поля записи из exec dbo.xp_Fobnovlen1Shelf и подчинённые списки
 * (история, договоры, наименования, отслеживание). Раскладка 1:1 с Access (около 230 элементов)
 * и кнопки-действия — следующий шаг.
 *
 * @var yii\web\View $this
 * @var int $id    Код элемента (положительный; знак уже разобран контроллером)
 * @var bool $rgm  Код пришёл отрицательным — «РГМ» (в Access Me!Rgm = 1)
 * @var array $rec запись из ElemService::card()
 * @var array $tabs подписи подчинённых списков (ElemService::TABS)
 */

use app\components\FieldLabel;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

// Правило проекта: у всех таблиц столбцы раздвигаются мышью
$this->registerJsFile('@web/js/colresize.js');
$this->registerCssFile('@web/css/colresize.css');

// Основные поля карточки — те, что в Access лежат в шапке формы
$main = [
    'Код', 'Элемент', 'Наименование', 'Серия', 'Номинал', 'Корпус', 'Изготовитель',
    'Кратко', 'Категория', 'Кол-во', 'Резерв', 'МинКоличество', 'ЦенаОптовая', 'ЦенаРозничная',
    'Стеллаж', 'Ячейка', 'Полка', 'Примечание',
];

// Подписи: сначала свои (как в Access), затем общий словарь проекта
$labels = [
    'МинКоличество' => 'Минимум',
    'ЦенаОптовая'   => 'Опт, $.',
    'ЦенаРозничная' => 'Розница, $.',
];
$label = static fn(string $f): string => $labels[$f] ?? FieldLabel::get($f);

$val = static function ($v): string {
    if ($v === null || $v === '') return '';
    if (is_bool($v)) return $v ? 'да' : '';
    if (is_string($v) && preg_match('/^(\d{4}-\d{2}-\d{2}) 00:00:00(\.0+)?$/', $v, $m)) return $m[1];
    return (string)$v;
};

$name = $val($rec['Наименование'] ?? $rec['Элемент'] ?? '');
$this->title = 'Элемент ' . $id . ($name !== '' ? ' — ' . $name : '');

$keys  = array_keys($rec);
$first = array_values(array_filter($main, static fn($f) => in_array($f, $keys, true)));
$rest  = array_values(array_diff($keys, $first));

/** Одна пара «подпись — значение». */
$cell = static function (string $f) use ($rec, $label, $val): string {
    $v = $val($rec[$f] ?? null);
    return '<div class="el-f"><div class="el-l">' . Html::encode($label($f)) . '</div>'
        . '<div class="el-v" title="' . Html::encode($v) . '">' . Html::encode($v) . '</div></div>';
};
?>
<div class="el-card">
    <header class="el-head">
        <div>
            <h1>Карточка элемента</h1>
            <div class="el-sub">
                Код: <b><?= $id ?></b><?= $rgm ? ' <span class="el-rgm" title="Открыт как РГМ (в Access Me!Rgm = 1)">РГМ</span>' : '' ?>
                <?php if ($name !== ''): ?> · <?= Html::encode($name) ?><?php endif ?>
            </div>
        </div>
        <button type="button" class="el-btn" id="elClose" title="Закрыть окно">✕ Закрыть</button>
    </header>

    <?php if (!$first && !$rest): ?>
        <div class="el-alert">Процедура не вернула данных по этому коду.</div>
    <?php endif ?>

    <?php if ($first): ?>
        <section class="el-box">
            <div class="el-box-t">Основное</div>
            <div class="el-grid"><?php foreach ($first as $f) echo $cell($f); ?></div>
        </section>
    <?php endif ?>

    <?php if ($rest): ?>
        <details class="el-box"<?= $first ? '' : ' open' ?>>
            <summary class="el-box-t">Все поля записи (<?= count($rest) ?>)</summary>
            <div class="el-grid"><?php foreach ($rest as $f) echo $cell($f); ?></div>
        </details>
    <?php endif ?>

    <section class="el-box">
        <div class="el-tabs" role="tablist">
            <?php $n = 0; foreach ($tabs as $key => $title): $n++; ?>
                <button type="button" class="el-tab<?= $n === 1 ? ' on' : '' ?>" role="tab"
                    data-tab="<?= Html::encode($key) ?>"
                    aria-selected="<?= $n === 1 ? 'true' : 'false' ?>"><?= Html::encode($title) ?></button>
            <?php endforeach ?>
            <span class="el-tab-info" id="elTabInfo"></span>
            <button type="button" class="el-btn el-btn-xs" id="elTabReload" title="Перечитать список">⟳</button>
        </div>
        <div class="el-tab-body" id="elTabBody">
            <div class="el-tab-empty">Загрузка…</div>
        </div>
    </section>

    <footer class="el-foot">
        <span class="el-note">Раскладка карточки как в Access и кнопки-действия — в разработке.</span>
    </footer>
</div>

<?php
$this->registerCss(<<<'CSS'
.el-card { --el-blue:#40B3D8; --el-blue-d:#2f8baa; --el-blue-l:#eaf7fc; --el-blue-b:#c5e7f2; --el-ink:#1b5c72;
    min-height:100vh; box-sizing:border-box; background:#f4fafc; padding:.4rem .7rem .6rem;
    font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d; }
.el-head { display:flex; align-items:center; gap:1rem; padding:.35rem .7rem; margin:-.4rem -.7rem .5rem;
    background:#fff; border-bottom:3px solid var(--el-blue); }
.el-head h1 { margin:0; font-size:1rem; font-weight:600; color:var(--el-ink); }
.el-sub { font-size:.72rem; color:#5b8598; margin-top:.1rem; }
.el-rgm { display:inline-block; padding:0 .3rem; border-radius:.25rem; background:#fff2d8; color:#8a5a00;
    border:1px solid #f0dfa8; font-size:.68rem; }
.el-btn { margin-left:auto; min-height:24px; padding:0 .6rem; background:#fff; border:1px solid var(--el-blue-b);
    border-radius:.3rem; color:var(--el-ink); font:600 .72rem/1 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
    cursor:pointer; white-space:nowrap; }
.el-btn:hover { background:var(--el-blue); border-color:var(--el-blue); color:#fff; }
.el-btn-xs { min-height:20px; padding:0 .35rem; margin-left:0; }

.el-box { background:#fff; border:1px solid var(--el-blue-b); border-radius:.5rem; margin-bottom:.5rem;
    box-shadow:0 1px 3px rgba(64,179,216,.12); overflow:hidden; }
.el-box-t { display:block; padding:.22rem .6rem; background:var(--el-blue-l); border-bottom:1px solid var(--el-blue-b);
    color:var(--el-ink); font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.03em; }
details.el-box > summary { cursor:pointer; list-style:none; }
details.el-box > summary::-webkit-details-marker { display:none; }
details.el-box > summary::before { content:'▸ '; }
details.el-box[open] > summary::before { content:'▾ '; }
.el-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:.15rem .8rem; padding:.4rem .6rem; }
.el-f { display:flex; gap:.4rem; align-items:baseline; border-bottom:1px dotted #e6f2f7; padding:.1rem 0; }
.el-l { flex:0 0 45%; color:#5b8598; font-size:.72rem; }
.el-v { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.el-alert { background:#fdecec; color:#a4232b; border:1px solid #f5c2c7; border-radius:.4rem; padding:.25rem .6rem; }

/* Вкладки списков */
.el-tabs { display:flex; align-items:center; gap:.15rem; padding:.2rem .4rem; background:var(--el-blue-l);
    border-bottom:1px solid var(--el-blue-b); flex-wrap:wrap; }
.el-tab { min-height:22px; padding:0 .55rem; background:transparent; border:1px solid transparent; border-radius:.3rem .3rem 0 0;
    color:#4a7383; font:600 .72rem/1 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; cursor:pointer; }
.el-tab:hover { background:#fff; }
.el-tab.on { background:#fff; border-color:var(--el-blue-b); border-bottom-color:#fff; color:var(--el-ink); }
.el-tab-info { margin-left:auto; color:#7d9aa7; font-size:.7rem; }
.el-tab-body { overflow:auto; max-height:calc(100vh - 320px); }
.el-tab-err { background:#fdecec; color:#a4232b; border:1px solid #f5c2c7; border-radius:.4rem; margin:.4rem .6rem; padding:.25rem .6rem; }
.el-tab-empty { color:#7d9aa7; font-style:italic; padding:.5rem .6rem; }

.el-tbl { border-collapse:collapse; width:100%; font-size:12px; }
.el-tbl th { position:sticky; top:0; z-index:1; background:var(--el-blue-l); color:var(--el-ink);
    font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.02em;
    padding:.2rem .35rem; border:1px solid var(--el-blue-b); white-space:nowrap; text-align:left; }
.el-tbl td { padding:.12rem .35rem; border-bottom:1px solid #e6f2f7;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:420px; }
.el-tbl tbody tr:hover td { background:#f4fafc; }

.el-foot { padding:.2rem .1rem; }
.el-note { color:#7d9aa7; font-size:.7rem; }
CSS);

$this->registerJs('window.EL = ' . Json::encode([
    'subUrl' => Url::to(['sub', 'id' => $id]),
]) . ';', \yii\web\View::POS_BEGIN);

$this->registerJs(<<<'JS'
/**
 * Подчинённые списки карточки — подформы Access (lstHistor, lstDogs, lstNaim, lstTrackingAnalogs, lstCodes).
 *
 * В Access все они заполнялись при открытии формы. Здесь список читается при первом щелчке
 * по вкладке и запоминается: у больших элементов история бывает в тысячи строк.
 */
(function () {
    const cfg = window.EL || {};
    const tabs = document.querySelector('.el-tabs');
    const body = document.getElementById('elTabBody');
    const info = document.getElementById('elTabInfo');
    const reload = document.getElementById('elTabReload');
    if (!tabs || !body || !cfg.subUrl) return;

    const cache = {};                 // tab → html
    let cur = null;
    let busy = false;

    async function load(tab, force) {
        if (busy) return;
        if (!force && cache[tab] !== undefined) {
            body.innerHTML = cache[tab];
            if (info) info.textContent = '';
            return;
        }
        busy = true;
        body.innerHTML = '<div class="el-tab-empty">Загрузка…</div>';
        if (info) info.textContent = '';
        try {
            const r = await fetch(cfg.subUrl + '&tab=' + encodeURIComponent(tab), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            cache[tab] = d.html || '';
            body.innerHTML = cache[tab];
            if (info) info.textContent = d.count ? 'Строк: ' + d.count : '';
            // Раздвигание столбцов мышью — как в остальных таблицах проекта (ширины запоминаются по вкладке)
            const t = body.querySelector('table.el-tbl');
            if (t && window.colResize) window.colResize(t, 'elem.tab.' + tab);
        } catch (e) {
            body.innerHTML = '<div class="el-tab-err">Не удалось загрузить список: '
                + e.message.replace(/[<>&]/g, '') + '</div>';
        } finally {
            busy = false;
        }
    }

    function select(tab) {
        cur = tab;
        [...tabs.querySelectorAll('.el-tab')].forEach(b => {
            const on = b.dataset.tab === tab;
            b.classList.toggle('on', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        load(tab, false);
    }

    tabs.addEventListener('click', e => {
        const b = e.target.closest('.el-tab');
        if (b && b.dataset.tab !== cur) select(b.dataset.tab);
    });
    if (reload) reload.addEventListener('click', () => cur && load(cur, true));

    // Первая вкладка — сразу, как Form_Open в Access
    const first = tabs.querySelector('.el-tab');
    if (first) select(first.dataset.tab);
})();
JS, \yii\web\View::POS_END);

$this->registerJs(<<<'JS'
document.getElementById('elClose')?.addEventListener('click', () => {
    // Окно открыто из списка (window.open) — закрываем; иначе возвращаемся назад
    if (window.opener && !window.opener.closed) window.close();
    else history.length > 1 ? history.back() : (location.href = '/');
});
JS, \yii\web\View::POS_END);
