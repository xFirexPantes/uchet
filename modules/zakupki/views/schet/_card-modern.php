<?php
/**
 * Каркас современной карточки счёта: шапка (номер, состояние, действия, дата/время/пользователь),
 * верхний ряд «участники / фирма», ниже — панели с данными счёта, таблицами и файлами.
 *
 * Координаты Access не используются: содержимое панелей — обычные сетки «подпись + поле».
 * Все id, data-field и data-act сохранены, поэтому скрипты карточки работают без изменений.
 *
 * @var yii\web\View $this
 * @var string $title
 * @var string $subtitle
 * @var string $badges     HTML бейджей состояния
 * @var string $actions    кнопки-действия (в шапке по центру)
 * @var string $stamp      дата / время / пользователь (в шапке справа)
 * @var string $who        «продавец / покупатель / ИНН / холдинг / контрагент»
 * @var string $SchDta     «данные счёта»: реквизиты, деньги, флажки
 * @var string $files      «файлы счёта» — под реквизитами, колонка тянется на высоту данных счёта
 * @var array  $panels     панели на всю ширину: [['title' => 'Файлы счёта', 'html' => '…'], …]
 * @var array  $tabs       вкладки: [['title' => 'Накладные', 'html' => '…'], …] — первая открыта
 */
use yii\helpers\Html;

$panels ??= [];
$tabs ??= [];
$actions ??= '';
$stamp ??= '';
$files ??= '';
$SchDta ??= '';
?>
<div class="vh-card">
    <header class="vh-card-head">
        <div class="vh-card-title">
            <h1 class="h5 mb-0"><?= Html::encode($title) ?></h1>
            <?php if (!empty($subtitle)): ?><div class="vh-card-sub"><?= $subtitle ?></div><?php endif ?>
        </div>
        <div class="vh-card-badges"><?= $badges ?></div>
        <?php if (!empty($actions)): ?>
            <div class="vh-head-actions"><?= $actions ?></div>
        <?php endif ?>
        <?php if (!empty($stamp)): ?>
            <div class="vh-stamp"><?= $stamp ?></div>
        <?php endif ?>
    </header>

    <div class="vh-card-body">
        <div class="vh-card-row">
            <div class="vh-card-col">
                <div class="vh-card-panel">
                    <div class="vh-card-panel-head">Реквизиты</div>
                    <div class="vh-rest-body"><?= $who ?></div>
                </div>
                <?php if (!empty($files)): ?>
                    <div class="vh-card-panel vh-card-files">
                        <div class="vh-card-panel-head">Файлы счёта</div>
                        <div class="vh-rest-body"><?= $files ?></div>
                    </div>
                <?php endif ?>
            </div>
            <div class="vh-card-panel vh-card-side">
                <div class="vh-card-panel-head">Данные счета</div>
                <div class="vh-rest-body"><?= $SchDta ?></div>
            </div>
        </div>

        <?php foreach ($panels as $p): ?>
            <?php if (empty($p['html'])) continue ?>
            <div class="vh-card-panel<?= !empty($p['cls']) ? ' ' . $p['cls'] : '' ?>">
                <div class="vh-card-panel-head"><?= Html::encode($p['title'] ?? '') ?></div>
                <div class="vh-rest-body"><?= $p['html'] ?></div>
            </div>
        <?php endforeach ?>

        <?php if (!empty($tabs)): ?>
            <div class="vh-card-panel vh-tabs">
                <div class="vh-tabs-bar" role="tablist">
                    <?php foreach ($tabs as $i => $t): ?>
                        <button type="button" role="tab" class="vh-tab<?= $i === 0 ? ' on' : '' ?>" data-tab="<?= (int)$i ?>"
                            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"><?= Html::encode($t['title'] ?? '') ?></button>
                    <?php endforeach ?>
                </div>
                <?php foreach ($tabs as $i => $t): ?>
                    <div class="vh-tab-body<?= $i === 0 ? ' on' : '' ?>" data-tab-body="<?= (int)$i ?>"><?= $t['html'] ?? '' ?></div>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</div>

<?php
$this->registerCss(<<<CSS
.vh-card { --vh-blue:#40B3D8; --vh-blue-d:#2f8baa; --vh-blue-l:#eaf7fc; --vh-blue-b:#c5e7f2; --vh-ink:#1b5c72;
    /* Высоты частей карточки: верх (реквизиты + данные счёта) и низ (вкладки) — постоянные,
       позиции занимают всё, что осталось между ними */
    --vh-top-h:250px; --vh-tabs-h:150px;
    background:#f4fafc; padding:.4rem .7rem .55rem; box-sizing:border-box;
    /* Вся карточка — ровно в экран: прокручиваются только таблицы внутри панелей */
    height:100vh; display:flex; flex-direction:column; overflow:hidden;
    font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d; }

.vh-card-head { display:flex; align-items:center; flex-wrap:wrap; gap:.35rem .75rem; flex:0 0 auto;
    padding:.35rem .7rem; margin:-.4rem -.7rem .4rem; background:#fff; border-bottom:3px solid var(--vh-blue); }
.vh-card-title h1 { color:var(--vh-ink); font-weight:600; font-size:1rem; margin:0; }
.vh-card-sub { font-size:.72rem; color:#5b8598; margin-top:.1rem; }
.vh-card-badges { display:flex; gap:.35rem; flex-wrap:wrap; }
.vh-card-badges .badge { font-weight:500; font-size:.72rem; padding:.35em .6em;
    background:var(--vh-blue-l); color:var(--vh-ink); border:1px solid var(--vh-blue-b); }
.vh-card-badges .badge.b-red { background:#fdecec; color:#a4232b; border-color:#f5c2c7; }
.vh-card-badges .badge.b-green { background:#e7f7ed; color:#146c43; border-color:#badbcc; }
/* Действия по центру шапки, дата/время/пользователь — справа */
.vh-head-actions { display:flex; flex-wrap:wrap; gap:.3rem; align-items:center; flex:1 1 auto; justify-content:center; }
.vh-head-actions .vh-tools { margin-bottom:0; }
.vh-stamp { display:flex; gap:.9rem; align-items:center; margin-left:auto; font-size:.75rem; color:#5b8598; }
.vh-stamp .vh-lbl { margin-right:.25rem; }
.vh-stamp .a-txt input { width:110px; }

.vh-card-body { display:flex; flex-direction:column; gap:.4rem; flex:1 1 auto; min-height:0; }
/* Верхний ряд: слева колонка «Реквизиты + Файлы счёта», справа данные счёта на всю её высоту.
   Высота постоянная (--vh-top-h): она одинакова у всех счетов, лишнее прокручивается внутри панелей */
.vh-card-row { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:.4rem;
    align-items:stretch; flex:0 0 auto; height:var(--vh-top-h); }
.vh-card-row > * { min-height:0; }
.vh-card-col { display:flex; flex-direction:column; gap:.4rem; min-width:0; overflow:hidden; }
.vh-card-col > .vh-card-panel { min-height:0; }
.vh-card-col > .vh-card-files { flex:1 1 auto; min-height:0; }   /* добирает высоту до правой панели */
.vh-card-side { display:flex; flex-direction:column; }
.vh-card-side > .vh-rest-body { flex:1 1 auto; min-height:0; }
.vh-card-panel { background:#fff; border:1px solid var(--vh-blue-b); border-radius:.5rem;
    box-shadow:0 1px 3px rgba(64,179,216,.12); overflow:hidden; min-width:0; }
.vh-card-panel-head { padding:.22rem .6rem; background:var(--vh-blue-l); border-bottom:1px solid var(--vh-blue-b);
    color:var(--vh-ink); font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.03em; }

/* Панель позиций: забирает всё оставшееся место, таблица прокручивается внутри */
.vh-card-pos { display:flex; flex-direction:column; flex:1 1 auto; min-height:110px; }
.vh-card-pos > .vh-rest-body { flex:1 1 auto; min-height:0; display:flex; flex-direction:column; overflow:hidden; }
.vh-scroll { flex:1 1 auto; min-height:0; overflow:auto; }

.vh-rest-body { padding:.35rem .6rem; overflow:auto; }
/* Список фирм в окне выбора поставщика */
.vh-firms5 { max-height:260px; overflow:auto; margin-top:.4rem; }
.vh-firms5 .a-grid { width:100%; }

/* Окно выбора поставщика: затемнение и карточка по центру */
.vh-modal { display:none; position:fixed; inset:0; z-index:1000; background:rgba(27,92,114,.28);
    align-items:center; justify-content:center; }
.vh-modal.on { display:flex; }
.vh-modal-box { background:#fff; border:1px solid #c5e7f2; border-radius:.6rem; min-width:420px; max-width:92vw;
    --vh-blue:#40B3D8; --vh-blue-b:#c5e7f2; --vh-ink:#1b5c72;
    box-shadow:0 10px 30px rgba(27,92,114,.28); overflow:hidden;
    font:13px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d; }
.vh-modal-head { display:flex; align-items:center; justify-content:space-between; gap:1rem;
    padding:.4rem .7rem; background:#eaf7fc; border-bottom:1px solid #c5e7f2;
    color:#1b5c72; font-weight:600; font-size:.78rem; text-transform:uppercase; letter-spacing:.03em; }
.vh-modal-body { padding:.6rem .7rem; }

/* Вкладки: накладные / резервы / договоры. Высота постоянная (--vh-tabs-h) — как раз пять строк
   таблицы; длинные списки прокручиваются внутри */
.vh-tabs { flex:0 0 auto; height:var(--vh-tabs-h); display:flex; flex-direction:column; overflow:hidden; }
.vh-tabs-bar { display:flex; flex-wrap:wrap; gap:.2rem; padding:.2rem .4rem; background:var(--vh-blue-l);
    border-bottom:1px solid var(--vh-blue-b); flex:0 0 auto; }
.vh-tab { padding:.25rem .7rem; background:transparent; border:1px solid transparent; border-radius:.35rem .35rem 0 0;
    color:#5b8598; font:600 .72rem/1.2 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; cursor:pointer; }
.vh-tab:hover { color:var(--vh-ink); }
.vh-tab.on { background:#fff; border-color:var(--vh-blue-b); border-bottom-color:#fff; color:var(--vh-ink); }
.vh-tab-body { display:none; padding:.35rem .6rem; }
.vh-tab-body.on { display:block; flex:1 1 auto; min-height:0; overflow:auto; }
/* Панель, разделённая на две равные колонки (данные счёта: реквизиты / деньги) */
.vh-side-split { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:.5rem 1rem; }
.vh-side-col { min-width:0; overflow:hidden; }

/* Строки «подпись + поле»: подпись в своей колонке, поле тянется за ней */
.vh-rows, .vh-search { display:grid; grid-template-columns:auto minmax(0,1fr); gap:.2rem .5rem; align-items:center; }
.vh-rows.two { grid-template-columns:auto minmax(0,1fr) auto minmax(0,1fr); column-gap:.9rem; }
.vh-lbl { font-size:.72rem; color:#5b8598; white-space:nowrap; }
.vh-ctl { display:flex; align-items:center; gap:.25rem; min-width:0; }
.vh-ctl > .a-txt { flex:1 1 auto; min-width:0; }
.vh-ctl .a-txt input, .vh-ctl .a-txt select { width:100%; height:21px; padding:0 .3rem; }
.vh-num { flex:0 0 auto; }
.vh-num input { width:72px; }
/* Деньги: подписи и значения прижаты вправо, так числа читаются в столбик */
.vh-money .vh-lbl { text-align:right; }
.vh-money .a-txt input { text-align:right; }
.vh-hint { font-size:.7rem; color:#5b8598; margin-top:.2rem; }

/* Флажки: пары «флажок + подпись», номер заказа ФЗ-275 держится рядом со своим флажком */
.vh-checks { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem 1.2rem; }
/* Флажки в данных счёта — две колонки, по три в каждой */
.vh-checks.two { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.35rem 1rem; }
.vh-check { display:inline-flex; align-items:center; gap:.35rem; font-size:.8rem; cursor:pointer; white-space:nowrap; }
.vh-check input:disabled + span { color:#9db9c4; }
/* Переключатели (тип счёта, «кто забирает») — тем же видом, что флажки */
.vh-radio { display:inline-flex; align-items:center; gap:.35rem; font-size:.8rem; cursor:pointer; white-space:nowrap; margin:0; }
.vh-radio input { accent-color:var(--vh-blue); margin:0; }
.vh-radio input:disabled + span { color:#9db9c4; }
.vh-radios { display:flex; flex-wrap:wrap; gap:.2rem .9rem; margin-top:.15rem; }
/* Узкое поле для свободного ввода (даты массовой установки) */
.vh-inp-xs { width:96px; }
/* Свободные поля в панелях (не привязанные к записи счёта) */
.vh-inp { height:22px; padding:0 .3rem; border:1px solid var(--vh-blue-b); border-radius:.25rem;
    background:#fff; color:#20414d; font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; box-sizing:border-box; }
.vh-inp:focus { outline:none; border-color:var(--vh-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
/* Пары «подпись + значение» в шапке (дата, кто, ShcID) */
.vh-stamp .vh-pair { display:inline-flex; align-items:center; gap:.25rem; }
.vh-check-pair { display:inline-flex; align-items:center; gap:.35rem; }
/* Ячейка сетки «подпись + поле» на всю ширину (блок флажков под полями) */
.vh-span { grid-column:1/-1; }

/* Кнопки-действия над таблицами */
.vh-tools { display:flex; flex-wrap:wrap; align-items:center; gap:.25rem; margin-bottom:.3rem; }
.vh-card-pos .vh-tools { flex:0 0 auto; }
.vh-search { margin-bottom:.3rem; max-width:460px; }

/* Узкий экран: колонки складываются в столбец, фиксированные высоты снимаются */
@media (max-width:1199.98px) {
    .vh-card { height:auto; min-height:100vh; overflow:visible; }
    .vh-card-row { grid-template-columns:minmax(0,1fr); height:auto; }
    .vh-card-col { overflow:visible; }
    .vh-card-pos { flex:0 0 auto; }
    .vh-card-pos > .vh-rest-body { overflow:visible; }
    .vh-tabs { height:auto; }
    .vh-tab-body.on { overflow:visible; }
    .vh-scroll { max-height:none; overflow:visible; }
}

/* Кнопки и поля внутри карточки и окна выбора — в корпоративных цветах */
.vh-card .a-btn, .vh-modal-box .a-btn { display:inline-flex; align-items:center; justify-content:center; min-height:22px; padding:0 .45rem;
    background:#fff; border:1px solid var(--vh-blue-b); border-radius:.3rem; color:var(--vh-ink);
    font:600 .72rem/1 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; cursor:pointer; white-space:nowrap; }
.vh-card .a-btn:hover:not(:disabled), .vh-modal-box .a-btn:hover:not(:disabled) { background:var(--vh-blue); border-color:var(--vh-blue); color:#fff; }
.vh-card .a-btn:disabled, .vh-modal-box .a-btn:disabled { background:#f7fbfd; border-color:#e3f0f5; color:#9db9c4; cursor:default; }
.vh-card .a-txt input, .vh-card .a-txt textarea, .vh-card .a-txt select,
.vh-modal-box .a-txt input, .vh-modal-box .a-txt select {
    border:1px solid var(--vh-blue-b); border-radius:.25rem; background:#fff; color:#20414d;
    font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; }
.vh-card .a-txt input:focus, .vh-card .a-txt select:focus,
.vh-modal-box .a-txt input:focus { outline:none; border-color:var(--vh-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
.vh-card .a-txt input:read-only { background:#fbfdfe; }
.vh-card .a-lbl, .vh-modal-box .a-lbl { color:var(--vh-ink); }
.vh-card .a-opt { accent-color:var(--vh-blue); }

/* Окна поверх карточки (флажки, выбор, сообщения): msgbox.js, picker.js, access-dialog.js
   ставят класс vh-modern-dlg — их оформление общее, в css/dlg-modern.css. */
CSS);

// Вкладки: переключение без перезагрузки. Ширины столбцов скрытых таблиц уже заданы в colgroup,
// поэтому colresize их не теряет.
$this->registerJs(<<<JS
document.querySelectorAll('.vh-tabs').forEach(box => {
    const tabs = [...box.querySelectorAll('.vh-tab')];
    const bodies = [...box.querySelectorAll('.vh-tab-body')];
    tabs.forEach(t => t.addEventListener('click', () => {
        const i = t.dataset.tab;
        tabs.forEach(x => { const on = x.dataset.tab === i; x.classList.toggle('on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
        bodies.forEach(b => b.classList.toggle('on', b.dataset.tabBody === i));
    }));
});
JS, \yii\web\View::POS_END);
?>
