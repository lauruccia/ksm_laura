<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Product;
use App\Support\CategoryLanding;
use App\Support\CompanyDirectory;
use App\Support\DirectoryMenu;
use App\Support\PlanCapabilities;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyDirectory $directory,
        private readonly DirectoryMenu $menu,
    ) {
    }

    public function index(Request $request, TenantContext $tenant): View|Response|RedirectResponse
    {
        // Sul dominio proprio di un'azienda non c'e' una directory: c'e' solo lei.
        if ($tenant->isCompanySite()) {
            return redirect()->route('home', status: 301);
        }

        $region = $request->string('regione')->toString();

        $filters = array_filter([
            'category' => $request->integer('categoria') ?: null,
            'city' => $request->string('citta')->toString() ?: null,
            'region' => in_array($region, config('ksm.regions'), true) ? $region : null,
            'search' => $request->string('cerca')->toString() ?: null,
        ]);

        $scope = $tenant->scope();

        // Il menu mostra solo le categorie che hanno aziende (con le loro madri),
        // a ogni livello: quelle vuote e le sottocategorie vuote restano fuori.
        $tree = $this->menu->tree();

        // Colonne sempre qualificate: la directory unisce anche `plans`,
        // che ha a sua volta una colonna `name`.
        $query = $scope->companies(Company::query()
            ->active()
            ->inDirectory())
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

        // Il caricamento continuo chiede solo le schede, senza layout ne' banner.
        if ($request->ajax()) {
            return response()
                ->view('pages.companies.partials.cards', ['companies' => $companies])
                ->header('Vary', 'X-Requested-With');
        }

        // Una sola categoria scelta, senza altri filtri: l'indirizzo giusto e' la pagina pulita /categorie/...
        if ($tenant->isPlatformSite() && array_keys($filters) === ['category'] && $request->integer('page', 1) <= 1 && $companies->total() > 0) {
            $slug = CompanyCategory::query()->whereKey($filters['category'])->value('slug');

            if ($slug) {
                return redirect(CategoryLanding::url($slug), 301);
            }
        }

        return view('pages.companies.index', [
            'companies' => $companies,
            'categories' => $tree->labels(),
            'categoryNav' => $this->menu->nodes($tree, (int) ($filters['category'] ?? 0), $request->only(['cerca', 'regione', 'citta'])),
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

    public function show(Company $company, TenantContext $tenant): View|RedirectResponse
    {
        // Sul suo dominio la pagina dell'azienda e' l'indirizzo principale: un solo indirizzo per pagina.
        if ($tenant->company()?->is($company) && ! request()->routeIs('home')) {
            return redirect()->route('home', status: 301);
        }

        // Biglietto e anagrafica non hanno una pagina: esistono solo in directory.
        abort_unless($company->is_active && $company->hasPage() && $tenant->scope()->allowsCompany($company), 404);

        $company->load(['category', 'plan'])->loadAvg('reviews', 'rating')->loadCount('reviews');

        $products = null;

        if ($company->allows(PlanCapabilities::SHOP)) {
            $products = $tenant->scope()->products($company->products()->active()->getQuery())->with('variants')->withAvg('reviews', 'rating')->withCount('reviews')->latest()->paginate(12);
            // Le schede leggono l'azienda: e' questa, non serve rileggerla per ogni prodotto.
            $products->getCollection()->each(fn ($product) => $product->setRelation('company', $company));
        }

        return view('pages.companies.show', [
            'company' => $company,
            'products' => $products,
            'reviews' => $company->allows(PlanCapabilities::REVIEWS)
                ? $company->reviews()->latest()->take(10)->get()
                : collect(),
        ]);
    }
}
