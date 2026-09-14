<?php

namespace App\Support\Maps;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Trova le coordinate di un indirizzo con Nominatim, il servizio di OpenStreetMap.
 *
 * La politica d'uso di Nominatim chiede un nome riconoscibile nella
 * richiesta e niente ricerche in blocco: qui si fa una ricerca sola, quando
 * una scheda azienda si salva con un indirizzo nuovo.
 */
class Geocoder
{
    /** @return array{0: float, 1: float}|null latitudine e longitudine */
    public function locate(string $query): ?array
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('app.name').' ('.config('ksm.maps.contact').')',
            ])
                ->acceptJson()
                ->timeout(6)
                ->get((string) config('ksm.maps.geocoder'), [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'it',
                ]);
        } catch (ConnectionException) {
            return null;
        }

        $first = $response->successful() ? ($response->json()[0] ?? null) : null;

        return isset($first['lat'], $first['lon'])
            ? [(float) $first['lat'], (float) $first['lon']]
            : null;
    }
}
