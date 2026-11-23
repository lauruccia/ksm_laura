<?php

namespace App\Support;

use App\Models\Company;
use App\Models\CompanyCategory;
use Illuminate\Support\Facades\Cache;

/**
 * Il menu laterale delle categorie, uguale nell'elenco aziende e nelle pagine
 * /categorie/...: mostra solo le categorie che hanno aziende, con le loro madri.
 *
 * Sul sito principale, senza ricerca o luogo da conservare, le voci portano
 * all'indirizzo pulito /categorie/{slug}; altrimenti restano i filtri dell'elenco.
 */
class DirectoryMenu
{
    /** Secondi di validita' delle categorie usate dalle aziende della directory. */
    private const SCOPED_CATEGORIES_TTL = 300;

    /** Fino a quante categorie principali il menu arriva con i rami gia' aperti. */
    private const OPEN_MAX_ROOTS = 3;

    public function __construct(private readonly TenantContext $tenant)
    {
    }

    /** L'albero delle categorie usate dalle aziende della directory, con tutte le madri. */
    public function tree(): CategoryTree
    {
        $full = CategoryTree::of(CompanyCategory::class);
        $domain = $this->tenant->domain();
        $companies = $this->tenant->scope()->companies(Company::query()->active()->inDirectory());

        // Scorrere le aziende del dominio (migliaia su un dominio di regione) a ogni
        // visita non serve: le categorie usate cambiano di rado, bastano per qualche minuto.
        $used = Cache::remember(
            'directory:categories:'.($domain ? $domain->id.'-'.$domain->updated_at?->timestamp : 0),
            self::SCOPED_CATEGORIES_TTL,
            fn () => $companies->distinct()->pluck('companies.category_id')->filter()->map(fn ($id) => (int) $id)->values()->all()
        );

        $keep = collect($used)->flatMap(fn (int $id) => $full->lineage($id))->unique()->all();

        return new CategoryTree(CompanyCategory::query()->whereKey($keep)->get(['id', 'name', 'parent_id']));
    }

    /**
     * Voci del menu: id, name, url, current, open, children.
     *
     * La categoria scelta e le sue madri restano aperte. Senza una scelta, con poche
     * categorie principali (un dominio tematico) si aprono tutti i rami.
     *
     * @param  array<string, string>  $keep  ricerca e luogo da conservare nei link
     * @return list<array{id: int, name: string, url: string, current: bool, open: bool, children: array}>
     */
    public function nodes(CategoryTree $tree, int $current = 0, array $keep = []): array
    {
        $open = $current
            ? $tree->lineage($current)
            : (count($tree->children()) <= self::OPEN_MAX_ROOTS ? array_keys($tree->labels()) : []);

        $slugs = $this->tenant->isPlatformSite() && array_filter($keep) === []
            ? CompanyCategory::query()->whereKey(array_keys($tree->labels()))->pluck('slug', 'id')
            : collect();

        return $this->level($tree, null, $current, $open, $keep, $slugs);
    }

    /** @param  list<int>  $open */
    private function level(CategoryTree $tree, ?int $parent, int $current, array $open, array $keep, $slugs): array
    {
        $nodes = [];

        foreach ($tree->children($parent) as $id => $name) {
            $slug = $slugs[$id] ?? null;

            $nodes[] = [
                'id' => $id,
                'name' => $name,
                'url' => $slug ? CategoryLanding::url($slug) : route('companies.index', $keep + ['categoria' => $id]),
                'current' => $current === $id,
                'open' => in_array($id, $open, true),
                'children' => $this->level($tree, $id, $current, $open, $keep, $slugs),
            ];
        }

        return $nodes;
    }
}
