<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoryLandingText;
use App\Models\CompanyCategory;
use App\Support\CategoryLanding;
use App\Support\CategoryTree;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Testi delle pagine /categorie/...: un elenco delle pagine che meritano un
 * testo (le piu' ricche di aziende per prime) e un modulo per scriverlo.
 *
 * Una pagina e' una categoria da sola o una categoria in un luogo. Il testo
 * si puo' scrivere per quella pagina, oppure una volta sola come modello per
 * tutti i luoghi di una categoria, con i segnaposto {luogo}, {categoria}...
 */
class AdminCategoryLandingTextController extends Controller
{
    private const PER_PAGE = 40;

    public function __construct(private readonly CategoryLanding $landing)
    {
    }

    public function index(Request $request): View
    {
        [$own, $templates] = $this->written();

        $pages = $this->orderedPages()->map(function (array $page) use ($own, $templates) {
            $page['status'] = $this->status($page, $own, $templates);

            return $page;
        });

        $summary = [
            'total' => $pages->count(),
            'written' => $pages->where('status', '!=', 'auto')->count(),
        ];

        $category = $request->integer('categoria');
        $term = mb_strtolower(trim($request->string('cerca')->toString()));
        $state = $request->string('stato')->toString();

        $filtered = $pages
            ->when($category, fn (Collection $c) => $c->where('category_id', $category))
            ->when($term !== '', fn (Collection $c) => $c->filter(
                fn (array $page) => str_contains(mb_strtolower($page['category'].' '.$page['place_name']), $term)
            ))
            ->when($state === 'senza', fn (Collection $c) => $c->where('status', 'auto'))
            ->when($state === 'con', fn (Collection $c) => $c->where('status', '!=', 'auto'))
            ->values();

        $page = max(1, $request->integer('page', 1));

        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, self::PER_PAGE)->values(),
            $filtered->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.landing-texts.index', [
            'pages' => $paginator,
            'summary' => $summary,
            'categories' => CategoryTree::of(CompanyCategory::class)->labels(),
        ]);
    }

    public function edit(Request $request): View
    {
        [$category, $place, $placeKey] = $this->target($request);

        $record = CategoryLandingText::query()
            ->where('company_category_id', $category->id)
            ->where('place_key', $placeKey)
            ->first() ?? new CategoryLandingText;

        $isTemplate = $placeKey === CategoryLandingText::ALL_PLACES;
        $sample = $isTemplate ? $this->samplePlace($category) : $place;
        $count = $this->count($category, $isTemplate ? ($sample['slug'] ?? '') : ($place['slug'] ?? ''));

        $brand = app(\App\Support\TenantContext::class)->brandName();
        $editParams = fn (string $key) => ['categoria' => $category->id] + ($key !== '' ? ['luogo' => $key] : []);

        return view('admin.landing-texts.edit', [
            'category' => $category,
            'place' => $place,
            'placeKey' => $placeKey,
            'isTemplate' => $isTemplate,
            'record' => $record,
            'defaults' => $this->landing->defaults($category, $sample, $count),
            'placeholders' => CategoryLanding::placeholders($category, $sample, $count, $brand),
            'sampleName' => $sample['name'] ?? null,
            'publicUrl' => CategoryLanding::url($category->slug, $isTemplate ? ($sample['slug'] ?? null) : ($place['slug'] ?? null)),
            'links' => [
                'category' => $placeKey !== '' ? route('admin.landing_texts.edit', $editParams('')) : null,
                'template' => $placeKey !== CategoryLandingText::ALL_PLACES ? route('admin.landing_texts.edit', $editParams(CategoryLandingText::ALL_PLACES)) : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        [$category, , $placeKey] = $this->target($request);

        $data = $request->validate([
            'heading' => ['nullable', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'intro' => ['nullable', 'string', 'max:5000'],
            'body' => ['nullable', 'string', 'max:20000'],
        ]);

        $values = [];

        foreach (CategoryLandingText::FIELDS as $field) {
            $text = trim((string) ($data[$field] ?? ''));
            $values[$field] = $text !== '' ? $text : null;
        }

        $keys = ['company_category_id' => $category->id, 'place_key' => $placeKey];

        if (array_filter($values) === []) {
            CategoryLandingText::query()->where($keys)->delete();
            $message = 'Nessun testo: la pagina torna al testo automatico.';
        } else {
            CategoryLandingText::updateOrCreate($keys, $values);
            $message = 'Testo salvato. Sul sito è già in linea.';
        }

        $next = $request->boolean('vai_avanti') ? $this->nextWithoutText($category->id, $placeKey) : null;

        if ($request->boolean('vai_avanti') && $next === null) {
            $message .= ' Non ci sono altre pagine senza testo.';
        }

        return redirect()
            ->route('admin.landing_texts.edit', $next ?? $this->editParams($category->id, $placeKey))
            ->with('success', $message);
    }

    /** Toglie il testo scritto a mano: la pagina torna a quello automatico. */
    public function destroy(Request $request): RedirectResponse
    {
        [$category, , $placeKey] = $this->target($request);

        CategoryLandingText::query()
            ->where('company_category_id', $category->id)
            ->where('place_key', $placeKey)
            ->delete();

        return redirect()
            ->route('admin.landing_texts.edit', $this->editParams($category->id, $placeKey))
            ->with('success', 'Testo tolto: la pagina usa di nuovo quello automatico.');
    }

    /**
     * La pagina scelta nell'indirizzo: categoria e luogo.
     *
     * `luogo` vuoto e' la pagina della categoria, `*` il modello per tutti i luoghi.
     *
     * @return array{0: CompanyCategory, 1: array<string, mixed>|null, 2: string}
     */
    private function target(Request $request): array
    {
        $category = CompanyCategory::query()->findOrFail($request->integer('categoria'));
        $placeKey = trim((string) $request->input('luogo', ''));

        abort_unless($placeKey === '' || $placeKey === CategoryLandingText::ALL_PLACES || preg_match('/^[a-z0-9-]+$/', $placeKey) === 1, 404);

        $place = null;

        if ($placeKey !== '' && $placeKey !== CategoryLandingText::ALL_PLACES) {
            $place = $this->landing->resolvePlace($category, $placeKey);
            abort_if($place === null, 404);
        }

        return [$category, $place, $placeKey];
    }

    /** @return array<string, int|string> */
    private function editParams(int $categoryId, string $placeKey): array
    {
        return ['categoria' => $categoryId] + ($placeKey !== '' ? ['luogo' => $placeKey] : []);
    }

    /** Le pagine dalla piu' ricca di aziende, a parita' per nome. */
    private function orderedPages(): Collection
    {
        return collect($this->landing->pages())
            ->sortBy([['count', 'desc'], ['category', 'asc'], ['place', 'asc']])
            ->values();
    }

    /**
     * Cosa c'e' scritto a mano: le pagine con un testo proprio e le categorie con un modello.
     *
     * @return array{0: array<string, true>, 1: array<int, true>}
     */
    private function written(): array
    {
        $own = [];
        $templates = [];

        foreach (CategoryLandingText::query()->get(['company_category_id', 'place_key']) as $row) {
            if ($row->place_key === CategoryLandingText::ALL_PLACES) {
                $templates[$row->company_category_id] = true;
            } else {
                $own[$row->company_category_id.'|'.$row->place_key] = true;
            }
        }

        return [$own, $templates];
    }

    /** own: scritto per questa pagina; template: coperta dal modello; auto: testo automatico. */
    private function status(array $page, array $own, array $templates): string
    {
        return match (true) {
            isset($own[$page['category_id'].'|'.$page['place']]) => 'own',
            $page['place'] !== '' && isset($templates[$page['category_id']]) => 'template',
            default => 'auto',
        };
    }

    /** La prossima pagina, nell'ordine dell'elenco, che ha ancora il testo automatico. */
    private function nextWithoutText(int $categoryId, string $placeKey): ?array
    {
        [$own, $templates] = $this->written();
        $pages = $this->orderedPages();
        $current = $placeKey === CategoryLandingText::ALL_PLACES ? '' : $placeKey;

        $position = $pages->search(fn (array $page) => $page['category_id'] === $categoryId && $page['place'] === $current);
        $after = $position === false ? $pages : $pages->slice($position + 1);

        $next = $after->first(fn (array $page) => $this->status($page, $own, $templates) === 'auto');

        return $next ? $this->editParams($next['category_id'], $next['place']) : null;
    }

    /** Una citta' di esempio per il modello: la piu' ricca di aziende nella categoria. */
    private function samplePlace(CompanyCategory $category): ?array
    {
        $cities = $this->landing->places($category)['cities'];
        $slug = array_key_first($cities);

        return $slug === null ? null : ['type' => 'city', 'slug' => $slug] + $cities[$slug];
    }

    /** Quante aziende ha la pagina (la categoria intera se `$placeSlug` e' vuoto). */
    private function count(CompanyCategory $category, string $placeSlug): int
    {
        foreach ($this->landing->pages() as $page) {
            if ($page['category_id'] === $category->id && $page['place'] === $placeSlug) {
                return $page['count'];
            }
        }

        return 0;
    }
}
