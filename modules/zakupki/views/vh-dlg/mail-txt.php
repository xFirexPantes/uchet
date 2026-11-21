<?php
/**
 * frmGetTxt4Mail — письмо по счёту. Раскладка из SaveAsText: ширина 13665 твипов = 911 px, данные 6916 (461), примечание 632 (42).
 * Tip 0 — из «Входящих счетов» (MakeEmail), Tip 1 — об оплате из «Платежей по счёту».
 * «От» (Dflt): в Access его давала GetDefaultMail (учётная запись Outlook Express из реестра Windows — на сервере её нет).
 * Здесь «От» запоминается в браузере (localStorage 'vhDlg.mailFrom') после удачной отправки и подставляется сам; после правки список
 * шаблонов перечитывается (xp_GetTxt4MailNew Tip,'Dflt'). Письмо уходит с адреса сервера, «От» ставится в «Ответить».
 * @var yii\web\View $this
 * @var int $id
 * @var int $tip
 * @var int $plat
 * @var string $email
 * @var array $list  [[id, text]]
 */
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\View;

$this->title = 'Письмо';
echo $this->render('_dlg', ['id' => $id]);
$L = Json::encode($list);
?>
<style>
#frmGetTxt4Mail select { width:100%; height:100%; border:1px inset #999; background:#c0c0c0; font:12px Tahoma; }
#frmGetTxt4Mail textarea { font:12px Tahoma; }
</style>
<div class="access-form" id="frmGetTxt4Mail" style="width:911px;height:503px;font-size:12px">
    <div style="left:4px;top:3px;width:900px;height:148px"><select id="lstTxt" size="8" title="Шаблон текста"></select></div>
    <div class="a-lbl" style="left:0;top:153px;width:911px;height:23px;text-align:center;font-weight:bold">Текст</div>
    <div class="a-lbl" style="left:4px;top:155px;width:60px;height:16px">E-mail</div>
    <div class="a-txt" style="left:68px;top:155px;width:241px;height:17px"><input type="text" id="email" maxlength="255" value="<?= Html::encode($email) ?>"></div>
    <div class="a-lbl" style="left:601px;top:155px;width:60px;height:16px">От</div>
    <div class="a-txt" style="left:665px;top:155px;width:241px;height:17px"><input type="text" id="Dflt" maxlength="255" value="" title="Адрес, от имени которого письмо (в «Ответить»)"></div>
    <div class="a-txt" style="left:4px;top:175px;width:903px;height:279px"><textarea id="Txt" maxlength="2000"></textarea></div>
    <div class="dlg-ft" style="top:461px;height:42px"></div>
    <button type="button" class="a-btn big" id="SndMl" title="Сформировать письмо" style="left:4px;top:465px;width:38px;height:38px">✉</button>
    <button type="button" class="a-btn big" id="AddTxt" title="Добавить текст в справочник" style="left:756px;top:465px;width:38px;height:38px">＋</button>
    <button type="button" class="a-btn big" id="EditTxt" title="Изменить текст в справочнике" style="left:797px;top:465px;width:38px;height:38px">✎</button>
    <button type="button" class="a-btn big" id="ClsFrm" title="Закрыть форму" style="left:869px;top:465px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const \$ = id => document.getElementById(id);
    const tip = $tip, plat = $plat, lst = \$('lstTxt');
    function fill(list, sel) {
        lst.innerHTML = '';
        list.forEach(r => { const o = document.createElement('option'); o.value = r.id; o.textContent = r.text; lst.appendChild(o); });
        if (sel) lst.value = String(sel); else if (lst.options.length) lst.selectedIndex = 0;
        if (lst.value) loadTxt();
    }
    async function loadTxt() {                                       // lstTxt_AfterUpdate
        const r = await dlgPost('TxtGet', { txt: lst.value });
        if (r.ok) \$('Txt').value = r.text ?? '';
    }
    lst.addEventListener('change', loadTxt);
    \$('Dflt').addEventListener('change', async () => {
        const r = await dlgPost('TxtList', { tip, dflt: \$('Dflt').value.trim() });
        if (r.ok) fill(r.list);
    });
    const after = async r => {
        if (r.list) fill(r.list, r.sel);
        if (r.msg) await msgBox(r.msg);
    };
    \$('AddTxt').addEventListener('click', async () => {             // AddTxt_Click
        if (!\$('Txt').value) return;
        after(await dlgPost('TxtAdd', { tip, text: \$('Txt').value, dflt: \$('Dflt').value.trim() }));
    });
    \$('EditTxt').addEventListener('click', async () => {            // EditTxt_Click
        if (!lst.value) return;
        after(await dlgPost('TxtUpd', { tip, txt: lst.value, text: \$('Txt').value, dflt: \$('Dflt').value.trim() }));
    });
    \$('SndMl').addEventListener('click', async () => {              // SndMl_Click
        if (!\$('Dflt').value.trim()) { await msgBox("Не указан адрес 'От'"); return; }
        const b = \$('SndMl'); b.disabled = true;
        try {
            const r = await dlgPost('MailSend', { tip, plat, txt: lst.value || 0, email: \$('email').value.trim(), dflt: \$('Dflt').value.trim(), text: \$('Txt').value });
            if (!r.ok) { await msgBox(r.msg || 'Не удалось отправить сообщение', { icon: 'warn' }); return; }
            try { localStorage.setItem('vhDlg.mailFrom', \$('Dflt').value.trim()); } catch (e) {}
            closeAccessDialog(true, 'sent');
        } finally { b.disabled = false; }
    });
    \$('ClsFrm').addEventListener('click', () => closeAccessDialog(false));
    fill($L);
    // GetDefaultMail → последний «От» этого браузера; есть — список шаблонов под него
    let saved = ''; try { saved = localStorage.getItem('vhDlg.mailFrom') || ''; } catch (e) {}
    if (saved && !\$('Dflt').value) { \$('Dflt').value = saved; \$('Dflt').dispatchEvent(new Event('change')); }
})();
JS, View::POS_END);
