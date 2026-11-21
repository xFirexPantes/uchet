<?php
/**
 * frmSchElMailAdd «Создание переписки». Раскладка из SaveAsText: 670 × 382 px.
 * Поля «Ответ», «Кто ответил», «Файл» видны только для типа письма 5 (MType_AfterUpdate).
 * «Файл» (Fle_DblClick): файл уходит на сервер и копируется в \\Tsrv\Docs\Счета на оплату\<фирма>\Переписка (UncPath, нужна папка с записью).
 * @var yii\web\View $this
 * @var int $id
 * @var array $types  [['id', 'text']] из xp_SclElMailTypes
 * @var string $email адрес по счёту (xp_SchElGetMail)
 */
use yii\helpers\Html;
use yii\web\View;

$this->title = 'Создание переписки';
echo $this->render('_dlg', ['id' => $id]);
?>
<style>
#frmSchElMailAdd select, #frmSchElMailAdd textarea, #frmSchElMailAdd input { font:12px Tahoma; }
#frmSchElMailAdd textarea { width:100%; height:100%; box-sizing:border-box; resize:none; }
#frmSchElMailAdd .t5 { display:none; }
#frmSchElMailAdd.is5 .t5 { display:block; }
</style>
<div class="access-form" id="frmSchElMailAdd" style="width:670px;height:382px">
    <div class="a-lbl" style="left:18px;top:14px;width:95px;height:17px">Тип письма:</div>
    <div class="a-select" style="left:122px;top:14px;width:113px;height:19px">
        <select id="MType"><option value=""></option>
            <?php foreach ($types as $t): ?><option value="<?= (int)$t['id'] ?>"><?= Html::encode($t['text']) ?></option><?php endforeach ?>
        </select>
    </div>
    <div class="a-lbl" style="left:18px;top:43px;width:95px;height:17px">Примечание:</div>
    <div class="a-txt" style="left:122px;top:43px;width:519px;height:114px"><textarea id="Prim" maxlength="255"></textarea></div>
    <div class="a-lbl" style="left:18px;top:169px;width:95px;height:17px">email:</div>
    <div class="a-txt" style="left:122px;top:169px;width:113px;height:17px"><input type="text" id="email" maxlength="100" value="<?= Html::encode($email) ?>"></div>

    <div class="a-lbl t5" style="left:18px;top:198px;width:95px;height:17px">Ответ:</div>
    <div class="a-txt t5" style="left:122px;top:198px;width:519px;height:114px"><textarea id="Ans" maxlength="1024"></textarea></div>
    <div class="a-lbl t5" style="left:18px;top:319px;width:75px;height:17px">Кто ответил:</div>
    <div class="a-txt t5" style="left:122px;top:319px;width:113px;height:17px"><input type="text" id="WhoAns" maxlength="255"></div>
    <div class="a-lbl t5" style="left:284px;top:319px;width:45px;height:17px">Файл:</div>
    <div class="a-txt t5" style="left:332px;top:319px;width:309px;height:17px"><input type="text" id="Fle" readonly title="Двойной щелчок — выбрать файл КП" style="cursor:pointer"></div>
    <input type="file" id="FleFile" style="display:none">

    <button type="button" class="a-btn" id="Addbut" style="left:266px;top:346px;width:95px;height:36px">Добавить</button>
    <button type="button" class="a-btn big" id="ExitBut" title="Закрыть" style="left:604px;top:342px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const \$ = id => document.getElementById(id), f = \$('frmSchElMailAdd');
    // MType_AfterUpdate: у типа 5 видны «Ответ», «Кто ответил», «Файл»
    \$('MType').addEventListener('change', () => f.classList.toggle('is5', \$('MType').value === '5'));
    // Fle_DblClick: выбрать файл (ShowFileDialogGlobal «Выбрать файл КП»); копия на \\Tsrv делается при «Добавить»
    \$('Fle').addEventListener('dblclick', () => \$('FleFile').click());
    \$('FleFile').addEventListener('change', () => { \$('Fle').value = \$('FleFile').files[0]?.name || ''; });
    // AddBut_Click → AddMail (проверки в сервисе), закрыть с перечитыванием списка писем
    \$('Addbut').addEventListener('click', async () => {
        const b = \$('Addbut'); b.disabled = true;
        try {
            const r = await dlgPost('MailAdd', { type: \$('MType').value || 0, prim: \$('Prim').value, email: \$('email').value.trim(),
                ans: \$('Ans').value, who: \$('WhoAns').value.trim(), file: \$('FleFile').files[0] || '' });
            if (!r.ok) { await msgBox(r.msg || 'Не удалось добавить письмо', { icon: r.icon || 'warn' }); return; }
            closeAccessDialog(true);
        } finally { b.disabled = false; }
    });
    \$('ExitBut').addEventListener('click', () => closeAccessDialog(false));
})();
JS, View::POS_END);
