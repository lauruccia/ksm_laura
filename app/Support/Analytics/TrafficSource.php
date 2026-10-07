<?php

namespace App\Support\Analytics;

use App\Support\Sites\HostDirectory;

/**
 * Da dove arriva chi apre il sito: ricerca, social, email, un altro sito,
 * una campagna con i parametri utm_*, oppure da nessuna parte (diretto).
 *
 * Si decide solo sulla prima pagina della visita: dalla seconda in poi
 * il rimando e' il sito stesso e non dice niente.
 */
final class TrafficSource
{
    public const DIRECT = 'direct';

    public const SEARCH = 'search';

    public const SOCIAL = 'social';

    public const EMAIL = 'email';

    public const NETWORK = 'network';

    public const REFERRAL = 'referral';

    public const CAMPAIGN = 'campaign';

    /** Nomi mostrati nei grafici, nell'ordine in cui compaiono. */
    public const LABELS = [
        self::DIRECT => 'Diretto',
        self::SEARCH => 'Motori di ricerca',
        self::SOCIAL => 'Social',
        self::EMAIL => 'Email',
        self::NETWORK => 'Altri siti della rete',
        self::REFERRAL => 'Siti esterni',
        self::CAMPAIGN => 'Campagne',
    ];

    /**
     * Dominio (o suffisso di dominio) => [canale, nome]. La posta sta in cima:
     * mail.google.com non deve finire fra i motori di ricerca.
     */
    private const KNOWN = [
        'mail.google.com' => [self::EMAIL, 'Gmail'],
        'outlook.live.com' => [self::EMAIL, 'Outlook'],
        'outlook.office.com' => [self::EMAIL, 'Outlook'],
        'outlook.office365.com' => [self::EMAIL, 'Outlook'],
        'mail.yahoo.com' => [self::EMAIL, 'Yahoo Mail'],
        'webmail.' => [self::EMAIL, 'Webmail'],

        'google.' => [self::SEARCH, 'Google'],
        'bing.com' => [self::SEARCH, 'Bing'],
        'duckduckgo.com' => [self::SEARCH, 'DuckDuckGo'],
        'yahoo.' => [self::SEARCH, 'Yahoo'],
        'ecosia.org' => [self::SEARCH, 'Ecosia'],
        'brave.com' => [self::SEARCH, 'Brave'],
        'yandex.' => [self::SEARCH, 'Yandex'],
        'baidu.com' => [self::SEARCH, 'Baidu'],
        'qwant.com' => [self::SEARCH, 'Qwant'],
        'startpage.com' => [self::SEARCH, 'Startpage'],
        'virgilio.it' => [self::SEARCH, 'Virgilio'],
        'libero.it' => [self::SEARCH, 'Libero'],

        'facebook.com' => [self::SOCIAL, 'Facebook'],
        'fb.com' => [self::SOCIAL, 'Facebook'],
        'fb.me' => [self::SOCIAL, 'Facebook'],
        'messenger.com' => [self::SOCIAL, 'Messenger'],
        'instagram.com' => [self::SOCIAL, 'Instagram'],
        'threads.net' => [self::SOCIAL, 'Threads'],
        'linkedin.com' => [self::SOCIAL, 'LinkedIn'],
        'lnkd.in' => [self::SOCIAL, 'LinkedIn'],
        'tiktok.com' => [self::SOCIAL, 'TikTok'],
        'youtube.com' => [self::SOCIAL, 'YouTube'],
        'youtu.be' => [self::SOCIAL, 'YouTube'],
        'pinterest.' => [self::SOCIAL, 'Pinterest'],
        'reddit.com' => [self::SOCIAL, 'Reddit'],
        'whatsapp.com' => [self::SOCIAL, 'WhatsApp'],
        'wa.me' => [self::SOCIAL, 'WhatsApp'],
        't.me' => [self::SOCIAL, 'Telegram'],
        'telegram.org' => [self::SOCIAL, 'Telegram'],
        'twitter.com' => [self::SOCIAL, 'X (Twitter)'],
        'x.com' => [self::SOCIAL, 'X (Twitter)'],
        't.co' => [self::SOCIAL, 'X (Twitter)'],

    ];

    /**
     * @param  array<string, mixed>  $query  i parametri dell'indirizzo aperto
     * @return array{channel: string, source: ?string, medium: ?string, campaign: ?string}
     */
    public static function classify(?string $referrer, array $query, string $ownHost): array
    {
        $campaign = self::clean($query['utm_campaign'] ?? null, 120);
        $medium = self::clean($query['utm_medium'] ?? null, 60);
        $utmSource = self::clean($query['utm_source'] ?? null, 120);

        // Un indirizzo con utm_source dice da solo da dove arriva chi lo apre.
        if ($utmSource !== null) {
            return [
                'channel' => self::channelFromMedium($medium, $utmSource),
                'source' => $utmSource,
                'medium' => $medium,
                'campaign' => $campaign,
            ];
        }

        $host = self::host($referrer);

        if ($host === null || $host === HostDirectory::normalise($ownHost)) {
            return ['channel' => self::DIRECT, 'source' => null, 'medium' => null, 'campaign' => $campaign];
        }

        foreach (self::KNOWN as $needle => [$channel, $name]) {
            $matches = str_ends_with($needle, '.')
                ? str_starts_with($host, $needle) || str_contains($host, '.'.$needle)
                : $host === $needle || str_ends_with($host, '.'.$needle);

            if ($matches) {
                return ['channel' => $channel, 'source' => $name, 'medium' => null, 'campaign' => $campaign];
            }
        }

        $channel = HostDirectory::knows($host) ? self::NETWORK : self::REFERRAL;

        return ['channel' => $channel, 'source' => $host, 'medium' => null, 'campaign' => $campaign];
    }

    private static function channelFromMedium(?string $medium, string $source): string
    {
        $medium = strtolower((string) $medium);

        return match (true) {
            str_contains($medium, 'mail') || str_contains($medium, 'newsletter') => self::EMAIL,
            str_contains($medium, 'social') => self::SOCIAL,
            $medium === 'organic' => self::SEARCH,
            default => self::CAMPAIGN,
        };
    }

    private static function host(?string $referrer): ?string
    {
        $host = parse_url((string) $referrer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = HostDirectory::normalise($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private static function clean(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
