<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Support\PlanCapabilities;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /** Scelte offerte nel catalogo; la prima divisibile per 2, 3 e 4 e' il predefinito. */
    private const PER_PAGE_OPTIONS = [12, 24, 48];

    private const PER_PAGE_DEFAULT = 24;

    public function index(Request $request): View
    {
        $visible = Product::query()
            ->active()
            // Solo le aziende con un piano che permette la vendita.
            ->whereHas('company', fn ($q) => $q->active()->selling());

        $categoryId = $request->integer('categoria');

        $products = (clone $visible)
            ->with(['company', 'category', 'brand'])
            // La categoria madre comprende le sue sottocategorie.
            ->when($categoryId, fn ($q, $id) => $q->whereIn(
                'category_id',
                ProductCategory::query()->whereKey($id)->orWhere('parent_id', $id)->pluck('id')
            ))
            ->when($request->integer('marca'), fn ($q, $id) => $q->where('brand_id', $id))
            ->when($request->string('cerca')->toString(), fn ($q, $term) => $q->where('name', 'like', "%$term%"))
            ->when($request->float('prezzo_min'), fn ($q, $min) => $q->where('price', '>=', $min))
            ->when($request->float('prezzo_max'), fn ($q, $max) => $q->where('price', '<=', $max))
            ->when($request->boolean('disponibili'), fn ($q) => $q->where('stock', '>', 0))
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

        return view('pages.products.index', [
            'products' => $products,
            'categories' => $categories,
            'currentCategory' => $categoryId ? ProductCategory::find($categoryId) : null,
            'catalogTotal' => $counts->sum(),
            'brands' => ProductBrand::orderBy('name')->get(),
            'priceRange' => (clone $visible)->selectRaw('min(price) as min, max(price) as max')->toBase()->first(),
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless(
            $product->status === 'active'
                && $product->company->is_active
                && $product->company->allows(PlanCapabilities::SHOP),
            404
        );

        $product->load(['company', 'category', 'brand', 'variants']);

        return view('pages.products.show', [
            'product' => $product,
            'reviews' => $product->reviews()->with('user')->latest()->take(10)->get(),
            'related' => Product::active()
                ->where('company_id', $product->company_id)
                ->whereKeyNot($product->id)
                ->take(4)
                ->get(),
        ]);
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
