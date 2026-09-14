<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Product;
use App\Support\CategoryTree;
use App\Support\CompanyDirectory;
use App\Support\PlanCapabilities;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyDirectory $directory)
    {
    }

    public function index(Request $request, TenantContext $tenant): View
    {
        $region = $request->string('regione')->toString();

        $filters = array_merge($tenant->listingFilters(), array_filter([
            'category' => $request->integer('categoria') ?: null,
            'city' => $request->string('citta')->toString() ?: null,
            'region' => in_array($region, config('ksm.regions'), true) ? $region : null,
            'search' => $request->string('cerca')->toString() ?: null,
        ]));

        $tree = CategoryTree::of(CompanyCategory::class);

        // Colonne sempre qualificate: la directory unisce anche `plans`,
        // che ha a sua volta una colonna `name`.
        $query = Company::query()
            ->active()
            ->inDirectory()
            // La categoria comprende le sue sottocategorie, a ogni livello.
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->whereIn(
                'companies.category_id',
                [(int) $id, ...$tree->descendants((int) $id)]
            ))
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('companies.city', 'like', "%$city%"))
            ->when($filters['region'] ?? null, fn ($q, $region) => $q->where('companies.region', $region))
            ->when($filters['search'] ?? null, fn ($q, $term) => $this->search($q, $term));

        // Il seme viaggia con la paginazione: senza, la pagina due
        // rimescolerebbe le stesse aziende gia' viste sulla uno.
        $seed = CompanyDirectory::seed($request->integer('mix'));

        $companies = $this->directory->paginate($query, $seed)
            ->appends($request->except('page') + ['mix' => $seed]);

        return view('pages.companies.index', [
            'companies' => $companies,
            'categories' => $tree->labels(),
            'filters' => $filters,
        ]);
    }

    /**
     * Una sola casella: nome, settore, citta', regione o prodotto in vendita.
     *
     * Settori e prodotti trovati si calcolano una volta come elenco di id:
     * scritti come whereHas verrebbero rivalutati per ogni azienda, e con
     * i dati veri la ricerca passa da circa 5 secondi a meno di mezzo.
     */
    private function search($query, string $term)
    {
        $like = "%$term%";

        return $query->where(function ($q) use ($like) {
            $q->where('companies.name', 'like', $like)
                ->orWhere('companies.city', 'like', $like)
                ->orWhere('companies.region', 'like', $like)
                ->orWhereIn('companies.category_id', CompanyCategory::query()->where('name', 'like', $like)->select('id'))
                ->orWhereIn('companies.id', Product::query()->active()->where('name', 'like', $like)->select('company_id'));
        });
    }

    public function show(Company $company): View
    {
        abort_unless($company->is_active, 404);

        $company->load(['category', 'plan'])->loadAvg('reviews', 'rating');

        return view('pages.companies.show', [
            'company' => $company,
            'products' => $company->allows(PlanCapabilities::SHOP)
                ? $company->products()->active()->latest()->paginate(12)
                : null,
            'reviews' => $company->allows(PlanCapabilities::REVIEWS)
                ? $company->reviews()->latest()->take(10)->get()
                : collect(),
        ]);
    }
}
