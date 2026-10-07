<?php

namespace App\Support\Analytics;

/**
 * Dispositivo, browser e sistema operativo da uno User-Agent.
 *
 * Poche regole a mano bastano per i grafici: serve sapere se si naviga da
 * telefono o da computer e con cosa, non la versione esatta. Niente
 * libreria da installare sul server.
 */
final class UserAgent
{
    /** @return array{device: string, browser: string, os: string} */
    public static function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;

        return [
            'device' => self::device($ua),
            'browser' => self::browser($ua),
            'os' => self::os($ua),
        ];
    }

    private static function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|PlayBook|Silk|Kindle/i', $ua) || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false)) {
            return 'tablet';
        }

        if (preg_match('/Mobi|iPhone|iPod|Windows Phone|BlackBerry|Opera Mini/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function browser(string $ua): string
    {
        // L'ordine conta: quasi tutti si dichiarano anche Chrome e Safari.
        return match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox\/|FxiOS/', $ua) => 'Firefox',
            (bool) preg_match('/MSIE |Trident\//', $ua) => 'Internet Explorer',
            (bool) preg_match('/Chrome\/|CriOS/', $ua) => 'Chrome',
            (bool) preg_match('/Safari\//', $ua) => 'Safari',
            default => 'Altro',
        };
    }

    private static function os(string $ua): string
    {
        return match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/', $ua) => 'iOS',
            (bool) preg_match('/Android/', $ua) => 'Android',
            (bool) preg_match('/Windows/', $ua) => 'Windows',
            (bool) preg_match('/CrOS/', $ua) => 'ChromeOS',
            (bool) preg_match('/Mac OS X|Macintosh/', $ua) => 'macOS',
            (bool) preg_match('/Linux|X11/', $ua) => 'Linux',
            default => 'Altro',
        };
    }
}
