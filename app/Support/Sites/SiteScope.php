<?php

namespace App\Support\Sites;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cosa si vede su un dominio della rete.
 *
 * Un solo filtro per tutte le pagine: home, shop, directory, schede,
 * carrello e mappa del sito. Cio' che resta fuori non si vede, nemmeno
 * aprendo l'indirizzo a mano. Sul sito principale non filtra nulla.
 *
 * Tre blocchi indipendenti, e un campo vuoto non limita nulla:
 *
 *  - aziende: quelle delle categorie azienda scelte (con le sottocategorie)
 *    e del luogo; se richiesto, solo quelle con almeno un prodotto in
 *    vendita nelle categorie prodotto del dominio;
 *  - prodotti: quelli delle categorie prodotto scelte (con le
 *    sottocategorie). Di chi: solo delle aziende delle categorie e del
 *    luogo del dominio (companies), di tutto il marketplace (all), oppure
 *    nessuno (none: il sito non ha lo shop);
 *  - luogo: se il nome e' una regione filtra la regione, altrimenti la citta'.
 *
 * Esempio, ristoranticalabria.it: categoria azienda Ristoranti, luogo
 * Calabria, categoria prodotto Voucher. Aziende: i ristoranti calabresi.
 * Prodotti: i voucher di quei ristoranti, non di altre aziende calabresi.
 *
 * Sul dominio proprio di un'azienda il sito e' solo suo: la sua scheda e i
 * suoi prodotti, niente del resto del marketplace.
 *
 * Un'azienda esclusiva di uno o piu' domini (company_exclusive_domain) si
 * vede su quei domini anche fuori dal loro filtro aziende (la categoria
 * prodotto vale comunque per i prodotti), e mai su KSM ne' sugli altri.
 */
final class SiteScope
{
    /** @var list<int>|null|false  false: non ancora calcolato */
    private array|null|false $productCategories = false;

    /** @var list<int>|null|false */
    private array|null|false $companyCategories = false;

    public function __construct(private readonly ?Domain $domain = null, private readonly ?Company $company = null)
    {
    }

    /** Il dominio proprio di un'azienda: tutto si restringe a lei. */
    public function company(): ?Company
    {
        return $this->company;
    }

    public function isRestricted(): bool
    {
        return $this->company !== null
            || $this->productCategoryIds() !== null
            || $this->companyCategoryIds() !== null
            || $this->place() !== null
            || $this->requiresProducts()
            || $this->productScope() === 'none';
    }

    /** @return list<int>|null  null: nessun filtro */
    public function productCategoryIds(): ?array
    {
        if ($this->productCategories === false) {
            $this->productCategories = $this->branches(ProductCategory::class, $this->productCategoryRoots());
        }

        return $this->productCategories;
    }

    /**
     * Le categorie prodotto scelte per il dominio, senza le sottocategorie.
     *
     * @return list<int>
     */
    public function productCategoryRoots(): array
    {
        return $this->existing(ProductCategory::class, (array) $this->domain?->product_category_ids);
    }

    /** @return list<int>|null */
    public function companyCategoryIds(): ?array
    {
        if ($this->companyCategories === false) {
            $this->companyCategories = $this->branches(CompanyCategory::class, $this->companyCategoryRoots());
        }

        return $this->companyCategories;
    }

    /**
     * Le categorie azienda scelte per il dominio, senza le sottocategorie.
     *
     * @return list<int>
     */
    public function companyCategoryRoots(): array
    {
        return $this->existing(CompanyCategory::class, (array) $this->domain?->company_category_ids);
    }

    /** companies: solo delle aziende del dominio; all: di tutto il marketplace; none: nessun prodotto. */
    public function productScope(): string
    {
        return $this->domain?->product_scope ?: 'companies';
    }

    /**
     * Il luogo del dominio: una regione ("Calabria") o una citta' ("Ostia").
     *
     * @return array{column: string, value: string}|null
     */
    public function place(): ?array
    {
        $place = trim((string) $this->domain?->filter_place);

        if ($place === '') {
            return null;
        }

        foreach (config('ksm.regions') as $region) {
            if (mb_strtolower($region) === mb_strtolower($place)) {
                return ['column' => 'region', 'value' => $region];
            }
        }

        return ['column' => 'city', 'value' => $place];
    }

    /** Limita una query di prodotti a quelli del dominio. */
    public function products(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        if ($this->company) {
            return $query->where("$table.company_id", $this->company->getKey());
        }

        if ($this->productScope() === 'none') {
            return $query->whereRaw('1 = 0');
        }

        $ownCompanies = $this->productScope() === 'companies' && ($this->companyCategoryIds() || $this->place());

        return $query
            ->when($this->productCategoryIds(), fn ($q, $ids) => $q->whereIn("$table.category_id", $ids))
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q
                    // Solo aziende delle categorie e del luogo del dominio. Non si usa companies():
                    // se si chiedono aziende con prodotti, le aziende dipendono a loro volta dai prodotti.
                    ->when($ownCompanies, fn ($q) => $q->whereIn(
                        "$table.company_id",
                        $this->byCategoryAndPlace(Company::query())->select('companies.id')
                    ))
                    // Mai i prodotti delle aziende che hanno scelto un altro dominio, o un dominio se si e' su KSM.
                    ->whereNotIn("$table.company_id", $this->exclusiveElsewhere()))
                // Le aziende esclusive del dominio ci stanno anche fuori dal suo filtro aziende.
                ->when($this->domain, fn ($q) => $q->orWhereIn("$table.company_id", $this->exclusiveHere())));
    }

    /** Limita una query di aziende a quelle del dominio. Colonne qualificate: la directory unisce `plans`. */
    public function companies(Builder $query): Builder
    {
        if ($this->company) {
            return $query->where('companies.id', $this->company->getKey());
        }

        return $query->where(fn ($q) => $q
            ->where(fn ($q) => $this->byCategoryAndPlace($q)
                ->when($this->requiresProducts(), fn ($q) => $q->whereIn(
                    'companies.id',
                    Product::query()->active()
                        ->when($this->productCategoryIds(), fn ($p, $ids) => $p->whereIn('products.category_id', $ids))
                        ->select('products.company_id')
                ))
                ->whereNotIn('companies.id', $this->exclusiveElsewhere()))
            ->when($this->domain, fn ($q) => $q->orWhereIn('companies.id', $this->exclusiveHere())));
    }

    /**
     * Anche sul sito principale si controlla: le aziende esclusive di un
     * dominio non si aprono su KSM, nemmeno scrivendo l'indirizzo a mano.
     */
    public function allowsProduct(Product $product): bool
    {
        return $this->products(Product::query()->whereKey($product->getKey()))->exists();
    }

    public function allowsCompany(Company $company): bool
    {
        return $this->companies(Company::query()->whereKey($company->getKey()))->exists();
    }

    /** Vero se il dominio ha almeno un prodotto in vendita: senza, la voce Prodotti del menu sparisce. */
    public function hasProducts(): bool
    {
        return $this->remembers('products', fn () => $this->products(Product::query()
            ->active()
            ->whereHas('company', fn ($q) => $q->active()->selling()))->exists());
    }

    /** Vero se il dominio ha almeno un'azienda in directory: senza, la voce Aziende del menu sparisce. */
    public function hasCompanies(): bool
    {
        return $this->remembers('companies', fn () => $this->companies(Company::query()->active()->inDirectory())->exists());
    }

    /** Sul sito principale c'e' sempre tutto; sui domini la risposta vale qualche minuto. */
    private function remembers(string $what, \Closure $check): bool
    {
        if (! $this->isRestricted()) {
            return true;
        }

        $key = 'site-scope.'.$what.'.'.($this->company ? 'c'.$this->company->getKey() : 'd'.$this->domain?->getKey().'.'.$this->domain?->updated_at?->timestamp);

        return (bool) Cache::remember($key, now()->addMinutes(10), fn () => (int) $check());
    }

    /** Aziende che si vedono solo su altri domini, non su questo (su KSM: tutte le esclusive). */
    private function exclusiveElsewhere(): QueryBuilder
    {
        return DB::table('company_exclusive_domain')
            ->when($this->domain, fn ($q) => $q->whereNotIn('company_id', $this->exclusiveHere()))
            ->select('company_id');
    }

    private function exclusiveHere(): QueryBuilder
    {
        return DB::table('company_exclusive_domain')->where('domain_id', $this->domain?->getKey())->select('company_id');
    }

    private function requiresProducts(): bool
    {
        return (bool) $this->domain?->companies_with_products;
    }

    /**
     * Le categorie scelte che esistono ancora: una categoria cancellata non filtra piu'.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function existing(string $model, array $ids): array
    {
        $tree = CategoryTree::of($model);

        return array_values(array_filter(array_map('intval', $ids), fn (int $id) => $tree->lineage($id) !== []));
    }

    /**
     * Le categorie scelte con tutte le loro sottocategorie.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  list<int>  $roots
     * @return list<int>|null
     */
    private function branches(string $model, array $roots): ?array
    {
        $tree = CategoryTree::of($model);
        $ids = [];

        foreach ($roots as $root) {
            array_push($ids, $root, ...$tree->descendants($root));
        }

        return $ids ? array_values(array_unique($ids)) : null;
    }

    private function byCategoryAndPlace(Builder $query): Builder
    {
        $place = $this->place();

        return $query
            ->when($this->companyCategoryIds(), fn ($q, $ids) => $q->whereIn('companies.category_id', $ids))
            ->when($place && $place['column'] === 'region', fn ($q) => $q->where('companies.region', $place['value']))
            ->when($place && $place['column'] === 'city', fn ($q) => $this->whereCityWord($q, $place['value']));
    }

    /** La citta' come parola intera: "Ostia" prende "Lido di Ostia" e "Ostia Antica", non "Ostiano". */
    private function whereCityWord(Builder $query, string $city): Builder
    {
        $city = addcslashes($city, '\\%_');

        return $query->where(function ($q) use ($city) {
            $q->where('companies.city', 'like', $city);
            foreach ([' ', '-'] as $separator) {
                $q->orWhere('companies.city', 'like', $city.$separator.'%')
                    ->orWhere('companies.city', 'like', '%'.$separator.$city)
                    ->orWhere('companies.city', 'like', '%'.$separator.$city.$separator.'%');
            }
        });
    }
}
