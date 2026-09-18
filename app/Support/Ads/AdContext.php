<?php

namespace App\Support\Ads;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Support\CategoryTree;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Dove si trova il visitatore, per scegliere i banner mirati.
 *
 * Il dominio viene dal contesto della richiesta; citta' e categoria dalla
 * pagina, se le conosce, altrimenti dal dominio della rete. Una categoria
 * porta con se' tutte le sue madri: una campagna su "Mangiare e bere"
 * compare anche nelle pagine dei ristoranti.
 *
 * Un dominio di regione ("Calabria") vale come tutte le sue citta': una
 * campagna su Cosenza compare su ristoranticalabria.it, e una campagna
 * sulla regione stessa anche.
 */
final class AdContext
{
    /** Il sito principale, ksm.it, nei bersagli delle campagne. */
    public const MAIN_SITE = 0;

    /** Quali banner accetta un dominio: tutti, solo le campagne mirate su di lui, nessuno. */
    public const MODES = ['all', 'targeted', 'none'];

    /** Per quanto tenere l'elenco delle citta' di una regione. */
    private const REGION_CITIES_TTL = 86400;

    /**
     * @param  list<int>  $categoryIds
     * @param  list<string>  $places  altri luoghi che valgono qui: la regione e le sue citta'
     */
    public function __construct(
        public readonly int $domainId,
        public readonly ?string $city,
        public readonly array $categoryIds,
        public readonly array $places = [],
        public readonly string $mode = 'all',
    ) {
    }

    public static function current(?string $city = null, ?int $categoryId = null): self
    {
        $tenant = app(TenantContext::class);
        $domain = $tenant->domain();
        $categoryId ??= $domain?->company_category_id;

        // Tutte le madri, non solo la prima: le categorie aziende arrivano al terzo livello.
        // Dall'albero gia' letto, senza una query per gradino a ogni banner.
        $categoryIds = $categoryId ? CategoryTree::of(CompanyCategory::class)->lineage((int) $categoryId) : [];

        $place = $tenant->scope()->place();
        $places = [];

        if ($place && $place['column'] === 'region') {
            $places = [$place['value'], ...self::regionCities($place['value'])];
        }

        return new self(
            domainId: $domain?->id ?? self::MAIN_SITE,
            city: filled($city) ? $city : ($place && $place['column'] === 'city' ? $place['value'] : null),
            categoryIds: $categoryIds,
            places: $places,
            mode: in_array($mode = data_get($domain?->site, 'ads.mode'), self::MODES, true) ? $mode : 'all',
        );
    }

    /** Le citta' con almeno un'azienda nella regione, dai dati delle aziende. */
    private static function regionCities(string $region): array
    {
        return Cache::remember('ads:region-cities:'.$region, self::REGION_CITIES_TTL, fn () => Company::query()
            ->where('region', $region)
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->map(fn ($city) => trim((string) $city))
            ->filter()
            ->values()
            ->all());
    }
}
