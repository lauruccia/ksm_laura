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
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CompanyController extends Controller
{
    /** Secondi di validita' delle categorie usate dalle aziende di un dominio. */
    private const SCOPED_CATEGORIES_TTL = 3600;

    public function __construct(private readonly CompanyDirectory $directory)
    {
    }

    public function index(Request $request, TenantContext $tenant): View|Response
    {
        $region = $request->string('regione')->toString();

        $filters = array_filter([
            'category' => $request->integer('categoria') ?: null,
            'city' => $request->string('citta')->toString() ?: null,
            'region' => in_array($region, config('ksm.regions'), true) ? $region : null,
            'search' => $request->string('cerca')->toString() ?: null,
        ]);

        $scope = $tenant->scope();

        // Sui domini il menu mostra solo le categorie delle aziende del dominio, con le loro madri.
        $tree = $scope->isRestricted()
            ? new CategoryTree($this->scopedCategories($tenant, $scope->companies(Company::query()->active()->inDirectory())))
            : CategoryTree::of(CompanyCategory::class);

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

        return view('pages.companies.index', [
            'companies' => $companies,
            'categories' => $tree->labels(),
            'tree' => $tree,
            // La categoria scelta e le sue madri: nel menu laterale restano aperte.
            'openCategories' => ($filters['category'] ?? null) ? $tree->lineage((int) $filters['category']) : [],
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

    /**
     * Le categorie usate dalle aziende della query, con tutte le madri.
     *
     * @return Collection<int, CompanyCategory>
     */
    private function scopedCategories(TenantContext $tenant, Builder $companies): Collection
    {
        $full = CategoryTree::of(CompanyCategory::class);
        $domain = $tenant->domain();

        // Scorrere le aziende del dominio (migliaia su un dominio di regione) a ogni
        // visita non serve: le categorie usate cambiano di rado, bastano per un'ora.
        $used = Cache::remember(
            'directory:categories:'.($domain ? $domain->id.'-'.$domain->updated_at?->timestamp : 0),
            self::SCOPED_CATEGORIES_TTL,
            fn () => $companies->distinct()->pluck('companies.category_id')->filter()->map(fn ($id) => (int) $id)->values()->all()
        );

        $keep = collect($used)->flatMap(fn (int $id) => $full->lineage($id))->unique()->all();

        return CompanyCategory::query()->whereKey($keep)->get(['id', 'name', 'parent_id']);
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

        return view('pages.companies.show', [
            'company' => $company,
            'products' => $company->allows(PlanCapabilities::SHOP)
                ? $tenant->scope()->products($company->products()->active()->getQuery())->latest()->paginate(12)
                : null,
            'reviews' => $company->allows(PlanCapabilities::REVIEWS)
                ? $company->reviews()->latest()->take(10)->get()
                : collect(),
        ]);
    }
}
