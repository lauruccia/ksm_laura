<?php

namespace App\Support\Sites;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\CategoryTree;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cosa si vede su un dominio della rete.
 *
 * Un solo filtro per tutte le pagine: home, shop, directory, schede,
 * carrello e mappa del sito. Cio' che resta fuori non si vede, nemmeno
 * aprendo l'indirizzo a mano. Sul sito principale non filtra nulla.
 *
 * Esempio, ristoranticalabria.it: categoria azienda Ristoranti, luogo
 * Calabria, categoria prodotto Voucher. Aziende: i ristoranti calabresi.
 * Prodotti: i voucher di quei ristoranti, non di altre aziende calabresi.
 *
 *  - luogo: se il nome e' una regione filtra la regione, altrimenti la citta';
 *  - aziende (company_scope): per categoria azienda (category), solo chi
 *    vende nella categoria prodotto (products), o entrambe (both);
 *  - prodotti: categoria prodotto del dominio con le sottocategorie, e solo
 *    di aziende della categoria azienda e del luogo del dominio.
 */
final class SiteScope
{
    /** @var list<int>|null|false  false: non ancora calcolato */
    private array|null|false $productCategories = false;

    /** @var list<int>|null|false */
    private array|null|false $companyCategories = false;

    public function __construct(private readonly ?Domain $domain = null)
    {
    }

    public function isRestricted(): bool
    {
        return $this->productCategoryIds() !== null
            || $this->companyCategoryIds() !== null
            || $this->place() !== null
            || $this->requiresProducts();
    }

    /** @return list<int>|null  null: nessun filtro */
    public function productCategoryIds(): ?array
    {
        if ($this->productCategories === false) {
            $root = $this->domain?->filtersByCategory() ? $this->domain->product_category_id : null;
            $this->productCategories = $root
                ? [(int) $root, ...CategoryTree::of(ProductCategory::class)->descendants((int) $root)]
                : null;
        }

        return $this->productCategories;
    }

    /** @return list<int>|null */
    public function companyCategoryIds(): ?array
    {
        if ($this->companyCategories === false) {
            $root = $this->domain?->filtersByCategory() && $this->domain->company_scope !== 'products'
                ? $this->domain->company_category_id
                : null;
            $this->companyCategories = $root
                ? [(int) $root, ...CategoryTree::of(CompanyCategory::class)->descendants((int) $root)]
                : null;
        }

        return $this->companyCategories;
    }

    /**
     * Il luogo del dominio: una regione ("Calabria") o una citta' ("Ostia").
     *
     * @return array{column: string, value: string}|null
     */
    public function place(): ?array
    {
        $place = $this->domain?->filtersByCity() ? trim((string) $this->domain->city) : '';

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

        return $query
            ->when($this->productCategoryIds(), fn ($q, $ids) => $q->whereIn("$table.category_id", $ids))
            // Solo aziende della categoria e del luogo del dominio. Non si usa companies():
            // con company_scope products le aziende dipendono a loro volta dai prodotti.
            ->when($this->companyCategoryIds() || $this->place(), fn ($q) => $q->whereIn(
                "$table.company_id",
                $this->byCategoryAndPlace(Company::query())->select('companies.id')
            ));
    }

    /** Limita una query di aziende a quelle del dominio. Colonne qualificate: la directory unisce `plans`. */
    public function companies(Builder $query): Builder
    {
        return $this->byCategoryAndPlace($query)
            ->when($this->requiresProducts(), fn ($q) => $q->whereIn(
                'companies.id',
                Product::query()->active()
                    ->when($this->productCategoryIds(), fn ($p, $ids) => $p->whereIn('products.category_id', $ids))
                    ->select('products.company_id')
            ));
    }

    public function allowsProduct(Product $product): bool
    {
        return ! $this->isRestricted() || $this->products(Product::query()->whereKey($product->getKey()))->exists();
    }

    public function allowsCompany(Company $company): bool
    {
        return ! $this->isRestricted() || $this->companies(Company::query()->whereKey($company->getKey()))->exists();
    }

    private function requiresProducts(): bool
    {
        return in_array($this->domain?->company_scope, ['products', 'both'], true);
    }

    private function byCategoryAndPlace(Builder $query): Builder
    {
        $place = $this->place();

        return $query
            ->when($this->companyCategoryIds(), fn ($q, $ids) => $q->whereIn('companies.category_id', $ids))
            ->when($place && $place['column'] === 'region', fn ($q) => $q->where('companies.region', $place['value']))
            ->when($place && $place['column'] === 'city', fn ($q) => $q->where('companies.city', 'like', '%'.$place['value'].'%'));
    }
}
