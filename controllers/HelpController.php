<?php
namespace app\controllers;

use Yii;
use yii\helpers\Html;
use yii\helpers\Markdown;
use yii\web\NotFoundHttpException;

/**
 * Справка по окну (кнопка «?» и F1, views/access/_help.php). Параметр p = маршрут окна
 * (не r: имя r занято под маршрут в UrlManager, Url::to его выбрасывает).
 * Маршрут окна → файл Markdown:
 *   zakupki/schet/view  → modules/zakupki/docs/schet/view.md
 *   site/index          → docs/site/index.md
 * Шапка файла между строками --- (title, access_form, procs…) — для разработчиков, пользователю не показывается.
 * @doc help/view
 */
class HelpController extends BaseController
{
    public function actionView(string $p)
    {
        if (!preg_match('~^[a-z0-9-]+(/[a-z0-9-]+){1,2}$~', $p)) throw new NotFoundHttpException();
        $parts = explode('/', $p);
        $file = count($parts) === 3 && Yii::$app->hasModule($parts[0])
            ? Yii::getAlias('@app/modules/' . $parts[0] . '/docs/' . $parts[1] . '/' . $parts[2] . '.md')
            : Yii::getAlias('@app/docs/' . implode('/', array_slice($parts, -2)) . '.md');
        $text = is_file($file) ? (string)file_get_contents($file) : "# Справка\n\nДля этого окна справка ещё не написана.";
        $title = 'Справка';
        if (preg_match('~^---\R(.*?)\R---\R~s', $text, $m)) {
            if (preg_match('~^title:\s*(.+)$~m', $m[1], $t)) $title = trim($t[1]);
            $text = substr($text, strlen($m[0]));
        }
        $html = Markdown::process($text, 'gfm');
        $this->layout = false;
        return $this->renderContent('<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Справка: ' . Html::encode($title) . '</title><style>'
            . 'body{font:14px/1.5 Tahoma,Arial,sans-serif;max-width:820px;margin:0 auto;padding:12px 16px 40px;color:#222;background:#fff}'
            . 'h1{font-size:20px;border-bottom:1px solid #ccc;padding-bottom:4px}h2{font-size:16px;margin-top:22px;color:#0a246a}'
            . 'code{background:#f3f3f3;padding:0 3px}li{margin:2px 0}table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:2px 6px}'
            . '</style></head><body>' . $html . '</body></html>');
    }
}
