<?php

namespace App\Support\Analytics;

/** Numeri e durate come si leggono in pagina. */
final class Format
{
    public static function number(int|float $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    public static function decimal(float $value, int $digits = 1): string
    {
        return number_format($value, $digits, ',', '.');
    }

    public static function percent(float $ratio): string
    {
        return number_format($ratio * 100, $ratio > 0 && $ratio < 0.1 ? 1 : 0, ',', '.').'%';
    }

    /** 75 => "1m 15s", 3700 => "1h 1m", 0 => "0s". */
    public static function duration(int|float $seconds): string
    {
        $seconds = (int) round($seconds);

        return match (true) {
            $seconds >= 3600 => intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m',
            $seconds >= 60 => intdiv($seconds, 60).'m '.($seconds % 60).'s',
            default => $seconds.'s',
        };
    }

    /** Nome del paese in italiano dalla sigla; senza estensione intl resta la sigla. */
    public static function country(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'Non rilevato';
        }

        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.$code, 'it');

            if ($name && $name !== $code && $name !== '-'.$code) {
                return $name;
            }
        }

        return $code;
    }

    /** Nome del dispositivo, dalla chiave salvata. */
    public static function device(string $device): string
    {
        return ['desktop' => 'Computer', 'mobile' => 'Telefono', 'tablet' => 'Tablet'][$device] ?? 'Altro';
    }
}
