<?php

namespace App\Support\Maps;

use App\Models\Company;

/**
 * Le coordinate da salvare quando una scheda azienda cambia.
 *
 * Il segnaposto spostato a mano sulla mappa vince sempre. Altrimenti, se
 * l'indirizzo e' cambiato o le coordinate mancano, si cerca l'indirizzo:
 * una ricerca per salvataggio, e nessuna se niente e' cambiato.
 */
class CompanyLocation
{
    private const FIELDS = ['company_location', 'address', 'city', 'region'];

    public function __construct(private readonly Geocoder $geocoder)
    {
    }

    /** @return array{latitude: float|string|null, longitude: float|string|null} */
    public function coordinates(Company $company, array $data): array
    {
        $before = $company->only(self::FIELDS);
        $after = array_intersect_key($data, array_flip(self::FIELDS)) + $before;

        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        $moved = $latitude !== null && $longitude !== null
            && ((float) $latitude !== (float) $company->latitude || (float) $longitude !== (float) $company->longitude);

        if ($moved) {
            return ['latitude' => $latitude, 'longitude' => $longitude];
        }

        $query = self::query($after);

        if ($query === '') {
            return ['latitude' => null, 'longitude' => null];
        }

        if ($query === self::query($before) && $company->latitude !== null) {
            return ['latitude' => $company->latitude, 'longitude' => $company->longitude];
        }

        $point = $this->geocoder->locate($query);

        return ['latitude' => $point[0] ?? null, 'longitude' => $point[1] ?? null];
    }

    /** Cosa cercare: la posizione scritta apposta, altrimenti indirizzo, citta' e regione. */
    public static function query(array $values): string
    {
        $location = trim((string) ($values['company_location'] ?? ''));

        if ($location !== '') {
            return $location;
        }

        return collect([$values['address'] ?? null, $values['city'] ?? null, $values['region'] ?? null])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->unique()
            ->join(', ');
    }
}
