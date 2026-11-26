<?php
/**
 * Статистика (frmSt) — современный вид: фильтры сверху, список элементов, футер, аналоги.
 *
 * Колонки списка в Access задавала подформа frmMinimum, а их состав и ширину менял
 * fnRepaintFormStat при смене «Вида» (каждый режим — своя процедура xp_frmStNewRS…).
 * Поэтому таблица строится по фактическим полям, которые вернула процедура:
 * известные поля получают русские подписи и ширину, служебные скрываются.
 *
 * @var yii\web\View $this
 * @var app\modules\zakupki\models\StatFilter $filter
 * @var array $vids
 * @var array $rows
 * @var string|null $error
 * @var string|null $note
 * @var array $footer  граница цены, разница, курс (StatService::footer)
 * @var array $hints   подсказки по колонкам: поле => текст (StatService::hints)
 */

use app\modules\zakupki\services\StatService;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;

$this->title = 'Статистика';

// Колонки — по первой строке результата: состав у каждого вида свой
$cols = StatService::columns($rows);

$hasDateFilter = $filter->dateS || $filter->datePo;
// Ссылки собираем от текущих значений фильтра, чтобы смена одного параметра не теряла остальные.
// Пустые значения выбрасываем, иначе в адресе копятся «?fnd=&city=&post=…».
$attrs = array_filter($filter->getAttributes(), static fn($v) => $v !== '' && $v !== null);
$base = ['index'] + $attrs;
$noDates = ['index'] + array_diff_key($attrs, ['dateS' => 1, 'datePo' => 1]);
$noSort = ['index'] + array_merge($attrs, ['sort' => '', 'dir' => 'asc']);
?>
<div class="st-modern">
    <header class="st-head">
        <div class="st-head-title">
            <h1>Статистика</h1>
            <div class="st-sub">Строк: <b id="stCount"><?= count($rows) ?></b>
                <span class="st-upd" id="stUpd" title="Список перечитывается сам каждые 9 минут, как в Access"></span>
            </div>
        </div>
        <div class="st-head-actions">
            <button type="button" class="st-btn" id="stRefresh" title="Перечитать список сейчас">⟳ Обновить</button>
            <a class="st-btn" href="<?= Url::home() ?>" title="Закрыть (на главную)">✕ Закрыть</a>
        </div>
    </header>

    <div id="stMsg">
        <?php foreach ($filter->getFirstErrors() as $e): ?>
            <div class="st-alert"><?= Html::encode($e) ?></div>
        <?php endforeach ?>
        <?php if ($error): ?>
            <div class="st-alert"><?= Html::encode($error) ?></div>
        <?php endif ?>
        <?php if ($note): ?>
            <div class="st-note"><?= Html::encode($note) ?></div>
        <?php endif ?>
    </div>

    <form method="get" action="<?= Url::to(['index']) ?>" id="frmSt" class="st-form">
        <?= Html::hiddenInput('sort', $filter->sort) ?>
        <?= Html::hiddenInput('dir', $filter->dir) ?>

        <section class="st-filters">
            <div class="st-fgroup">
                <div class="st-fgroup-t">Вид</div>
                <div class="st-frow">
                    <select class="st-inp" name="vid" onchange="this.form.submit()">
                        <?php if (!$vids): ?>
                            <option value="<?= Html::encode($filter->vid) ?>"><?= Html::encode($filter->vid) ?></option>
                        <?php endif ?>
                        <?php foreach ($vids as $v): ?>
                            <option value="<?= Html::encode($v['Num'] ?? '') ?>"
                                <?= (string)($v['Num'] ?? '') === (string)$filter->vid ? 'selected' : '' ?>>
                                <?= Html::encode($v['Name'] ?? '') ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
            </div>

            <div class="st-fgroup">
                <div class="st-fgroup-t">Поиск</div>
                <div class="st-frow">
                    <input class="st-inp st-inp-w" type="text" name="fnd" maxlength="100"
                        value="<?= Html::encode($filter->fnd) ?>" placeholder="часть наименования"
                        title="Ищется по части текста, как FindElem в Access">
                    <button type="submit" class="st-btn" title="Найти">🔍</button>
                </div>
            </div>

            <div class="st-fgroup">
                <div class="st-fgroup-t">Поставщик и город</div>
                <div class="st-frow">
                    <input class="st-inp st-inp-s" type="text" name="post" maxlength="100"
                        value="<?= Html::encode($filter->post) ?>" placeholder="поставщик">
                    <input class="st-inp st-inp-s" type="text" name="city" maxlength="100"
                        value="<?= Html::encode($filter->city) ?>" placeholder="город">
                </div>
            </div>

            <div class="st-fgroup">
                <div class="st-fgroup-t">Дата документа</div>
                <div class="st-frow">
                    <span class="st-lbl">с</span>
                    <input class="st-inp" type="date" name="dateS" value="<?= Html::encode($filter->dateS) ?>">
                    <span class="st-lbl">по</span>
                    <input class="st-inp" type="date" name="datePo" value="<?= Html::encode($filter->datePo) ?>">
                    <?php if ($hasDateFilter): ?>
                        <a class="st-btn" href="<?= Url::to($noDates) ?>" title="Сбросить даты">✕</a>
                    <?php endif ?>
                </div>
            </div>

            <?php /* TypeSel: в Access fnRepaintFormStat прячет фильтр «Сырьё» для вида 7 */ ?>
            <div class="st-fgroup"<?= $footer['is7'] ? ' hidden' : '' ?>>
                <div class="st-fgroup-t">Сырьё</div>
                <label class="st-radio"><input type="radio" name="typeSel" value="1"
                    <?= (int)$filter->typeSel === 1 ? 'checked' : '' ?> onchange="this.form.submit()">НЕ давальческое</label>
                <label class="st-radio"><input type="radio" name="typeSel" value="2"
                    <?= (int)$filter->typeSel === 2 ? 'checked' : '' ?> onchange="this.form.submit()">Давальческое</label>
                <label class="st-radio"><input type="radio" name="typeSel" value="3"
                    <?= (int)$filter->typeSel === 3 ? 'checked' : '' ?> onchange="this.form.submit()">Не важно</label>
            </div>

            <div class="st-fgroup">
                <div class="st-fgroup-t">Аналоги</div>
                <label class="st-radio"><input type="radio" name="analog" value="0"
                    <?= (int)$filter->analog === 0 ? 'checked' : '' ?> onchange="this.form.submit()">Обычные</label>
                <label class="st-radio"><input type="radio" name="analog" value="1"
                    <?= (int)$filter->analog === 1 ? 'checked' : '' ?> onchange="this.form.submit()">По аналогу</label>
            </div>

            <div class="st-fgroup">
                <div class="st-fgroup-t">Прочее</div>
                <label class="st-check"><input type="checkbox" name="fz275" value="1"
                    <?= (int)$filter->fz275 === 1 ? 'checked' : '' ?> onchange="this.form.submit()">ФЗ-275</label>
                <button type="submit" class="st-btn" title="Применить фильтры">Применить</button>
            </div>
        </section>

        <section class="st-panel">
            <div class="st-panel-head">
                <span>Список элементов</span>
                <?php if ($filter->sort !== ''): ?>
                    <span class="st-tools">
                        Сортировка: <?= Html::encode(StatService::label($filter->sort)) ?>
                        <?= $filter->dir === 'desc' ? '▼' : '▲' ?>
                        <a class="st-btn st-btn-xs" href="<?= Url::to($noSort) ?>"
                            title="Без сортировки (как в Access, когда все переключатели выключены)">✕</a>
                    </span>
                <?php endif ?>
            </div>

            <div class="st-list-scroll">
                <table class="st-list" id="stList">
                    <colgroup>
                        <?php foreach ($cols as [$f, , $w]): ?>
                            <col<?= $w ? ' style="width:' . (int)$w . 'px"' : '' ?>>
                        <?php endforeach ?>
                    </colgroup>
                    <thead>
                    <tr>
                        <?php foreach ($cols as [$f, $title, , $right]):
                            $next = ($filter->sort === $f && $filter->dir === 'asc') ? 'desc' : 'asc';
                            $cls = $filter->sort === $f ? ($filter->dir === 'desc' ? 'desc' : 'asc') : ''; ?>
                            <th class="<?= $cls ?>" title="Сортировать">
                                <a href="<?= Url::to(array_merge($base, ['sort' => $f, 'dir' => $next])) ?>"><?= Html::encode($title) ?></a>
                            </th>
                        <?php endforeach ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?= $this->render('_rows', [
                        'rows'  => $rows,
                        'cols'  => $cols,
                        'bnd'   => $footer['bnd'],
                        'empty' => $error ? 'Список не загружен' : 'Нет записей, удовлетворяющих условию',
                    ]) ?>
                    </tbody>
                </table>
            </div>

            <?php /*
              Футер подформы frmMinimum: PriceBound, KursUSD, Razn, WarnTS.
              В Access граница правилась по двойному щелчку (InputBox), здесь — обычное поле:
              Enter или «Применить» перечитывает список с новыми Bnd/Razn/Kurs (Razn_AfterUpdate).
            */ ?>
            <div class="st-foot">
                <?php if (!$footer['isB']): ?>
                    <label class="st-foot-f">
                        <span><?= $footer['dl'] ? 'Граница, &#36;' : 'Граница, ₽' ?></span>
                        <input class="st-inp st-inp-n" type="number" step="0.01" name="bnd"
                            value="<?= Html::encode(number_format($footer['bnd'], 2, '.', '')) ?>"
                            title="Цена от этой границы выделяется в списке; в Access задавалась двойным щелчком">
                    </label>
                    <input type="hidden" name="dl" value="<?= (int)$footer['dl'] ?>">

                    <label class="st-foot-f">
                        <span>Курс</span>
                        <input class="st-inp st-inp-n" type="text" name="kurs"
                            value="<?= Html::encode(number_format($footer['kurs'], 4, '.', '')) ?>" readonly
                            title="Курс доллара на экране учёта (Forms!frmUchet!KursUSD)">
                    </label>
                <?php endif ?>

                <?php if ($footer['is7']): ?>
                    <label class="st-foot-f">
                        <span>Разница</span>
                        <input class="st-inp st-inp-n" type="number" step="0.01" name="razn"
                            value="<?= Html::encode(number_format($footer['razn'], 2, '.', '')) ?>"
                            title="Разница цены для вида 7">
                    </label>
                <?php else: ?>
                    <input type="hidden" name="razn" value="<?= Html::encode($filter->razn) ?>">
                <?php endif ?>

                <?php if ($footer['warn']): ?>
                    <span class="st-foot-warn" title="Вид 1 учитывает тех.запас, остальные — нет">Выборка без учёта тех.запаса</span>
                <?php endif ?>

                <button type="submit" class="st-btn" title="Перечитать список с новыми значениями">Применить</button>
            </div>
            <?php /* Descr в frmSt: подсказка по колонке, на которой стоит курсор */ ?>
            <div class="st-hint" id="stHint" role="status" aria-live="polite"></div>
        </section>
    </form>

    <section class="st-panel st-an">
        <div class="st-panel-head">
            <span>Аналоги <span class="st-an-code" id="stAnCode"></span></span>
            <span class="st-tools" id="stAnInfo">выберите строку в списке</span>
        </div>
        <div class="st-list-scroll st-an-scroll" id="stAnBox"></div>
    </section>

    <?php /* Диалог текста: frmShowPrim (ветки 58/81/83) — примечание элемента, комментарий производства, просмотр */ ?>
    <div class="st-dlg" id="stDlg" hidden>
        <div class="st-dlg-box" role="dialog" aria-modal="true" aria-labelledby="stDlgT">
            <div class="st-dlg-head">
                <span id="stDlgT"></span>
                <button type="button" class="st-btn st-btn-xs" id="stDlgX" title="Закрыть">✕</button>
            </div>
            <div class="st-dlg-body">
                <textarea id="stDlgText" spellcheck="false"></textarea>
                <select id="stDlgSel" size="12" hidden></select>
            </div>
            <div class="st-dlg-foot">
                <span class="st-dlg-msg" id="stDlgMsg"></span>
                <button type="button" class="st-btn" id="stDlgOk">Сохранить</button>
                <button type="button" class="st-btn" id="stDlgNo">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<?php
$this->registerCss(<<<'CSS'
.st-modern { --st-blue:#40B3D8; --st-blue-d:#2f8baa; --st-blue-l:#eaf7fc; --st-blue-b:#c5e7f2; --st-ink:#1b5c72;
    background:#f4fafc; padding:.4rem .7rem .6rem; box-sizing:border-box;
    font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d; min-height:calc(100vh - 92px); }
#main > .container.st-wide { max-width:100%; width:100%; padding-left:.4rem; padding-right:.4rem; }

.st-head { display:flex; align-items:center; flex-wrap:wrap; gap:.4rem 1rem; padding:.35rem .7rem;
    margin:-.4rem -.7rem .4rem; background:#fff; border-bottom:3px solid var(--st-blue); }
.st-head-title h1 { margin:0; color:var(--st-ink); font-size:1rem; font-weight:600; }
.st-sub { font-size:.72rem; color:#5b8598; margin-top:.1rem; }
.st-upd { margin-left:.4rem; color:#7d9aa7; }
.st-head-actions { display:flex; align-items:center; gap:.3rem; margin-left:auto; }

.st-alert { background:#fdecec; color:#a4232b; border:1px solid #f5c2c7; border-radius:.4rem;
    padding:.25rem .6rem; margin-bottom:.4rem; }
.st-note { background:#fff8e6; color:#7a5a00; border:1px solid #f0dfa8; border-radius:.4rem;
    padding:.25rem .6rem; margin-bottom:.4rem; }

.st-filters { display:flex; flex-wrap:wrap; gap:.4rem; align-items:stretch; margin-bottom:.4rem; }
.st-fgroup { background:#fff; border:1px solid var(--st-blue-b); border-radius:.5rem; padding:.3rem .55rem .4rem;
    box-shadow:0 1px 3px rgba(64,179,216,.10); }
.st-fgroup-t { color:var(--st-ink); font-weight:600; font-size:.68rem; text-transform:uppercase;
    letter-spacing:.03em; margin-bottom:.25rem; }
.st-frow { display:flex; align-items:center; flex-wrap:wrap; gap:.3rem; }
.st-lbl { font-size:.72rem; color:#5b8598; white-space:nowrap; }
.st-check, .st-radio { display:inline-flex; align-items:center; gap:.3rem; font-size:.78rem;
    cursor:pointer; white-space:nowrap; margin:0 .8rem 0 0; }
.st-check input, .st-radio input { accent-color:var(--st-blue); margin:0; }

.st-btn { display:inline-flex; align-items:center; justify-content:center; gap:.25rem; min-height:24px; padding:0 .55rem;
    background:#fff; border:1px solid var(--st-blue-b); border-radius:.3rem; color:var(--st-ink);
    font:600 .72rem/1 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; cursor:pointer;
    white-space:nowrap; text-decoration:none; }
.st-btn:hover { background:var(--st-blue); border-color:var(--st-blue); color:#fff; }
.st-btn-xs { min-height:20px; padding:0 .35rem; font-size:.72rem; }
.st-inp { height:24px; padding:0 .35rem; border:1px solid var(--st-blue-b); border-radius:.25rem;
    background:#fff; color:#20414d; font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; box-sizing:border-box; }
.st-inp:focus { outline:none; border-color:var(--st-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
.st-inp-w { width:180px; } .st-inp-s { width:130px; } .st-inp-n { width:96px; text-align:right; }

.st-panel { background:#fff; border:1px solid var(--st-blue-b); border-radius:.5rem;
    box-shadow:0 1px 3px rgba(64,179,216,.12); overflow:hidden; }
.st-panel-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem;
    padding:.22rem .6rem; background:var(--st-blue-l); border-bottom:1px solid var(--st-blue-b);
    color:var(--st-ink); font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.03em; }
.st-tools { display:flex; align-items:center; gap:.35rem; text-transform:none; letter-spacing:0; font-weight:400; }

.st-list-scroll { overflow:auto; max-height:calc(100vh - 300px); }
.st-list { border-collapse:collapse; width:100%; font-size:12px; }
.st-list th { position:sticky; top:0; z-index:1; background:var(--st-blue-l); color:var(--st-ink);
    font-weight:600; font-size:.68rem; text-transform:uppercase; letter-spacing:.02em;
    padding:.2rem .35rem; border:1px solid var(--st-blue-b); white-space:nowrap; text-align:left; }
.st-list th a { color:inherit; text-decoration:none; }
.st-list th.asc::after { content:' ▲'; } .st-list th.desc::after { content:' ▼'; }
.st-list td { padding:.12rem .35rem; border-bottom:1px solid #e6f2f7;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:420px; }
.st-list tbody tr { cursor:default; }
.st-list tbody tr:hover td { background:#f4fafc; }
/* Текущая запись: в Access OnCurrent красит строку по Код = CurKd */
.st-list tbody tr.st-cur td { background:#d6ecf7; }
.st-empty { color:#7d9aa7; font-style:italic; padding:.5rem .6rem !important; }

/* Футер frmMinimum: граница цены, курс, разница, «без тех.запаса» */
.st-foot { display:flex; align-items:center; flex-wrap:wrap; gap:.3rem .8rem;
    padding:.3rem .6rem; background:var(--st-blue-l); border-top:1px solid var(--st-blue-b); }
.st-foot-f { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; color:var(--st-ink); margin:0; }
.st-foot-f > span { white-space:nowrap; }
.st-foot-warn { color:#c05a00; font-size:.72rem; font-weight:600; }

/* Строка подсказки под списком (в Access — Descr.Caption главной формы frmSt) */
.st-hint { min-height:22px; padding:.15rem .6rem; background:#fbfeff; border-top:1px solid #e6f2f7;
    color:#3f6d80; font-size:.72rem; line-height:1.35; }
.st-hint:empty::before { content:'Наведите курсор на ячейку — появится подсказка о цветах и обозначениях'; color:#9db9c4; }

/* Подформа аналогов (subFrmStAnalog): свой заголовок и полоса прокрутки */
.st-an { margin-top:.4rem; }
.st-an-code { text-transform:none; letter-spacing:0; font-weight:400; color:#5b8598; }
.st-an-scroll { max-height:220px; min-height:34px; padding:.1rem .2rem; }

/* Диалог текста (frmShowPrim): примечание, комментарий, выбор состояния */
.st-dlg { position:fixed; inset:0; z-index:1050; display:flex; align-items:center; justify-content:center;
    background:rgba(27,92,114,.28); }
.st-dlg[hidden] { display:none; }
.st-dlg-box { width:min(560px,94vw); background:#fff; border:1px solid var(--st-blue-b); border-radius:.5rem;
    box-shadow:0 12px 32px rgba(27,92,114,.28); display:flex; flex-direction:column; }
.st-dlg-head { display:flex; align-items:center; justify-content:space-between; gap:.5rem; padding:.3rem .6rem;
    background:var(--st-blue-l); border-bottom:1px solid var(--st-blue-b); color:var(--st-ink); font-weight:600; }
.st-dlg-body { padding:.5rem .6rem; }
.st-dlg-body textarea { width:100%; height:150px; box-sizing:border-box; resize:vertical; padding:.3rem .4rem;
    border:1px solid var(--st-blue-b); border-radius:.3rem; font:12px/1.45 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;
    color:#20414d; }
.st-dlg-body textarea:focus, .st-dlg-body select:focus { outline:none; border-color:var(--st-blue); box-shadow:0 0 0 2px rgba(64,179,216,.22); }
.st-dlg-body select { width:100%; border:1px solid var(--st-blue-b); border-radius:.3rem;
    font:12px system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif; color:#20414d; }
.st-dlg-foot { display:flex; align-items:center; gap:.35rem; padding:.35rem .6rem;
    background:var(--st-blue-l); border-top:1px solid var(--st-blue-b); }
.st-dlg-msg { margin-right:auto; color:#a4232b; }
.st-dlg-msg.ok { color:#1b7a3d; }

@media (max-width:1199.98px) { .st-list-scroll { max-height:none; } }
CSS);

$this->registerJs('window.ST = ' . Json::encode([
    'rowsUrl' => Url::to(['rows']),
    'analogsUrl' => Url::to(['analogs']),        // аналоги текущего элемента (subFrmStAnalog)
    'statusesUrl' => Url::to(['statuses']),      // состояния проблемы (frmfndStatus, OpenArgs 25)
    'actUrl' => Url::to(['act']),                // сохранение примечания / комментария / состояния
    'cardUrl' => Url::to(['/sklad/elem/view']),   // карточка элемента (Fobnovlen1) — модуль «Склад»
    'vid'     => (string)$filter->vid,           // текущий вид: часть двойных щелчков зависит от него
    'canProblEl' => Yii::$app->user->can('ProblEl', ['withMe' => false]),
    'hints'   => $hints,                         // подсказки по колонкам (Descr.Caption в Access)
    'period'  => 540000,            // TimerInterval = 540000 мс (9 минут), как в frmMinimum
]) . ';', \yii\web\View::POS_BEGIN);

$this->registerJs(<<<'JS'
/**
 * Автообновление списка «Статистики» — Form_Timer формы frmMinimum (TimerInterval = 540000).
 *
 * Что делает: раз в 9 минут перечитывает список с теми же фильтрами и возвращается
 * к текущей записи по «Код» (в Access: запоминает txtID, перезапрашивает, ищет запись заново).
 * Обновляется только тело таблицы — фильтры и ввод в полях не трогаются.
 */
(function () {
    const cfg = window.ST || {};
    const tbl = document.getElementById('stList');
    if (!tbl || !cfg.rowsUrl) return;

    const body   = tbl.tBodies[0];
    const scroll = tbl.closest('.st-list-scroll');
    const count  = document.getElementById('stCount');
    const upd    = document.getElementById('stUpd');
    const msg    = document.getElementById('stMsg');
    const btn    = document.getElementById('stRefresh');
    const period = cfg.period || 540000;

    let cur = '';            // Код текущей записи
    let busy = false;
    let last = Date.now();

    const setCur = code => {
        cur = code || '';
        [...body.rows].forEach(tr => tr.classList.toggle('st-cur', !!cur && tr.dataset.code === cur));
        loadAnalogs(cur);
    };

    /**
     * Аналоги текущего элемента — подформа аналогов (subFrmStAnalog).
     * В Access подформа сама перезапрашивалась на Form_Current: exec xp_frmStRS1Analog(Me![Код]).
     */
    async function loadAnalogs(code) {
        const box  = document.getElementById('stAnBox');
        const info = document.getElementById('stAnInfo');
        const lab  = document.getElementById('stAnCode');
        if (!box || !cfg.analogsUrl) return;

        if (!code) {
            box.replaceChildren();
            if (lab) lab.textContent = '';
            if (info) info.textContent = 'выберите строку в списке';
            return;
        }

        if (lab) lab.textContent = '№ ' + code;
        if (info) info.textContent = 'загрузка…';
        try {
            const r = await fetch(cfg.analogsUrl + '?id=' + encodeURIComponent(code),
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            if (cur !== code) return;                    // пока грузили, выбрали другую запись
            box.innerHTML = d.html || '';
            if (info) info.textContent = d.error ? '' : (d.count ? 'строк: ' + d.count : 'аналогов нет');
        } catch (e) {
            if (cur !== code) return;
            if (info) info.textContent = '';
            const x = document.createElement('div');
            x.className = 'st-alert';
            x.textContent = 'Не удалось получить аналоги: ' + e.message;
            box.replaceChildren(x);
        }
    }

    // OnCurrent: щелчок по строке делает её текущей
    tbl.addEventListener('click', e => {
        if (tbl.dataset.resizing) return;               // тянули столбец — это не выбор записи
        const tr = e.target.closest('tbody tr[data-code]');
        if (tr) setCur(tr.dataset.code);
    });

    /**
     * Подсказка по колонке — Descr.Caption главной формы frmSt, который подставляли
     * обработчики *_GotFocus подформы frmMinimum. В вебе вместо фокуса ячейки — наведение:
     * у таблицы нет поячеечного фокуса, а смысл подсказки тот же.
     */
    const hint = document.getElementById('stHint');
    const hintFor = f => (cfg.hints && f && cfg.hints[f]) || '';

    const showHint = f => {
        if (!hint) return;
        const t = hintFor(f);
        hint.textContent = t || '';
    };

    tbl.addEventListener('mouseover', e => {
        const td = e.target.closest('tbody td[data-f]');
        showHint(td ? td.dataset.f : '');
    });
    tbl.addEventListener('mouseleave', () => showHint(''));

    // OpenKartElem: двойной щелчок по строке открывает карточку элемента (Fobnovlen1) в отдельном окне.
    // В Access это DoCmd.OpenForm "Fobnovlen1", ..., acDialog, Me![Код] — окно поверх списка.
    const openCard = code => {
        if (!cfg.cardUrl) return;
        window.open(cfg.cardUrl + '?id=' + encodeURIComponent(code), 'elem' + code);
    };

    // Колонки, у которых в Access обработчик двойного щелчка не задан (или он ничего не делает)
    const NOOP_FIELDS = new Set(['LowR', 'NotRec', 'VP5', 'NoAnalog', 'KolPok', 'FZ275Kol',
        'RequestSch', 'IDInvoice', 'OrderKat', 'chk', 'MainPlace', 'IDCursor']);

    const cellVal = (tr, f) => (tr.querySelector('td[data-f="' + f + '"]')?.textContent || '').trim();

    /**
     * Двойной щелчок по строке списка — маршрутизация по колонке, как набор обработчиков
     * *_DblClick подформы frmMinimum:
     *   Наименование, Номинал, Корпус, Изготовитель, Количество, Недополучено, txtSV, МинКоличество
     *                            → OpenKartElem (карточка элемента);
     *   ГдеКупил = «ПЕРЕЛОЖИТЬ НА СКЛАДЕ» → frmMoveEl (перенос, пока не перенесён — сообщаем);
     *   Код                      → frmfndStatus 25 (право ProblEl, вид 1/3, «Проблемы» в «ГдеКупил»),
     *                              иначе OpenKartElem;
     *   Аналог                   → frmShowPrim 58 — просмотр текста;
     *   Примечание               → frmShowPrim 83 + xp_SkladUpdPrim — правка текста;
     *   SostProblEl              → только вид «b»: комментарий производства (xp_SkladTrackingUpdComm);
     *   DfltIsp                  → только вид «b»: просмотр «Информация» (frmShowPrim 81).
     */
    tbl.addEventListener('dblclick', e => {
        if (tbl.dataset.resizing) return;
        const tr = e.target.closest('tbody tr[data-code]');
        if (!tr) return;
        const code = tr.dataset.code;
        const f = e.target.closest('td[data-f]')?.dataset.f || '';

        if (f === 'Analog') {
            if (cfg.vid !== 'b') showText('Аналог элемента', cellVal(tr, f));
            else openCard(code);
            return;
        }
        if (f === 'Примечание' || f === 'ПримечаниеДолг') {
            editText('Примечание элемента', cellVal(tr, f), 500, code, 'Prim');
            return;
        }
        if (f === 'SostProblEl') {
            // В Access: только для вида «b», у остальных обработчик пустой
            if (cfg.vid === 'b') editText('Комментарий производства', cellVal(tr, f), 1024, code, 'Comm');
            return;
        }
        if (f === 'DfltIsp') {
            // В Access: только для вида «b» (просмотр «Информация»), у остальных обработчик пустой
            if (cfg.vid === 'b') showText('Информация', cellVal(tr, f));
            return;
        }
        if (f === 'Код') {
            const problem = (cfg.vid === '1' || cfg.vid === '3') && cellVal(tr, 'ГдеКупил').includes('Проблемы');
            if (cfg.canProblEl && problem) { pickStatus(code); return; }
            openCard(code);
            return;
        }
        if (f === 'ГдеКупил') {
            // В Access: frmMoveEl с OpenArgs = Код & "3"; форма переноса ещё не перенесена
            if (cellVal(tr, f) === 'ПЕРЕЛОЖИТЬ НА СКЛАДЕ') {
                showText('Перенос элемента', 'Перенос элемента на склад (форма frmMoveEl) пока не перенесён в веб.');
                return;
            }
            openCard(code);
            return;
        }
        if (NOOP_FIELDS.has(f)) return;
        openCard(code);                                  // OpenKartElem — по умолчанию, как в Access
    });

    /* ---------- Диалог текста: frmShowPrim (ветки 58 — просмотр, 83 — правка) ---------- */
    const dlg   = document.getElementById('stDlg');
    const dlgT  = document.getElementById('stDlgT');
    const dlgTx = document.getElementById('stDlgText');
    const dlgSl = document.getElementById('stDlgSel');
    const dlgMs = document.getElementById('stDlgMsg');
    const dlgOk = document.getElementById('stDlgOk');
    const dlgNo = document.getElementById('stDlgNo');
    const dlgX  = document.getElementById('stDlgX');

    let dlgSubmit = null;        // что делать по «Сохранить»; null — режим просмотра

    const dlgMsg = (text, ok = false) => {
        if (!dlgMs) return;
        dlgMs.textContent = text || '';
        dlgMs.classList.toggle('ok', !!ok);
    };

    function closeDlg() {
        if (dlg) dlg.hidden = true;
        dlgSubmit = null;
        dlgMsg('');
    }

    function openDlg(title, mode) {
        if (!dlg) return false;
        if (dlgT) dlgT.textContent = title || '';
        const readOnly = mode === 'text-ro' || mode === 'list';
        if (dlgTx) dlgTx.hidden = mode !== 'text' && mode !== 'text-ro';
        if (dlgSl) dlgSl.hidden = mode !== 'list';
        if (dlgOk) dlgOk.hidden = readOnly;
        dlgMsg('');
        dlg.hidden = false;
        return true;
    }

    function showText(title, text) {
        if (!openDlg(title, 'text-ro') || !dlgTx) return;
        dlgTx.value = text || '';
        dlgTx.setAttribute('readonly', 'readonly');
        dlgTx.focus();
        dlgTx.setSelectionRange(0, 0);
    }

    function editText(title, text, max, code, act) {
        if (!openDlg(title, 'text') || !dlgTx) return;
        dlgTx.value = text || '';
        dlgTx.removeAttribute('readonly');
        dlgTx.maxLength = max || 500;
        dlgTx.focus();
        dlgTx.setSelectionRange(0, 0);
        dlgSubmit = async () => {
            // CommonStartSP6 Warn = 1: Access предупреждает, что текст уменьшится, но сохраняет
            const cut = dlgTx.value.slice(0, max || 500);
            const res = await postAct({ act, id: code, text: cut });
            if (!res.ok) { dlgMsg(res.error || 'Не удалось сохранить.'); return; }
            closeDlg();
            refresh();                                   // Form_Timer / StndOnLne: перечитать список
        };
    }

    /** Выбор состояния проблемы: frmfndStatus (OpenArgs 25) → xp_StatWorkWithProblemUpdSost. */
    async function pickStatus(code) {
        if (!openDlg('Выбор состояния', 'list') || !dlgSl) return;
        if (dlgOk) dlgOk.hidden = true;                  // пока список не загружен, сохранять нечего
        dlgSl.replaceChildren();
        dlgMsg('загрузка…');
        let items = [];
        try {
            const r = await fetch(cfg.statusesUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const d = await r.json();
            if (d.error) { dlgMsg(d.error); return; }
            items = d.items || [];
        } catch (err) {
            dlgMsg('Не удалось получить список состояний: ' + err.message);
            return;
        }
        if (!items.length) { dlgMsg('Список состояний пуст.'); return; }
        dlgMsg('');
        if (dlgOk) dlgOk.hidden = false;
        dlgSl.replaceChildren(...items.map(it => {
            const o = document.createElement('option');
            o.value = it.id;
            o.textContent = it.name;
            return o;
        }));
        dlgSl.focus();
        dlgSubmit = async () => {
            const sost = dlgSl.value;
            if (!sost) { dlgMsg('Выберите состояние.'); return; }
            const res = await postAct({ act: 'Sost', id: code, sost });
            if (!res.ok) { dlgMsg(res.error || 'Не удалось изменить состояние.'); return; }
            closeDlg();
            refresh();
        };
    }

    /** POST на StatController::actionAct с CSRF-токеном (как в остальных экранах проекта). */
    async function postAct(data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
        const cp = document.querySelector('meta[name=csrf-param]')?.content;
        if (cp) fd.append(cp, document.querySelector('meta[name=csrf-token]')?.content || '');
        try {
            const r = await fetch(cfg.actUrl, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            return await r.json();
        } catch (e) {
            return { ok: false, error: 'Не удалось связаться с сервером: ' + e.message };
        }
    }

    if (dlgOk) dlgOk.addEventListener('click', () => { if (dlgSubmit) dlgSubmit(); });
    if (dlgNo) dlgNo.addEventListener('click', closeDlg);
    if (dlgX)  dlgX.addEventListener('click', closeDlg);
    if (dlgSl) dlgSl.addEventListener('dblclick', () => { if (dlgSubmit) dlgSubmit(); });
    if (dlg)   dlg.addEventListener('click', e => { if (e.target === dlg) closeDlg(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && dlg && !dlg.hidden) closeDlg(); });

    const stamp = () => {
        const d = new Date();
        const p = n => String(n).padStart(2, '0');
        if (upd) upd.textContent = 'обновлено ' + p(d.getHours()) + ':' + p(d.getMinutes());
    };

    async function refresh() {
        if (busy) return;
        busy = true;
        if (btn) btn.disabled = true;
        const top = scroll ? scroll.scrollTop : 0;
        try {
            const r = await fetch(cfg.rowsUrl + location.search, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            body.innerHTML = d.html || '';
            showHint('');                                // строки новые — старая подсказка уже не про них
            if (count) count.textContent = d.count ?? 0;
            if (msg) {
                const box = el => { const x = document.createElement('div'); x.className = el.cls; x.textContent = el.text; return x; };
                msg.replaceChildren(...[d.error && box({ cls: 'st-alert', text: d.error }),
                                        d.note && box({ cls: 'st-note', text: d.note })].filter(Boolean));
            }
            setCur(cur);                                 // вернулись к той же записи по Код
            const tr = cur ? [...body.rows].find(r => r.dataset.code === cur) : null;
            if (tr) tr.scrollIntoView({ block: 'nearest' });
            else if (scroll) scroll.scrollTop = top;
            last = Date.now();
            stamp();
        } catch (e) {
            if (msg) {
                const x = document.createElement('div');
                x.className = 'st-alert';
                x.textContent = 'Не удалось обновить список: ' + e.message;
                msg.replaceChildren(x);
            }
            if (upd) upd.textContent = 'обновление не удалось';
        } finally {
            busy = false;
            if (btn) btn.disabled = false;
        }
    }

    // Вкладка не видна — запрос не шлём; вернулись и срок прошёл — обновляем сразу
    setInterval(() => {
        if (document.hidden) return;
        if (Date.now() - last < period - 1000) return;
        refresh();
    }, 30000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - last >= period) refresh();
    });
    if (btn) btn.addEventListener('click', refresh);
})();
JS, \yii\web\View::POS_END);

// Экран на всю ширину окна
$this->registerJs(<<<'JS'
document.getElementById('main')?.querySelector(':scope > .container')?.classList.add('st-wide');
JS, \yii\web\View::POS_END);

echo $this->render('@app/views/access/_help');
