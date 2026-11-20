<?php
namespace app\components;

/**
 * Показ значений так, как их показывает Access (свойства Format / DecimalPlaces).
 * Общий для всех перенесённых форм.
 */
class AccessFormat
{
    /** Цвет Access (число BGR, 255 = красный) → CSS #rrggbb. 0 или пусто → ''. */
    public static function color($c): string
    {
        $c = (int)$c;
        return $c ? sprintf('#%02x%02x%02x', $c & 255, ($c >> 8) & 255, ($c >> 16) & 255) : '';
    }

    /**
     * @param mixed       $v   значение из процедуры
     * @param string|null $fmt Format из SaveAsText: 'Short Date', 'Short Time', 'Fixed', 'General Number', '#,##0.00 ?;...'
     * @param int|null    $dp  DecimalPlaces (null = «Авто»)
     */
    public static function value($v, ?string $fmt = null, ?int $dp = null): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        // даты: драйвер отдаёт '2026-11-19 00:00:00.000'
        if (is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}):(\d{2}))?/', $v, $m)) {
            $date = "$m[3].$m[2].$m[1]";
            $time = isset($m[4]) ? "$m[4]:$m[5]" : '00:00';
            if ($fmt === 'Short Time') return $time;
            if ($fmt === 'Short Date' || str_starts_with((string)$fmt, 'dd')) return $date;
            if ($date === '30.12.1899') return $time . ':' . $m[6];          // в Access «только время»
            return $time === '00:00' ? $date : "$date $time:" . ($m[6] ?? '00');  // General Date
        }
        if (!is_numeric($v)) {
            return (string)$v;
        }
        $n = (float)$v;
        if ($fmt !== null && str_starts_with($fmt, '#,##0')) {                // денежный формат формы
            return number_format($n, $dp ?? 2, ',', ' ') . ' ₽';
        }
        if ($fmt === 'Fixed') {
            return number_format($n, $dp ?? 2, ',', '');
        }
        if ($dp !== null && $fmt !== null) {
            return number_format($n, $dp, ',', '');
        }
        // General Number: без лишних нулей, запятая. Целые строки (ИНН, номера с ведущими нулями) как есть.
        if (is_string($v) && preg_match('/^-?\d+$/', $v)) {
            return $v;
        }
        $s = rtrim(rtrim(number_format($n, 6, ',', ''), '0'), ',');
        return $s === '-0' ? '0' : $s;
    }

    /** Флажок Access: True = -1, в SQL bit = 1. */
    public static function bool($v): bool
    {
        return $v !== null && $v !== '' && (int)$v !== 0;
    }
}
