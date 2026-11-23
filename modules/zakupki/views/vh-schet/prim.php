<?php
/**
 * frmShowPrim «ввод текста» — диалог поверх «Входящих счетов». Два режима из frmStat:
 *   44 — «Примечание счёта» (✎ EditPrim): ✓ сохраняет сразу, xp_UpdatePrimSch (ID, текст до 500);
 *   83 — «Комментарий к счёту» (двойной щелчок по комментарию): ✓ сохраняет через xp_UpdateCommSch (ID, текст до 1024), ответ 2 — нет прав.
 * Ширина формы 6831 твипов = 455 px; область данных 2891 + примечание 651 = 236 px. Подпись N сверху, поле txt 43..189, внизу ✓ слева и ✕ справа.
 *
 * @var yii\web\View $this
 * @var int    $id    счёт (PredShcID)
 * @var int    $mode  44 | 83
 * @var string $text  текущий текст
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'ввод текста';
$caption = $mode === 44 ? 'Примечание счёта' : 'Комментарий к счёту';
$this->registerCssFile('@web/css/access-form.css');
$this->registerCssFile('@web/css/dlg-modern.css');          // корпоративные цвета, когда окно открыто поверх современного вида
$this->registerJsFile('@web/js/access-dialog.js');
// dlg-modern ставится на <html> только если родитель — современный экран (.vh-modern);
// скрипт в head: класс готов до отрисовки, окно не мигает бежевым
$this->registerJs(<<<'JS'
try {
    if (window.parent !== window && window.parent.document.querySelector('.vh-modern')) {
        document.documentElement.classList.add('dlg-modern');
    }
} catch (e) { /* другой сайт или нет доступа — обычный вид Access */ }
JS, \yii\web\View::POS_HEAD);
?>
<style>
#frmShowPrim #N{font:bold 13px Tahoma,sans-serif;display:flex;align-items:center;padding:0 4px}
#frmShowPrim textarea{width:100%;height:100%;box-sizing:border-box;border:1px inset #999;font:13px Tahoma,sans-serif;resize:none;overflow-y:auto}
#frmShowPrim .ft{position:absolute;left:0;top:193px;width:455px;height:43px;border-top:1px solid #aca899}
</style>
<div class="access-form" id="frmShowPrim" style="width:455px;height:236px">
    <div id="N" style="left:1px;top:0;width:454px;height:42px"><?= Html::encode($caption) ?></div>
    <div style="left:1px;top:43px;width:454px;height:146px"><textarea id="txt"><?= Html::encode($text) ?></textarea></div>
    <div class="ft"></div>
    <button type="button" class="a-btn" id="AcceptChanges" title="Отправить" style="left:4px;top:197px;width:38px;height:38px;font-size:16px">✓</button>
    <button type="button" class="a-btn" id="Kn6" title="Закрыть форму" style="left:408px;top:197px;width:38px;height:38px;font-size:16px">✕</button>
</div>
<?php
$actUrl = Json::encode(Url::to(['act', 'id' => $id]));
$act = Json::encode($mode === 44 ? 'Prim' : 'Comm');
$old = Json::encode($text);
$this->registerJs(<<<JS
(function () {
    const csrfParam = document.querySelector('meta[name=csrf-param]')?.content;
    const csrfToken = document.querySelector('meta[name=csrf-token]')?.content;
    const t = document.getElementById('txt');
    t.focus(); t.setSelectionRange(0, 0);
    // txtUpdate: 44 — сохранить; 83 — сохранить, только если текст изменился (frmStat: OldLine <> txtComm)
    document.getElementById('AcceptChanges').addEventListener('click', async () => {
        const v = t.value;
        if ($act === 'Comm' && v === $old) { closeAccessDialog(false); return; }
        if ($act === 'Comm' && v.length > 1024) await msgBox('Текст будет уменьшен до 1024 символов');   // CommonStartSP6 Warn = 1
        const fd = new FormData(); fd.append('act', $act); fd.append('text', v);
        if (csrfParam) fd.append(csrfParam, csrfToken);
        const res = await fetch($actUrl, { method: 'POST', body: fd })
            .then(r => r.ok ? r.json() : ({ ok: false, msg: 'Ошибка ' + r.status }));
        if (res.msg) await msgBox(res.msg, { icon: res.ok ? 'info' : 'warn' });
        closeAccessDialog(!!res.ok, res.ok ? res.text : null);       // в Access окно закрывается в любом случае
    });
    document.getElementById('Kn6').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
