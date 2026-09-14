<?php

namespace App\Support\Ads;

/**
 * Riconosce i programmi automatici dal loro User-Agent.
 *
 * E' il filtro per chi si dichiara: motori di ricerca, anteprime dei link
 * nelle app di messaggi, controlli di disponibilita', script. Chi finge di
 * essere un browser lo ferma ads.js, che non conta nei browser pilotati da
 * un programma e conta solo i banner rimasti visibili almeno un secondo.
 */
final class BotDetector
{
    private const PATTERN = '/(bot|crawler|spider|slurp)\b|crawl|mediapartners|headless|phantomjs|puppeteer'
        .'|playwright|selenium|lighthouse|pagespeed|pingdom|uptime|preview|facebookexternalhit|embedly'
        .'|whatsapp|telegram|skype|discord|curl\/|wget|python|java\/|go-http-client|okhttp|axios'
        .'|node-fetch|guzzle|scrapy|httpclient|feedfetcher|validator|ia_archiver|archive\.org/i';

    public static function isBot(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        // Un browser vero si presenta sempre.
        return $userAgent === '' || preg_match(self::PATTERN, $userAgent) === 1;
    }
}
