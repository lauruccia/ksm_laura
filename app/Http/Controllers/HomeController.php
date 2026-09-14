<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Product;
use App\Support\CompanyDirectory;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __construct(private readonly CompanyDirectory $directory)
    {
    }

    public function index(TenantContext $tenant): View
    {
        $filters = $tenant->listingFilters();

        $inDirectory = Company::query()
            ->active()
            ->inDirectory()
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('companies.category_id', $id))
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('companies.city', 'like', "%$city%"));

        // Stesso ordine della directory, fasce per piano e dentro a caso,
        // ma il database restituisce solo le otto della vetrina.
        $companies = $this->directory->take($inDirectory, CompanyDirectory::seed(null), 8);

        $products = Product::query()
            ->active()
            ->with('company')
            ->whereHas('company', fn ($q) => $q->active()->selling())
            ->latest()
            ->take(8)
            ->get();

        return view('pages.home', [
            'companies' => $companies,
            'products' => $products,
            'categories' => CompanyCategory::whereNull('parent_id')->orderBy('name')->take(12)->get(),
        ]);
    }
}
