<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Support\Cart;
use App\Support\CategoryTree;
use App\Support\PlanCapabilities;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function __construct(private readonly Cart $cart, private readonly TenantContext $tenant)
    {
    }

    /** Scelte offerte nel catalogo; la prima divisibile per 2, 3 e 4 e' il predefinito. */
    private const PER_PAGE_OPTIONS = [12, 24, 48];

    private const PER_PAGE_DEFAULT = 24;

    /** Quanti prodotti portano il segno "piu' venduto". */
    private const BESTSELLER_BADGES = 3;

    /** Secondi di validita' della classifica dei piu' venduti. */
    private const RANKING_TTL = 3600;

    /** Per quanti giorni un prodotto e' una novita'. */
    public const NEW_FOR_DAYS = 30;

    public function index(Request $request): View
    {
        $scope = $this->tenant->scope();

        // Solo le aziende con un piano che permette la vendita, e sui domini solo i prodotti del dominio.
        $visible = $scope->products(Product::query()
            ->active()
            ->whereHas('company', fn ($q) => $q->active()->selling()));

        $categoryId = $request->integer('categoria');

        $products = (clone $visible)
            ->with(['company', 'category', 'brand', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            // La categoria madre comprende le sue sottocategorie.
            ->when($categoryId, fn ($q, $id) => $q->whereIn(
                'category_id',
                ProductCategory::query()->whereKey($id)->orWhere('parent_id', $id)->pluck('id')
            ))
            ->when($request->integer('marca'), fn ($q, $id) => $q->where('brand_id', $id))
            ->when($request->string('cerca')->toString(), fn ($q, $term) => $q->where('name', 'like', "%$term%"))
            ->when($request->float('prezzo_min'), fn ($q, $min) => $q->where('price', '>=', $min))
            ->when($request->float('prezzo_max'), fn ($q, $max) => $q->where('price', '<=', $max))
            ->when($request->boolean('disponibili'), fn ($q) => $q->available())
            ->when($request->boolean('kmoney'), fn ($q) => $q->where('kmoney_percent', '>', 0))
            ->when($request->boolean('offerta'), fn ($q) => $q->where('discount_price', '>', 0)->whereColumn('discount_price', '<', 'price'))
            ->orderBy($this->sortColumn($request->string('ordina')->toString()), $this->sortDirection($request->string('ordina')->toString()))
            ->paginate($this->perPage($request))
            ->withQueryString();

        // Prodotti visibili per categoria, per i contatori della barra laterale.
        $counts = (clone $visible)
            ->selectRaw('category_id, count(*) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $categories = ProductCategory::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->each(function (ProductCategory $category) use ($counts) {
                $category->children->each(fn ($child) => $child->visible_count = (int) ($counts[$child->id] ?? 0));
                $category->visible_count = (int) ($counts[$category->id] ?? 0) + $category->children->sum('visible_count');
            });

        $featured = $this->tenant->content()->featured();
        $featuredProducts = $featured['enabled'] ? $this->featured($visible, $featured['sort'], $featured['count']) : collect();

        $this->markBestsellers($visible, $products->getCollection()->concat($featuredProducts));

        return view('pages.products.index', [
            'products' => $products,
            'categories' => $categories,
            'railCategories' => $this->railCategories($categories, $counts),
            'featuredProducts' => $featuredProducts,
            'currentCategory' => $categoryId ? ProductCategory::find($categoryId) : null,
            'catalogTotal' => $counts->sum(),
            // Sui domini solo le marche dei prodotti che ci sono: le altre porterebbero a zero risultati.
            'brands' => ProductBrand::query()
                ->when($scope->isRestricted(), fn ($q) => $q->whereIn('id', (clone $visible)->whereNotNull('brand_id')->select('brand_id')))
                ->orderBy('name')
                ->get(),
            'priceRange' => (clone $visible)->selectRaw('min(price) as min, max(price) as max')->toBase()->first(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless(
            $product->status === 'active'
                && $product->company->is_active
                && $product->company->allows(PlanCapabilities::SHOP)
                && $this->tenant->scope()->allowsProduct($product),
            404
        );

        $product->load(['company', 'category', 'brand', 'variants']);

        return view('pages.products.show', [
            'product' => $product,
            // Se il carrello in corso e' di un altro venditore lo si dice prima
            // del click, non dopo: aggiungere qui mette l'altro in attesa.
            'parkedCompany' => $this->cart->belongsToOtherCompany($product) ? $this->cart->company() : null,
            'reviews' => $product->reviews()->with('user')->latest()->take(10)->get(),
            'related' => $this->tenant->scope()->products(Product::active())
                ->where('company_id', $product->company_id)
                ->whereKeyNot($product->id)
                ->take(4)
                ->get(),
        ]);
    }

    /** La fila in evidenza sopra il catalogo. */
    private function featured(Builder $visible, string $sort, int $count): Collection
    {
        $query = (clone $visible)
            ->with(['company', 'variants'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');

        if ($sort !== 'bestsellers') {
            return $query
                ->when($sort === 'offers', fn ($q) => $q->where('discount_price', '>', 0)->whereColumn('discount_price', '<', 'price'))
                ->latest()
                ->take($count)
                ->get();
        }

        // Sommare le vendite di tutto il catalogo a ogni visita costa: la classifica
        // si tiene in cache, i prodotti si rileggono e si rifiltrano ogni volta.
        $ids = $this->ranking($visible, $count);

        return $query->whereKey($ids)->get()
            ->sortBy(fn (Product $product) => array_search($product->id, $ids, true))
            ->values();
    }

    /** Il segno "piu' venduto" sui primi prodotti per pezzi venduti, fra quelli del sito. */
    private function markBestsellers(Builder $visible, Collection $shown): void
    {
        $top = array_slice($this->ranking($visible, self::BESTSELLER_BADGES, soldOnly: true), 0, self::BESTSELLER_BADGES);

        $shown->each(fn (Product $product) => $product->is_bestseller = in_array($product->id, $top, true));
    }

    /**
     * Id dei prodotti del sito dal piu' venduto, per un'ora.
     *
     * @return list<int>
     */
    private function ranking(Builder $visible, int $count, bool $soldOnly = false): array
    {
        // Nella chiave anche l'ultima modifica del dominio: cambiato il filtro, cambia la classifica.
        $domain = $this->tenant->domain();
        $site = $domain ? $domain->id.'-'.$domain->updated_at?->timestamp : 0;

        return Cache::remember("shop:ranking:$site:$count:".(int) $soldOnly, self::RANKING_TTL, fn () => (clone $visible)
            ->withSold()
            ->orderByDesc('sold_count')
            ->latest()
            ->take($count)
            ->get()
            ->filter(fn (Product $product) => ! $soldOnly || (float) $product->sold_count > 0)
            ->modelKeys());
    }

    /**
     * Le categorie nei riquadri dello shop.
     *
     * Quelle scelte per il dominio, nell'ordine scelto; altrimenti, su un
     * dominio di categoria, le sottocategorie della sua; sul sito
     * principale le categorie principali. Il contatore comprende le
     * sottocategorie, e le categorie vuote la vista le salta.
     */
    private function railCategories(Collection $topLevel, Collection $counts): Collection
    {
        $chosen = $this->tenant->content()->categories()['ids'];
        $root = $this->tenant->scope()->productCategoryIds()[0] ?? null;

        if (! $chosen && ! $root) {
            return $topLevel;
        }

        $tree = CategoryTree::of(ProductCategory::class);
        $ids = $chosen ?: (array_keys($tree->children($root)) ?: [$root]);

        return ProductCategory::query()
            ->whereKey($ids)
            ->get()
            ->sortBy(fn (ProductCategory $category) => array_search($category->id, $ids, true))
            ->values()
            ->each(fn (ProductCategory $category) => $category->visible_count = collect([$category->id, ...$tree->descendants($category->id)])
                ->sum(fn (int $id) => (int) ($counts[$id] ?? 0)));
    }

    private function perPage(Request $request): int
    {
        $perPage = $request->integer('per_pagina');

        return in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::PER_PAGE_DEFAULT;
    }

    private function sortColumn(string $sort): string
    {
        return match ($sort) {
            'prezzo', 'prezzo_desc' => 'price',
            'nome' => 'name',
            default => 'created_at',
        };
    }

    private function sortDirection(string $sort): string
    {
        return in_array($sort, ['prezzo_desc', ''], true) || $sort === '' ? 'desc' : 'asc';
    }
}
