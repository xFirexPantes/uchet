<?php
/**
 * «Позиция счёта для деталей» (frmScetaDetalAdd) — окно поверх карточки счёта на детали.
 *
 * Обычная разметка: подписи и поля в сетке, координаты Access не используются.
 * Открывается в iframe (web/js/access-dialog.js). Поля сохраняются сразу после изменения
 * (AfterUpdate во временную запись), ✓ записывает позицию в счёт и закрывает окно.
 *
 * @var yii\web\View $this
 * @var int $id     счёт (отрицательный)
 * @var int $idPos  позиция (0 — новая)
 * @var array $row  временная запись из xp_ProdPlanUpdPosSch
 */
use app\components\AccessFormat as F;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Позиция счёта для деталей';
// Form_Open: подпись цены зависит от «Цена с НДС» счёта
$prcLabel = (int)($row['PrcWNDS'] ?? 0) === 0 ? 'Цена без НДС' : 'Цена';

$val = static fn(string $f, ?string $fmt = null, ?int $dp = null): string => F::value($row[$f] ?? null, $fmt, $dp);
$num = static fn(string $f): string => Html::encode($val($f, 'General Number', 2));

$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/dlg-modern.css');          // корпоративные цвета окна
$this->registerCss('body { background:#fff !important; }');  // макет dialog.php задаёт бежевый фон inline
$this->registerJsFile('@web/js/access-dialog.js');
// dlg-modern ставится на <html> только если родитель — современный экран или карточка счёта
$this->registerJs(<<<'JS'
try {
    if (window.parent !== window && window.parent.document.querySelector('.vh-modern, .vh-card')) {
        document.documentElement.classList.add('dlg-modern');
    }
} catch (e) { /* другой сайт или нет доступа — обычный вид Access */ }
JS, View::POS_HEAD);
?>
<div class="vh-dlg" id="frmScetaDetalAdd">
    <div class="vh-dlg-body">
        <div class="vh-dlg-name">
            <div class="vh-dlg-cell">
                <label class="vh-dlg-lbl" for="Nme">Номенклатура</label>
                <input class="vh-dlg-inp" type="text" id="Nme" readonly value="<?= Html::encode((string)($row['Nme'] ?? '')) ?>"
                    title="Двойной щелчок — выбрать из списка" style="cursor:pointer">
            </div>
            <div class="vh-dlg-cell">
                <label class="vh-dlg-lbl" for="FullNme">Точное наименование</label>
                <input class="vh-dlg-inp ed" type="text" id="FullNme" data-field="FullNme" data-kind="text"
                    value="<?= Html::encode((string)($row['FullNme'] ?? '')) ?>">
            </div>
        </div>

        <div class="vh-dlg-nums">
            <div class="vh-dlg-cell">
                <label class="vh-dlg-lbl" for="Kol">Количество</label>
                <input class="vh-dlg-inp r ed" type="text" id="Kol" data-field="Kol" data-kind="num" value="<?= $num('Kol') ?>">
            </div>
            <div class="vh-dlg-cell">
                <label class="vh-dlg-lbl" id="Prc_Надпись" for="Prc"><?= Html::encode($prcLabel) ?></label>
                <input class="vh-dlg-inp r ed" type="text" id="Prc" data-field="Prc" data-kind="num" value="<?= $num('Prc') ?>">
            </div>
            <div class="vh-dlg-cell">
                <label class="vh-dlg-lbl" for="NDS">Ставка НДС</label>
                <input class="vh-dlg-inp r ed" type="text" id="NDS" data-field="NDS" data-kind="num" value="<?= $num('NDS') ?>">
            </div>
            <div class="vh-dlg-cell">
                <label class="vh-dlg-lbl" for="Sm">Сумма</label>
                <input class="vh-dlg-inp r ed" type="text" id="Sm" data-field="Sm" data-kind="num" value="<?= $num('Sm') ?>">
            </div>
            <div class="vh-dlg-cell">
                <label class="vh-dlg-lbl" for="SmNDS">Сумма НДС</label>
                <input class="vh-dlg-inp r ed" type="text" id="SmNDS" data-field="SmNDS" data-kind="num" value="<?= $num('SmNDS') ?>">
            </div>
            <div class="vh-dlg-cell">
                <label class="vh-dlg-lbl" for="Vsego">Всего</label>
                <input class="vh-dlg-inp r ed" type="text" id="Vsego" data-field="Vsego" data-kind="num" value="<?= $num('Vsego') ?>">
            </div>
        </div>
    </div>

    <div class="vh-dlg-foot">
        <span class="vh-dlg-hint" id="Hlp">Количество и цена пересчитывают суммы. ✓ записывает позицию в счёт.</span>
        <span class="vh-dlg-btns">
            <button type="button" class="vh-dlg-btn" id="AccptRec" title="Сохранить позицию в счёт">✓ Сохранить</button>
            <button type="button" class="vh-dlg-btn" id="ClsFrm" title="Закрыть без сохранения">✕</button>
        </span>
    </div>

    <?php // Служебные поля — как в форме Access: их читают скрипты окна ?>
    <input type="hidden" id="ScID" value="<?= Html::encode((string)($row['ScID'] ?? '')) ?>">
    <input type="hidden" id="ID1C" value="<?= Html::encode((string)($row['ID1C'] ?? '')) ?>">
    <input type="hidden" id="Who" value="<?= Html::encode((string)($row['Who'] ?? '')) ?>">
    <input type="hidden" id="DT" value="<?= Html::encode((string)($row['DT'] ?? '')) ?>">
    <input type="hidden" id="IDPos" value="<?= Html::encode((string)($row['IDPos'] ?? $idPos)) ?>">
    <input type="hidden" id="ID" value="<?= Html::encode((string)($row['ID'] ?? $id)) ?>">
    <input type="hidden" id="PrcWNDS" value="<?= Html::encode((string)($row['PrcWNDS'] ?? '')) ?>">
    <input type="hidden" id="SkladID" value="<?= Html::encode((string)($row['SkladID'] ?? '')) ?>">
</div>
<?php
$saveUrl = Json::encode(Url::to(['pos-detal-save', 'id' => $id]));
$accUrl  = Json::encode(Url::to(['pos-detal-accept', 'id' => $id, 'pos' => $idPos]));
$nomUrl  = Json::encode(Url::to(['pos-detal-nomenkl', 'id' => $id]));
$fobUrl  = Json::encode(Url::to(['fobnov/view', 'id' => $id]));
$this->registerJs(<<<JS
(function () {
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    let queue = Promise.resolve();
    function post(url, data) {
        const fd = new FormData();
        Object.entries(data).forEach(([k, v]) => fd.append(k, v ?? ''));
        if (csrfParam) fd.append(csrfParam, csrfToken);
        const p = queue.then(() => fetch(url, { method: 'POST', body: fd }))
            .then(r => r.ok ? r.json() : r.json().catch(() => ({})).then(j => ({ ok: false, msg: j.message || ('Ошибка ' + r.status) })));
        queue = p.catch(() => {});
        return p;
    }
    // Поле сохраняется сразу после изменения (AfterUpdate); Kol и Prc пересчитывают суммы и переводят курсор дальше
    const next = { Kol: 'Prc', Prc: 'Sm' };
    document.querySelectorAll('.ed').forEach(el => {
        el.dataset.old = el.value;
        el.addEventListener('keydown', e => { if (e.key === 'Enter') el.blur(); });
        el.addEventListener('change', () => {
            el.classList.add('saving'); el.classList.remove('err');
            post($saveUrl, { field: el.dataset.field, value: el.value }).then(async res => {
                el.classList.remove('saving');
                if (res.set) Object.entries(res.set).forEach(([f, v]) => {
                    const t = document.querySelector('.ed[data-field="' + f + '"]'); if (t) { t.value = v; t.dataset.old = v; }
                });
                if (res.ok) {
                    el.dataset.old = el.value;
                    if (res.set && next[el.dataset.field]) document.querySelector('.ed[data-field="' + next[el.dataset.field] + '"]')?.focus();
                } else { el.value = el.dataset.old; el.classList.add('err'); }
                if (res.msg) await msgBox(res.msg);
            });
        });
    });
    document.getElementById('AccptRec').addEventListener('click', () => {
        document.activeElement?.blur();                       // сначала сохранить поле, где стоит курсор
        post($accUrl, {}).then(async res => {
            if (res.msg) await msgBox(res.msg);
            if (res.close) closeAccessDialog(true);
        });
    });
    // Nme_DblClick: окно fObnov (оно больше этого окна — открывается в главном), выбранный элемент → xp_ProdPlanUpdPosNomenkl, поля заново
    document.getElementById('Nme')?.addEventListener('dblclick', async () => {
        let dlg = accessDialog;
        try { if (window.top !== window && window.top.accessDialog) dlg = window.top.accessDialog; } catch (e) {}
        const f = await dlg($fobUrl, 1336, 505);
        if (!f.value) return;
        const res = await post($nomUrl, { sklad: f.value });
        if (res.set) Object.entries(res.set).forEach(([k, v]) => {
            const t = document.querySelector('.ed[data-field="' + k + '"]') || document.getElementById(k);
            if (t) { t.value = v; t.dataset.old = v; t.title = v; }
        });
        if (res.msg) await msgBox(res.msg);
    });
    document.getElementById('ClsFrm').addEventListener('click', () => closeAccessDialog(false));
    document.querySelector('.ed[data-field=FullNme]')?.focus();
})();
JS, View::POS_END);
