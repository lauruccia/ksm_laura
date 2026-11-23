<?php

namespace App\Support;

use App\Models\CategoryLandingText;
use App\Models\Company;
use App\Models\CompanyCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pagine dedicate della directory: "categoria" e "categoria + luogo".
 *
 * Servono ai motori di ricerca: indirizzi puliti come /categorie/centri-estetici/roma
 * al posto dei filtri /aziende?categoria=12&citta=Roma. Una categoria comprende
 * le sue sottocategorie, come nell'elenco aziende.
 *
 * Il luogo e' una citta' (dalla colonna `city`) o una regione (dall'elenco di
 * config('ksm.regions')). Nei dati vecchi la regione sta a volte nella colonna
 * della citta': qui conta come regione, non come citta'.
 */
class CategoryLanding
{
    /** Sotto questo numero di aziende la pagina resta aperta ma non va indicizzata ne' in mappa. */
    public const MIN_INDEXABLE = 3;

    private const PLACES_TTL_MINUTES = 60;

    private const SITEMAP_TTL_HOURS = 6;

    public function __construct(private readonly TenantContext $tenant)
    {
    }

    public static function url(string $categorySlug, ?string $placeSlug = null): string
    {
        $parameters = ['category' => $categorySlug];

        if ($placeSlug !== null && $placeSlug !== '') {
            $parameters['place'] = $placeSlug;
        }

        return route('categories.show', $parameters);
    }

    /** Le aziende della directory nel ramo della categoria, sottocategorie comprese. */
    public function companies(CompanyCategory $category): Builder
    {
        $ids = [$category->id, ...CategoryTree::of(CompanyCategory::class)->descendants($category->id)];

        return $this->tenant->scope()
            ->companies(Company::query()->active()->inDirectory())
            ->whereIn('companies.category_id', $ids);
    }

    /** @param  array{type: string, raw: string}  $place */
    public function inPlace(Builder $query, array $place): Builder
    {
        if ($place['type'] === 'city') {
            return $query->where('companies.city', $place['raw']);
        }

        // Regione: la colonna giusta, oppure il nome scritto per errore nella citta'.
        return $query->where(fn ($q) => $q
            ->where('companies.region', $place['raw'])
            ->orWhere('companies.city', $place['raw']));
    }

    /**
     * Citta' e regioni con aziende nel ramo della categoria, dalle piu' ricche.
     *
     * @return array{
     *     cities: array<string, array{name: string, raw: string, count: int}>,
     *     regions: array<string, array{name: string, raw: string, count: int}>
     * }
     */
    public function places(CompanyCategory $category): array
    {
        return Cache::remember('landing:places:'.$category->id, now()->addMinutes(self::PLACES_TTL_MINUTES), function () use ($category) {
            $rows = $this->companies($category)
                ->toBase()
                ->select([])
                ->selectRaw('companies.city as city, companies.region as region, count(*) as n')
                ->groupBy('companies.city', 'companies.region')
                ->get();

            $cities = [];
            $regions = [];

            foreach ($rows as $row) {
                $keys = $this->placeKeys($row->city, $row->region);
                $n = (int) $row->n;

                if ($keys['city']) {
                    [$slug, $raw] = $keys['city'];
                    $cities[$slug]['variants'][$raw] = ($cities[$slug]['variants'][$raw] ?? 0) + $n;
                    $cities[$slug]['count'] = ($cities[$slug]['count'] ?? 0) + $n;
                }

                if ($keys['region']) {
                    [$slug, $raw] = $keys['region'];
                    $regions[$slug] = [
                        'name' => $raw,
                        'raw' => $raw,
                        'count' => ($regions[$slug]['count'] ?? 0) + $n,
                    ];
                }
            }

            // Della stessa citta' scritta in modi diversi vale la forma piu' usata.
            foreach ($cities as $slug => $city) {
                arsort($city['variants']);
                $raw = (string) array_key_first($city['variants']);
                $cities[$slug] = ['name' => $this->tidy($raw), 'raw' => $raw, 'count' => $city['count']];
            }

            $byCount = fn (array $a, array $b) => $b['count'] <=> $a['count'] ?: strcmp($a['name'], $b['name']);
            uasort($cities, $byCount);
            uasort($regions, $byCount);

            return ['cities' => $cities, 'regions' => $regions];
        });
    }

    /**
     * Il luogo dell'indirizzo, se la categoria ha aziende li'.
     *
     * @return array{type: string, slug: string, name: string, raw: string, count: int}|null
     */
    public function resolvePlace(CompanyCategory $category, string $slug): ?array
    {
        $places = $this->places($category);
        $regionNames = self::regionNames();

        if (isset($regionNames[$slug])) {
            $found = $places['regions'][$slug] ?? null;

            return $found ? ['type' => 'region', 'slug' => $slug] + $found : null;
        }

        $found = $places['cities'][$slug] ?? null;

        return $found ? ['type' => 'city', 'slug' => $slug] + $found : null;
    }

    /**
     * Le sottocategorie dirette che hanno aziende (nello stesso luogo, se c'e').
     *
     * @param  array{type: string, slug: string, raw: string}|null  $place
     * @return list<array{name: string, url: string, count: int}>
     */
    public function subcategories(CompanyCategory $category, ?array $place = null): array
    {
        $tree = CategoryTree::of(CompanyCategory::class);
        $direct = $tree->children($category->id);

        if (! $direct) {
            return [];
        }

        $query = $this->companies($category);

        if ($place) {
            $query = $this->inPlace($query, $place);
        }

        $rows = $query->toBase()
            ->select([])
            ->selectRaw('companies.category_id as category_id, count(*) as n')
            ->groupBy('companies.category_id')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            // La prima madre (o la categoria stessa) che e' figlia diretta di $category.
            foreach ($tree->lineage((int) $row->category_id) as $id) {
                if (isset($direct[$id])) {
                    $counts[$id] = ($counts[$id] ?? 0) + (int) $row->n;
                    break;
                }
            }
        }

        $slugs = CompanyCategory::query()->whereIn('id', array_keys($counts))->pluck('slug', 'id');
        $links = [];

        foreach ($direct as $id => $name) {
            if (isset($counts[$id], $slugs[$id])) {
                $links[] = ['name' => $name, 'url' => self::url($slugs[$id], $place['slug'] ?? null), 'count' => $counts[$id]];
            }
        }

        return $links;
    }

    /**
     * Il percorso dalla categoria principale a questa: nome e indirizzo di ogni gradino.
     *
     * @return list<array{name: string, url: string}>
     */
    public function trail(CompanyCategory $category): array
    {
        $lineage = array_reverse(CategoryTree::of(CompanyCategory::class)->lineage($category->id));
        $rows = CompanyCategory::query()->whereIn('id', $lineage)->get(['id', 'name', 'slug'])->keyBy('id');

        return collect($lineage)
            ->filter(fn (int $id) => $rows->has($id))
            ->map(fn (int $id) => ['name' => $rows[$id]->name, 'url' => self::url($rows[$id]->slug)])
            ->values()
            ->all();
    }

    /**
     * Le pagine che meritano di esistere: categorie e categorie + luogo con
     * abbastanza aziende. Le aziende contano anche nelle categorie madri.
     * `place` e' vuoto per la pagina della categoria.
     *
     * @return list<array{category_id: int, category: string, slug: string, place: string, place_name: string, type: string, count: int}>
     */
    public function pages(): array
    {
        return Cache::remember('landing:pages', now()->addMinutes(self::PLACES_TTL_MINUTES), function () {
            $tree = CategoryTree::of(CompanyCategory::class);
            $categories = CompanyCategory::query()->get(['id', 'name', 'slug'])->keyBy('id');
            $regions = self::regionNames();

            $rows = $this->tenant->scope()
                ->companies(Company::query()->active()->inDirectory())
                ->toBase()
                ->select([])
                ->selectRaw('companies.category_id as category_id, companies.city as city, companies.region as region, count(*) as n')
                ->groupBy('companies.category_id', 'companies.city', 'companies.region')
                ->get();

            $counts = [];
            $variants = [];

            foreach ($rows as $row) {
                if (! $row->category_id) {
                    continue;
                }

                $keys = array_filter($this->placeKeys($row->city, $row->region));
                $n = (int) $row->n;

                foreach ($keys as $kind => [$slug, $raw]) {
                    if ($kind === 'city') {
                        $variants[$slug][$raw] = ($variants[$slug][$raw] ?? 0) + $n;
                    }
                }

                foreach ($tree->lineage((int) $row->category_id) as $id) {
                    $counts[$id][''] = ($counts[$id][''] ?? 0) + $n;

                    foreach ($keys as [$slug]) {
                        $counts[$id][$slug] = ($counts[$id][$slug] ?? 0) + $n;
                    }
                }
            }

            ksort($counts);
            $pages = [];

            foreach ($counts as $id => $places) {
                if (! $categories->has($id)) {
                    continue;
                }

                ksort($places);

                foreach ($places as $place => $n) {
                    $place = (string) $place;

                    if ($n < self::MIN_INDEXABLE) {
                        continue;
                    }

                    if ($place === '') {
                        $type = 'category';
                        $name = '';
                    } elseif (isset($regions[$place])) {
                        $type = 'region';
                        $name = $regions[$place];
                    } else {
                        $type = 'city';
                        arsort($variants[$place]);
                        $name = $this->tidy((string) array_key_first($variants[$place]));
                    }

                    $pages[] = [
                        'category_id' => (int) $id,
                        'category' => $categories[$id]->name,
                        'slug' => $categories[$id]->slug,
                        'place' => $place,
                        'place_name' => $name,
                        'type' => $type,
                        'count' => $n,
                    ];
                }
            }

            return $pages;
        });
    }

    /**
     * Tutti gli indirizzi da mettere in mappa.
     *
     * @return list<array{loc: string, priority: string}>
     */
    public function sitemapEntries(): array
    {
        return Cache::remember('landing:sitemap', now()->addHours(self::SITEMAP_TTL_HOURS), fn () => array_map(
            fn (array $page) => [
                'loc' => self::url($page['slug'], $page['place']),
                'priority' => $page['place'] === '' ? '0.7' : '0.6',
            ],
            $this->pages()
        ));
    }

    /**
     * Titolo, titolo e descrizione per i motori e testo introduttivo che il
     * sito scrive da solo quando nessuno li ha scritti a mano.
     *
     * @param  array{type: string, name: string}|null  $place
     * @return array{heading: string, meta_title: string, meta_description: string, intro: string}
     */
    public function defaults(CompanyCategory $category, ?array $place, int $count): array
    {
        $brand = $this->tenant->brandName();

        $heading = match ($place['type'] ?? null) {
            'city' => __('site.landing_h1_city', ['category' => $category->name, 'place' => $place['name']]),
            'region' => __('site.landing_h1_region', ['category' => $category->name, 'place' => $place['name']]),
            default => $category->name,
        };

        return [
            'heading' => $heading,
            'meta_title' => __('site.landing_title', ['heading' => $heading, 'brand' => $brand]),
            'meta_description' => __('site.landing_description', ['heading' => $heading, 'brand' => $brand]),
            'intro' => trans_choice('site.landing_intro', $count, [
                'brand' => $brand,
                'count' => number_format($count, 0, ',', '.'),
                'heading' => $heading,
            ]),
        ];
    }

    /**
     * I testi che la pagina mostra davvero: quelli scritti a mano (della pagina
     * stessa, altrimenti del modello della categoria) con i segnaposto
     * sostituiti, e per il resto quelli automatici. `body` e' vuoto se nessuno
     * ha scritto l'approfondimento.
     *
     * @param  array{type: string, name: string, slug: string}|null  $place
     * @return array{heading: string, meta_title: string, meta_description: string, intro: string, body: ?string}
     */
    public function content(CompanyCategory $category, ?array $place, int $count): array
    {
        $defaults = $this->defaults($category, $place, $count) + ['body' => null];
        $custom = $this->customTexts($category->id, $place['slug'] ?? null);
        $variables = self::placeholders($category, $place, $count, $this->tenant->brandName());

        $content = [];

        foreach (CategoryLandingText::FIELDS as $field) {
            $text = trim((string) ($custom[$field] ?? ''));
            $content[$field] = $text !== '' ? strtr($text, $variables) : $defaults[$field];
        }

        return $content;
    }

    /**
     * I segnaposto da usare nei testi, con il valore che hanno su questa pagina.
     *
     * @param  array{name: string}|null  $place
     * @return array<string, string>
     */
    public static function placeholders(CompanyCategory $category, ?array $place, int $count, string $brand): array
    {
        return [
            '{categoria}' => $category->name,
            '{luogo}' => $place['name'] ?? 'Italia',
            '{numero}' => number_format($count, 0, ',', '.'),
            '{sito}' => $brand,
        ];
    }

    /**
     * I testi scritti a mano per una pagina, campo per campo: vale quello della
     * pagina e, dove manca, quello del modello della categoria.
     *
     * @return array<string, ?string>
     */
    public function customTexts(int $categoryId, ?string $placeSlug): array
    {
        $keys = $placeSlug === null ? [''] : [$placeSlug, CategoryLandingText::ALL_PLACES];

        $rows = CategoryLandingText::query()
            ->where('company_category_id', $categoryId)
            ->whereIn('place_key', $keys)
            ->get()
            ->keyBy('place_key');

        $texts = [];

        foreach (CategoryLandingText::FIELDS as $field) {
            foreach ($keys as $key) {
                $value = trim((string) ($rows[$key]->{$field} ?? ''));

                if ($value !== '') {
                    $texts[$field] = $value;
                    break;
                }
            }
        }

        return $texts;
    }

    /**
     * Citta' e regione di un'azienda, come [indirizzo, scritta originale].
     *
     * @return array{city: array{0: string, 1: string}|null, region: array{0: string, 1: string}|null}
     */
    private function placeKeys(mixed $city, mixed $region): array
    {
        $regions = self::regionNames();
        $city = trim((string) $city);
        $region = trim((string) $region);
        $citySlug = $city !== '' ? Str::slug($city) : '';

        // Nei dati vecchi la regione sta a volte nella colonna della citta'.
        if ($citySlug !== '' && isset($regions[$citySlug])) {
            $region = $region !== '' ? $region : $regions[$citySlug];
            $citySlug = '';
        }

        $regionSlug = $region !== '' ? Str::slug($region) : '';

        return [
            'city' => $citySlug !== '' ? [$citySlug, $city] : null,
            'region' => isset($regions[$regionSlug]) ? [$regionSlug, $regions[$regionSlug]] : null,
        ];
    }

    /** @return array<string, string> indirizzo => nome */
    private static function regionNames(): array
    {
        return collect(config('ksm.regions'))
            ->mapWithKeys(fn (string $name) => [Str::slug($name) => $name])
            ->all();
    }

    /** TUTTO MAIUSCOLO diventa Iniziali Maiuscole; il resto resta com'e'. */
    private function tidy(string $name): string
    {
        return mb_strtoupper($name) === $name ? Str::title(mb_strtolower($name)) : $name;
    }
}
