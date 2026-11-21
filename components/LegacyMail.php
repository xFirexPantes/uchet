<?php
namespace app\components;

use Yii;

/**
 * Письма вместо SendEMail / GetAdr / MakeSmd4LogMail из Access.
 *
 * В Access письмо уходило через Outlook на компьютере пользователя. В вебе — с сервера по SMTP
 * (настройки в config/local.php, ключ 'smtp'). Пока SMTP не настроен, письма не уходят, а складываются
 * файлами в runtime/mail (useFileTransport) — так безопасно проверять.
 *
 * Как в Access: если база не боевая (не TRXSQL), письмо уходит на тестовый адрес (params['mailTestTo']),
 * а если он не задан — только в runtime/mail. Каждая отправка пишется в журнал xp_AddLogTxtMailNew.
 */
class LegacyMail
{
    public const OK = 'Сообщение отправлено';

    /** GetAdr(Tip): xp_GetAdr (Tip int, Adr varchar(500) OUTPUT). */
    public static function adr(int $tip, string $dflt = ''): string
    {
        try {
            $v = Yii::$app->db->createCommand(
                'SET NOCOUNT ON; DECLARE @a varchar(500); EXEC dbo.xp_GetAdr ?, @a OUTPUT; SELECT @a', [1 => $tip]
            )->queryScalar();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
            return '';
        }
        return $v === null || $v === false ? $dflt : (string)$v;
    }

    /**
     * SendEMail(адрес, "", тема, текст, MakeSmd4LogMail()). Адресов может быть несколько через «;».
     * Возвращает «Сообщение отправлено» или текст ошибки — как VBA, чтобы вызывающий показал его в MsgBox.
     * $replyTo — адрес, от имени которого письмо шло в Access (SendEMailCDO … , ml): здесь он в «Ответить» (Reply-To).
     */
    public static function send(string $to, string $subject, string $body, string $replyTo = ''): string
    {
        $p = Yii::$app->params;
        if (!($p['mailIsProdDb'] ?? false)) {
            $to = (string)($p['mailTestTo'] ?? '');                  // не боевая база: только на тестовый адрес
        }
        $list = array_values(array_filter(array_map('trim', explode(';', $to))));
        try {
            if (!$list) {
                // адреса нет: письмо всё равно сохраняем файлом, чтобы было видно, что ушло бы
                $res = self::compose(['test@localhost'], $subject, $body, true, $replyTo) ? self::OK . ' (в файл, адрес не задан)' : 'Ошибка при отправке сообщения';
            } else {
                $res = self::compose($list, $subject, $body, false, $replyTo) ? self::OK : 'Ошибка при отправке сообщения';
            }
        } catch (\Throwable $e) {
            Yii::error($e->getMessage(), __METHOD__);
            $res = 'Ошибка при отправке сообщения: ' . $e->getMessage();
        }
        self::log($to, $subject, $body, $res, $replyTo);
        return $res;
    }

    private static function compose(array $to, string $subject, string $body, bool $toFile, string $replyTo = ''): bool
    {
        $mailer = Yii::$app->mailer;
        $old = $mailer->useFileTransport;
        if ($toFile) $mailer->useFileTransport = true;
        try {
            $m = $mailer->compose()
                ->setFrom(Yii::$app->params['mailFrom'] ?? 'uchet@localhost')
                ->setTo($to);
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $m->setReplyTo($replyTo);
            return $m
                ->setSubject($subject)
                ->setTextBody($body)
                ->send();
        } finally {
            $mailer->useFileTransport = $old;
        }
    }

    /** MakeSmd4LogMail + cmd.Execute: xp_AddLogTxtMailNew (AdrFrom, AdrTo, Txt, subj, res). */
    private static function log(string $to, string $subject, string $body, string $res, string $from = ''): void
    {
        try {
            Yii::$app->db->createCommand('SET NOCOUNT ON; EXEC dbo.xp_AddLogTxtMailNew ?, ?, ?, ?, ?', [
                1 => mb_substr($from, 0, 255), 2 => mb_substr($to, 0, 255), 3 => mb_substr($body, 0, 512),
                4 => mb_substr($subject, 0, 255), 5 => mb_substr($res, 0, 255),
            ])->execute();
        } catch (\yii\db\Exception $e) {
            Yii::error($e->getMessage(), __METHOD__);
        }
    }
}
