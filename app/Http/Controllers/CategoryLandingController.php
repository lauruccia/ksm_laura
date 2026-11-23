<?php

namespace App\Http\Controllers;

use App\Models\CompanyCategory;
use App\Support\CategoryLanding;
use App\Support\CompanyDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /categorie/{categoria} e /categorie/{categoria}/{citta-o-regione}:
 * le pagine della directory pensate per essere trovate dai motori di ricerca.
 */
class CategoryLandingController extends Controller
{
    /** Quante citta' e regioni proporre come altri luoghi. */
    private const OTHER_CITIES = 30;

    public function __construct(
        private readonly CompanyDirectory $directory,
        private readonly CategoryLanding $landing,
    ) {
    }

    public function show(Request $request, string $category, ?string $place = null): View|Response
    {
        $categoryModel = CompanyCategory::query()->where('slug', $category)->firstOrFail();

        $resolved = null;

        if ($place !== null) {
            $resolved = $this->landing->resolvePlace($categoryModel, $place);
            abort_if($resolved === null, 404);
        }

        $query = $this->landing->companies($categoryModel);

        if ($resolved) {
            $query = $this->landing->inPlace($query, $resolved);
        }

        // Ordine stabile per tutto il giorno e senza seme nell'indirizzo: la stessa
        // pagina resta la stessa per chi la indicizza, e cambia a rotazione nei giorni.
        $seed = (crc32(now()->format('Ymd').'|'.$categoryModel->id.'|'.($resolved['slug'] ?? '')) % 999_999) + 1;

        $companies = $this->directory->paginate($query, $seed);

        abort_if($companies->isEmpty(), 404);

        // Il caricamento continuo chiede solo le schede, senza layout.
        if ($request->ajax()) {
            return response()
                ->view('pages.companies.partials.cards', ['companies' => $companies])
                ->header('Vary', 'X-Requested-With');
        }

        $places = $this->landing->places($categoryModel);
        $min = CategoryLanding::MIN_INDEXABLE;

        // Altri luoghi: solo quelli che meritano una pagina, senza quello in cui siamo.
        $otherCities = collect($places['cities'])
            ->reject(fn (array $city, string $slug) => $slug === ($resolved['slug'] ?? null) || $city['count'] < $min)
            ->take(self::OTHER_CITIES)
            ->map(fn (array $city, string $slug) => ['name' => $city['name'], 'url' => CategoryLanding::url($categoryModel->slug, $slug)])
            ->values();

        $otherRegions = collect($places['regions'])
            ->reject(fn (array $region, string $slug) => $slug === ($resolved['slug'] ?? null) || $region['count'] < $min)
            ->map(fn (array $region, string $slug) => ['name' => $region['name'], 'url' => CategoryLanding::url($categoryModel->slug, $slug)])
            ->values();

        $page = max(1, $companies->currentPage());
        $baseUrl = CategoryLanding::url($categoryModel->slug, $resolved['slug'] ?? null);

        return view('pages.companies.category', [
            'category' => $categoryModel,
            'content' => $this->landing->content($categoryModel, $resolved, $companies->total()),
            'place' => $resolved,
            'companies' => $companies,
            'total' => $companies->total(),
            'indexable' => $companies->total() >= $min,
            'canonical' => $page > 1 ? $baseUrl.'?page='.$page : $baseUrl,
            'trail' => $this->landing->trail($categoryModel),
            'subcategories' => $this->landing->subcategories($categoryModel, $resolved),
            'otherCities' => $otherCities,
            'otherRegions' => $otherRegions,
            'categoryUrl' => CategoryLanding::url($categoryModel->slug),
        ]);
    }
}
