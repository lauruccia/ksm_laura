<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Product;
use App\Support\CompanyDirectory;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class HomeController extends Controller
{
    public function __construct(private readonly CompanyDirectory $directory)
    {
    }

    /**
     * L'indirizzo principale. Un dominio della rete puo' aprire lo shop,
     * l'elenco aziende, la pagina di un'azienda o una pagina CMS al posto
     * della home: la pagina scelta risponde qui, senza reindirizzare.
     */
    public function index(TenantContext $tenant): View|Response
    {
        // Il dominio proprio di un'azienda apre la sua pagina, se il piano gliene da' una.
        $company = $tenant->company();

        if ($company && $company->hasPage()) {
            return app()->call([app(CompanyController::class), 'show'], ['company' => $company]);
        }

        $domain = $tenant->domain();

        return match ($domain?->entry_page) {
            'shop' => app()->call([app(ProductController::class), 'index']),
            'companies' => app()->call([app(CompanyController::class), 'index']),
            'company' => $domain->entryCompany
                ? app()->call([app(CompanyController::class), 'show'], ['company' => $domain->entryCompany])
                : $this->home($tenant),
            'page' => $domain->entryCmsPage
                ? app()->call([app(PageController::class), 'show'], ['page' => $domain->entryCmsPage])
                : $this->home($tenant),
            default => $this->home($tenant),
        };
    }

    private function home(TenantContext $tenant): View
    {
        $scope = $tenant->scope();

        $inDirectory = $scope->companies(Company::query()->active()->inDirectory());

        // Stesso ordine della directory, fasce per piano e dentro a caso,
        // ma il database restituisce solo le otto della vetrina.
        $companies = $this->directory->take($inDirectory, CompanyDirectory::seed(null), 8);

        $products = $scope->products(Product::query()
            ->active()
            ->with(['company', 'variants'])
            ->whereHas('company', fn ($q) => $q->active()->selling()))
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
