<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CompanyCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cio' che serve ai motori di ricerca sulla scheda di un'azienda, uguale per tutte:
 * tipo schema.org, link alle pagine categoria e alle aziende simili.
 *
 * Un solo modello per tutte le schede: il lavoro non si perde se un cliente se ne va.
 */
class CompanySeo
{
    /**
     * Slug di categoria (o di una sua madre) => tipo schema.org.
     * Per aggiungere un settore basta una riga; il resto resta LocalBusiness.
     */
    private const TYPES = [
        'dentisti' => 'Dentist',
        'parrucchieri' => 'HairSalon',
        'onoranze-funebri' => 'FuneralHome',
    ];

    private const RELATED_LIMIT = 6;

    private const TTL_HOURS = 6;

    public function __construct(private readonly TenantContext $tenant, private readonly CategoryLanding $landing)
    {
    }

    /**
     * Su un dominio della rete la scheda non si indicizza se la stessa esiste anche su ksm.it,
     * dove si posiziona. Le aziende esclusive di un dominio su ksm.it non ci sono: li' restano indicizzabili.
     */
    public function noindex(Company $company): bool
    {
        return $this->tenant->isNetworkSite()
            && config('ksm.seo.noindex_network_company_pages')
            && ! $company->exclusiveDomains()->exists();
    }

    /** Il tipo schema.org piu' preciso per la categoria dell'azienda. */
    public function schemaType(Company $company): string
    {
        if (! $company->category_id) {
            return 'LocalBusiness';
        }

        $slugs = Cache::remember('seo:type-slugs:'.$company->category_id, now()->addHours(self::TTL_HOURS), function () use ($company) {
            $lineage = CategoryTree::of(CompanyCategory::class)->lineage((int) $company->category_id);

            return CompanyCategory::query()->whereIn('id', $lineage)->pluck('slug')->all();
        });

        foreach ($slugs as $slug) {
            if (isset(self::TYPES[$slug])) {
                return self::TYPES[$slug];
            }
        }

        return 'LocalBusiness';
    }

    /**
     * Gli indirizzi delle pagine categoria e categoria + citta' della scheda.
     * Solo sul sito principale: le pagine /categorie non esistono sugli altri domini.
     *
     * @return array{category: string, place: ?string}|null
     */
    public function landingUrls(Company $company, ?string $city): ?array
    {
        $category = $company->category;

        if (! $category || ! $this->tenant->isPlatformSite()) {
            return null;
        }

        $placeUrl = null;

        if ($city !== null) {
            $slug = Str::slug($city);
            $found = $this->landing->places($category)['cities'][$slug] ?? null;

            if ($found && $found['count'] >= CategoryLanding::MIN_INDEXABLE) {
                $placeUrl = CategoryLanding::url($category->slug, $slug);
            }
        }

        return ['category' => CategoryLanding::url($category->slug), 'place' => $placeUrl];
    }

    /**
     * Altre aziende con pagina, stessa categoria e stessa citta'. Si parte da quelle
     * che seguono per id e si torna all'inizio: ogni scheda ne linka altre, a catena,
     * e nessuna resta senza link in ingresso.
     *
     * @return Collection<int, Company>
     */
    public function related(Company $company): Collection
    {
        $city = trim((string) $company->city);

        if (! $company->category_id || $city === '') {
            return collect();
        }

        return Cache::remember(
            'seo:related:'.request()->getHost().':'.$company->id,
            now()->addHours(self::TTL_HOURS),
            function () use ($company, $city) {
                $base = fn () => $this->tenant->scope()
                    ->companies(Company::query()->active()->inDirectory()->withPage())
                    ->where('companies.category_id', $company->category_id)
                    ->where('companies.city', $city)
                    ->orderBy('companies.id');

                $columns = ['companies.id', 'companies.name', 'companies.slug'];

                $next = $base()->where('companies.id', '>', $company->id)->limit(self::RELATED_LIMIT)->get($columns);

                if ($next->count() < self::RELATED_LIMIT) {
                    $next = $next->concat(
                        $base()->where('companies.id', '<', $company->id)->limit(self::RELATED_LIMIT - $next->count())->get($columns)
                    );
                }

                return $next->values();
            }
        );
    }
}
