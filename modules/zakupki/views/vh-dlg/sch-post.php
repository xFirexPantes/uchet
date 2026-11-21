<?php
/**
 * frmSchPost «Переписка по счету». Раскладка из SaveAsText: ширина 16170 твипов = 1078 px, высота 7401 (493);
 * подформа lstMalis 8,8 — 1061 × 427. Подформа subfrmSchPost (таблица): столбцы по TabIndex, подписи из SaveAsText,
 * без ColumnWidth — 1" = 96 px; ID скрыт. Правка в ячейках как в subfrmSchPost: двойной щелчок по примечанию, «Кому», ответу,
 * «Кто ответил», дате ответа; Delete — очистить; щелчок по «Получен ответ» — переключить (UpdateMail → xp_SchElUpdateMail).
 * «Добавить письмо» → frmSchElMailAdd (vh-dlg/mail-add, 670 × 382), после добавления список заново.
 * @var yii\web\View $this
 * @var int $id
 * @var array $rows
 */
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\View;

$this->title = 'Переписка по счету';
echo $this->render('_dlg', ['id' => $id]);
$addUrl = Json::encode(Url::to(['mail-add', 'id' => $id]));
$fileUrl = Json::encode(Url::to(['mail-file', 'id' => $id]));
$c = fn($f) => ['cls' => 'c-' . $f];
?>
<div class="access-form" id="frmSchPost" style="width:1078px;height:493px">
    <div class="dlg-sub" style="left:8px;top:8px;width:1061px;height:427px">
        <?= $this->render('@app/views/access/_grid', ['tid' => 'lstMalis', 'key' => 'vhDlg.schPost', 'rows' => $rows, 'idField' => 'ID',
            'dataFields' => ['IsAnswered', 'Prim', 'Email', 'ans', 'Whoans', 'Fle'], 'cols' => [
                ['IsAnswered', 'Получен ответ', 96, ['chk' => 1] + $c('IsAnswered')],
                ['MType',      'Тип письма:',     96, []],
                ['Prim',       'Примечание:',     96, $c('Prim')],
                ['Email',      'Кому:',           96, $c('Email')],
                ['Who',        'Кто:',            96, []],
                ['DT',         'Когда:',          96, ['fmt' => 'Short Date']],
                ['ans',        'Ответ:',          96, $c('ans')],
                ['Whoans',     'Кто ответил:',    96, $c('Whoans')],
                ['DTans',      'Когда ответил:',  96, ['fmt' => 'dd.mm.yyyy'] + $c('DTans')],
                ['WhoAddans',  'Кто внес ответ:', 115, []],
                ['Fle',        'Файл:',           96, $c('Fle')],
            ]]) ?>
    </div>
    <button type="button" class="a-btn big" id="AddMail" title="Добавить письмо" style="left:26px;top:446px;width:38px;height:38px">＋</button>
    <button type="button" class="a-btn big" id="DelMailBut" title="Удалить письмо" style="left:75px;top:446px;width:38px;height:38px">−</button>
    <button type="button" class="a-btn big" id="ExitBut" title="Закрыть" style="left:1028px;top:446px;width:38px;height:38px">✕</button>
</div>
<?php
$this->registerJs(<<<JS
(function () {
    const t = document.getElementById('lstMalis');
    dlgRows(t);
    t.querySelectorAll('td.c-IsAnswered input').forEach(x => x.disabled = false);
    // UpdateMail: xp_SchElUpdateMail, затем список заново (Me.Form.RecordSource = …); строка счёта перечитывается после закрытия окна
    const upd = async (tr, rgm, val = '', isAns = 0) => {
        const r = await dlgPost('MailUpd', { mail: tr.dataset.id, rgm, val, isAns });
        if (!r.ok) { if (r.msg) await msgBox(r.msg, { icon: r.icon || 'warn' }); return; }
        location.reload();
    };
    const row = tr => { try { return JSON.parse(tr.dataset.row || '{}'); } catch (e) { return {}; } };
    const field = td => ((td?.className || '').match(/\bc-(\w+)/) || [])[1] || '';
    let curField = '';
    t.tBodies[0]?.addEventListener('click', async e => {
        const td = e.target.closest('td'), tr = e.target.closest('tr'); if (!td || !tr) return;
        curField = field(td);
        if (curField === 'IsAnswered' && e.target.matches('input')) {        // isAnswered_MouseDown
            e.preventDefault();
            const v = row(tr).IsAnswered;
            await upd(tr, 4, '', (v && v !== '0') ? 0 : 1);
        }
    });
    t.tBodies[0]?.addEventListener('dblclick', async e => {
        const td = e.target.closest('td'), tr = e.target.closest('tr'); if (!td || !tr || !tr.dataset.id) return;
        const d = row(tr), f = field(td);
        if (f === 'Prim') {                                                  // Prim_DblClick → frmShowPrim
            const s = await textBox('Примечание', d.Prim ?? '', { max: 255 });
            if (s !== null && s !== '') await upd(tr, 0, s);
        } else if (f === 'ans') {                                            // ans_DblClick → frmShowPrim
            const s = await textBox('Ответ', d.ans ?? '', { max: 1024 });
            if (s !== null && s !== (d.ans ?? '')) await upd(tr, 2, s);
        } else if (f === 'Email') {                                          // email_DblClick: InputBox + CheckEmail
            const s = await inputBox('email получателя', d.Email ?? '');
            if (s === null) return;
            if (!/^[\w\-.]+@[\w\-.]+\.\w+$/.test(s.trim())) { await msgBox('Введен ошибочный почтовый адрес', { icon: 'error' }); return; }
            await upd(tr, 1, s.trim());
        } else if (f === 'Whoans') {                                         // Whoans_DblClick
            const s = await inputBox('Кто ответил', d.Whoans ?? '');
            if (s) await upd(tr, 3, s);
        } else if (f === 'DTans') {                                          // DTans_DblClick → adhDoCalendar
            const s = await dateBox('Когда ответил');
            if (s) await upd(tr, 5, s);
        } else if (f === 'Fle') {                                            // Fle_DblClick
            if (!(d.Fle ?? '').trim()) {                                    // файла нет: выбрать, копия на \\\\Tsrv (UpdateMail RGM 6)
                const inp = document.createElement('input'); inp.type = 'file';
                inp.onchange = async () => {
                    if (!inp.files[0]) return;
                    const r = await dlgPost('MailAttach', { mail: tr.dataset.id, file: inp.files[0] });
                    if (!r.ok) { await msgBox(r.msg || 'Не удалось прикрепить файл', { icon: r.icon || 'warn' }); return; }
                    location.reload();
                };
                inp.click(); return;
            }
            const r = await fetch($fileUrl + '&' + new URLSearchParams({ mail: tr.dataset.id }));
            if (!r.ok || (r.headers.get('content-type') || '').includes('json')) {
                const j = await r.json().catch(() => ({}));
                await msgBox(j.msg || j.message || ('Ошибка ' + r.status)); return;
            }
            const a = document.createElement('a'); a.href = URL.createObjectURL(await r.blob());
            a.download = (d.Fle.split(/[\\\/]/).pop()) || 'file';
            document.body.appendChild(a); a.click(); a.remove();
        }
    });
    // *_KeyDown, KeyCode 46: Delete очищает поле текущей строки
    document.addEventListener('keydown', async e => {
        if (e.key !== 'Delete' || document.querySelector('.mb-ov') || e.target.matches('input, textarea, select')) return;
        const tr = dlgSel(t); if (!tr || !tr.dataset.id) return;
        const rgm = { Prim: 0, Email: 1, ans: 2, Whoans: 3, DTans: 5 }[curField];
        if (rgm !== undefined) { e.preventDefault(); await upd(tr, rgm, ''); }
        else if (curField === 'Fle' && (row(tr).Fle ?? '')) {              // Fle_KeyDown: «Удаляем?» → Kill + RGM 6 ''
            if (!(await askYesNo('Удаляем?'))) return;
            const r = await dlgPost('MailDetach', { mail: tr.dataset.id });
            if (!r.ok) { await msgBox(r.msg || 'Не удалось удалить файл', { icon: 'warn' }); return; }
            location.reload();
        }
    });
    // DelMailBut_Click → DelMail: xp_SchElDelMailNew; удалили — список заново. Строка счёта (число писем) перечитывается после закрытия окна.
    document.getElementById('DelMailBut').addEventListener('click', async () => {
        const tr = dlgSel(t); if (!tr || !tr.dataset.id) return;
        if (!(await askYesNo('Удаляем?'))) return;
        const r = await dlgPost('MailDel', { mail: tr.dataset.id });
        if (!r.ok) { if (r.msg) await msgBox(r.msg); return; }
        location.reload();
    });
    // AddMail_Click: DoCmd.OpenForm frmSchElMailAdd (acDialog), потом Requery
    document.getElementById('AddMail').addEventListener('click', async () => {
        const r = await accessDialog($addUrl, 670, 382);
        if (r.reload) location.reload();
    });
    document.getElementById('ExitBut').addEventListener('click', () => closeAccessDialog(true));
})();
JS, View::POS_END);
