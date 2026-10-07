<?php

namespace App\Support\Analytics;

use Illuminate\Http\Request;

/**
 * Il paese di chi visita, come sigla a due lettere (IT, DE, US...).
 *
 * Due strade, nessuna obbligatoria:
 *  - l'intestazione che molti servizi davanti al sito aggiungono da soli
 *    (Cloudflare: CF-IPCountry, e simili: vedi ksm.analytics.country_headers);
 *  - il file GeoLite2-Country di MaxMind, se e' stato messo sul server e
 *    indicato in KSM_GEOIP_DATABASE, con il pacchetto geoip2/geoip2 installato.
 *
 * Senza nessuna delle due il paese resta vuoto e le statistiche lo
 * mostrano come "Non rilevato": meglio un buco di un paese inventato.
 */
final class Geo
{
    private static mixed $reader = null;

    public static function country(Request $request): ?string
    {
        foreach ((array) config('ksm.analytics.country_headers') as $header) {
            $code = strtoupper(trim((string) $request->headers->get($header)));

            // XX e T1 sono i segnaposto di Cloudflare per "sconosciuto" e "Tor".
            if (preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, ['XX', 'T1'], true)) {
                return $code;
            }
        }

        return self::fromDatabase((string) $request->ip());
    }

    private static function fromDatabase(string $ip): ?string
    {
        $path = config('ksm.analytics.geoip_database');
        $reader = 'GeoIp2\\Database\\Reader';

        if (! $path || $ip === '' || ! is_file($path) || ! class_exists($reader)) {
            return null;
        }

        try {
            self::$reader ??= new $reader($path);
            $code = strtoupper((string) self::$reader->country($ip)->country->isoCode);

            return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;
        } catch (\Throwable) {
            // Indirizzo privato o non in archivio: nessun paese.
            return null;
        }
    }
}
