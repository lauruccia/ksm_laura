<?php

namespace App\Support\Analytics;

use Illuminate\Http\Request;

/**
 * Dove si trova chi visita: paese (sigla a due lettere), regione e citta'.
 *
 * Due strade, nessuna obbligatoria:
 *  - l'intestazione che molti servizi davanti al sito aggiungono da soli
 *    (Cloudflare: CF-IPCountry, e simili: vedi ksm.analytics.country_headers),
 *    che da' solo il paese;
 *  - un archivio .mmdb (DB-IP City Lite o MaxMind GeoLite2) messo sul server e
 *    indicato in KSM_GEOIP_DATABASE, letto da MmdbReader: da' anche regione e citta'.
 *
 * Senza nessuna delle due tutto resta vuoto e le statistiche lo mostrano come
 * "Non rilevato": meglio un buco di un luogo inventato. L'indirizzo IP serve
 * solo per questa ricerca e non si salva.
 */
final class Geo
{
    private static ?MmdbReader $reader = null;

    private static ?string $readerPath = null;

    /** @return array{country: ?string, region: ?string, city: ?string} */
    public static function locate(Request $request): array
    {
        $found = self::fromDatabase((string) $request->ip());

        foreach ((array) config('ksm.analytics.country_headers') as $header) {
            $code = strtoupper(trim((string) $request->headers->get($header)));

            // XX e T1 sono i segnaposto di Cloudflare per "sconosciuto" e "Tor".
            if (preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, ['XX', 'T1'], true)) {
                // Se l'intestazione e l'archivio non sono d'accordo sul paese, regione e citta' non si fidano.
                if ($found['country'] !== null && $found['country'] !== $code) {
                    $found['region'] = $found['city'] = null;
                }

                $found['country'] = $code;
                break;
            }
        }

        return $found;
    }

    public static function country(Request $request): ?string
    {
        return self::locate($request)['country'];
    }

    /** Dimentica il file aperto (serve ai test e dopo un aggiornamento dell'archivio). */
    public static function forget(): void
    {
        self::$reader = null;
        self::$readerPath = null;
    }

    /** @return array{country: ?string, region: ?string, city: ?string} */
    private static function fromDatabase(string $ip): array
    {
        $none = ['country' => null, 'region' => null, 'city' => null];
        $path = config('ksm.analytics.geoip_database');

        if (! $path || $ip === '' || ! is_file($path)) {
            return $none;
        }

        try {
            if (self::$reader === null || self::$readerPath !== $path) {
                self::$reader = new MmdbReader($path);
                self::$readerPath = $path;
            }

            $data = self::$reader->get($ip);
        } catch (\Throwable) {
            // Archivio rovinato: le statistiche non si fermano per questo.
            return $none;
        }

        if (! is_array($data)) {
            return $none;
        }

        $code = strtoupper((string) ($data['country']['iso_code'] ?? ''));
        $country = preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : null;

        // Regione e citta' solo per l'Italia: e' l'unico posto in cui le statistiche le mostrano.
        if ($country !== 'IT') {
            return ['country' => $country, 'region' => null, 'city' => null];
        }

        return [
            'country' => $country,
            'region' => Places::region($data['subdivisions'][0]['names']['en'] ?? null),
            'city' => Places::city($data['city']['names']['en'] ?? null),
        ];
    }
}
