<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Payments\KMoney\KMoneyPercentages;
use App\Payments\KMoney\KMoneyShare;
use App\Support\BulkSelection;
use App\Support\PlanCapabilities;
use App\Support\ProductForm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.products.index', [
            'products' => $this->filters(Product::with(['company', 'category']), $request)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'company' => $request->integer('azienda') ? Company::find($request->integer('azienda')) : null,
            'categories' => ProductCategory::orderBy('name')->pluck('name', 'id'),
            'brands' => ProductBrand::orderBy('name')->pluck('name', 'id'),
            'companies' => Company::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** I filtri dell'elenco, gli stessi per la pagina e per "tutti i risultati". */
    public function filters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->string('cerca')->toString(), fn ($q, $t) => $q->where('name', 'like', "%$t%"))
            ->when($request->integer('azienda'), fn ($q, $id) => $q->where('company_id', $id))
            ->when(in_array($request->input('stato'), ['active', 'inactive'], true), fn ($q) => $q->where('status', $request->input('stato')));
    }

    /** Azioni in blocco dell'elenco: quelle comuni a tutti gli elenchi, piu' categoria, marca e azienda. */
    public const BULK_ACTIONS = ['activate', 'deactivate', 'kmoney', 'category', 'brand', 'company', 'delete'];

    /**
     * Attiva, disattiva, cambia la quota KMoney, la categoria, la marca o l'azienda,
     * o elimina piu' prodotti insieme: quelli spuntati o tutti i risultati della
     * ricerca, su ogni pagina.
     */
    public function bulk(Request $request, KMoneyPercentages $percentages): RedirectResponse
    {
        $request->validate(BulkSelection::rules(self::BULK_ACTIONS) + [
            'percent' => ['required_if:action,kmoney', 'nullable', Rule::in(array_merge(['auto'], KMoneyShare::STEPS))],
            // Vuoto toglie la categoria o la marca.
            'category_id' => ['nullable', 'integer', Rule::exists(ProductCategory::class, 'id')],
            'brand_id' => ['nullable', 'integer', Rule::exists(ProductBrand::class, 'id')],
            'company_id' => ['required_if:action,company', 'nullable', 'integer', Rule::exists(Company::class, 'id')],
        ], BulkSelection::messages() + [
            'company_id.required_if' => __("Scegli l'azienda a cui spostare i prodotti."),
        ]);

        $query = BulkSelection::query($request, Product::query(), $this->filters(...));

        $message = match ($request->input('action')) {
            'activate' => trans_choice(':count prodotto attivato.|:count prodotti attivati.', $query->update(['status' => 'active'])),
            'deactivate' => trans_choice(':count prodotto disattivato.|:count prodotti disattivati.', $query->update(['status' => 'inactive'])),
            'delete' => trans_choice(':count prodotto eliminato.|:count prodotti eliminati.', BulkSelection::deleteEach($query)),
            'kmoney' => $this->applyKMoney($percentages, $query->pluck('id')->all(), $request->input('percent')),
            'category' => $this->applyCategory($percentages, $query, $request->integer('category_id') ?: null),
            'brand' => trans_choice('Marca aggiornata su :count prodotto.|Marca aggiornata su :count prodotti.', $this->updateEach(
                $query, fn () => ['brand_id' => $request->integer('brand_id') ?: null],
            )['count']),
            'company' => $this->applyCompany($percentages, $query, Company::findOrFail($request->integer('company_id'))),
        };

        return back()->with('success', $message);
    }

    /**
     * Un prodotto alla volta, cosi' partono gli eventi del modello (versione e
     * notifiche ai gestionali, come nella modifica singola).
     *
     * @param  \Closure(Product): array<string, mixed>  $attributes
     * @return array{count: int, byCompany: array<int, list<int>>}
     */
    private function updateEach(Builder $query, \Closure $attributes): array
    {
        $count = 0;
        $byCompany = [];

        $query->chunkById(200, function ($products) use ($attributes, &$count, &$byCompany) {
            foreach ($products as $product) {
                $product->update($attributes($product));
                $byCompany[$product->company_id][] = $product->id;
                $count++;
            }
        });

        return ['count' => $count, 'byCompany' => $byCompany];
    }

    /** La stessa categoria su piu' prodotti; la quota KMoney segue le scelte per categoria del venditore. */
    private function applyCategory(KMoneyPercentages $percentages, Builder $query, ?int $categoryId): string
    {
        $done = $this->updateEach($query, fn () => ['category_id' => $categoryId]);

        foreach (Company::whereKey(array_keys($done['byCompany']))->get() as $company) {
            $percentages->refresh($company, $done['byCompany'][$company->id]);
        }

        return trans_choice('Categoria aggiornata su :count prodotto.|Categoria aggiornata su :count prodotti.', $done['count']);
    }

    /**
     * Sposta i prodotti a un'altra azienda. La categoria resta; una quota KMoney
     * scelta sul prodotto si adatta a quelle ammesse dal conto della nuova azienda,
     * poi la quota effettiva si ricalcola con le regole della nuova azienda.
     */
    private function applyCompany(KMoneyPercentages $percentages, Builder $query, Company $target): string
    {
        $steps = KMoneyShare::steps($target->paymentSettings()->first());

        $done = $this->updateEach($query, fn (Product $product) => [
            'company_id' => $target->id,
            'kmoney_discount_percent' => $product->kmoney_discount_percent === null
                ? null
                : KMoneyShare::fit(KMoneyShare::snap($product->kmoney_discount_percent), $steps),
        ]);

        $ids = array_merge(...array_values($done['byCompany'] ?: [[]]));

        if ($ids) {
            $percentages->refresh($target, $ids);
        }

        return trans_choice(':count prodotto spostato a :company.|:count prodotti spostati a :company.', $done['count'], ['company' => $target->name]);
    }

    /**
     * Nuovo prodotto per un'azienda. Senza azienda si sceglie prima quella:
     * le quote KMoney del modulo dipendono dal suo conto.
     */
    public function create(Request $request, ProductForm $form): View
    {
        $company = $request->integer('azienda') ? Company::find($request->integer('azienda')) : null;

        if (! $company) {
            $term = trim($request->string('cerca')->toString());

            return view('admin.products.pick-company', [
                'term' => $term,
                'companies' => $term === '' ? collect() : Company::query()
                    ->where('name', 'like', '%'.addcslashes($term, '\\%_').'%')
                    ->orderBy('name')
                    ->take(20)
                    ->get(['id', 'name', 'city', 'is_active']),
            ]);
        }

        return view('admin.products.create', [
            'company' => $company,
            'product' => new Product(['status' => 'active']),
            'inDebt' => $form->inDebt($company),
            'kmoneySteps' => $form->kmoneySteps($company),
            'categories' => ProductCategory::orderBy('name')->get(),
            'brands' => ProductBrand::orderBy('name')->get(),
            'sells' => $company->allows(PlanCapabilities::SHOP),
        ]);
    }

    public function store(Request $request, ProductForm $form): RedirectResponse
    {
        $request->validate(['company_id' => ['required', 'integer', 'exists:companies,id']]);

        $product = $form->save($request, new Product(), Company::findOrFail($request->integer('company_id')));

        return redirect()->route('admin.products.edit', $product)->with('success', __('Prodotto creato.'));
    }

    public function show(Product $product): View
    {
        return view('admin.products.show', ['product' => $product->load('company', 'variants')]);
    }

    /** Tutto il prodotto, con lo stesso modulo che usa l'azienda. */
    public function edit(Product $product, ProductForm $form): View
    {
        $company = $product->company;

        return view('admin.products.edit', [
            'product' => $product->load('company', 'variants'),
            'inDebt' => $company ? $form->inDebt($company) : false,
            'kmoneySteps' => $company ? $form->kmoneySteps($company) : [],
            'categories' => ProductCategory::orderBy('name')->get(),
            'brands' => ProductBrand::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product, ProductForm $form): RedirectResponse
    {
        abort_unless($product->company, 404);

        $form->save($request, $product, $product->company);

        return back()->with('success', __('Prodotto aggiornato.'));
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['status' => $product->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', __('Stato prodotto aggiornato.'));
    }

    /** La stessa quota KMoney su piu' prodotti selezionati, anche di aziende diverse. */
    public function kmoney(Request $request, KMoneyPercentages $percentages): RedirectResponse
    {
        $data = $request->validate([
            'products' => ['required', 'array'],
            'products.*' => ['integer'],
            'percent' => ['required', Rule::in(array_merge(['auto'], KMoneyShare::STEPS))],
        ], [
            'products.required' => __('Seleziona almeno un prodotto.'),
        ]);

        return back()->with('success', $this->applyKMoney($percentages, $data['products'], $data['percent']));
    }

    private function applyKMoney(KMoneyPercentages $percentages, array $ids, string $choice): string
    {
        $percent = $choice === 'auto' ? null : (int) $choice;
        $count = 0;
        $skipped = 0;

        Product::whereKey($ids)
            ->with('company.paymentSettings')
            ->get(['id', 'company_id'])
            ->groupBy('company_id')
            ->each(function ($products) use ($percentages, $percent, &$count, &$skipped) {
                $company = $products->first()->company;

                // Una quota che KMoney non ammette per quel conto non si scrive.
                if ($percent !== null && ! in_array($percent, KMoneyShare::steps($company->paymentSettings), true)) {
                    $skipped += $products->count();

                    return;
                }

                $count += $percentages->setProductPercent($company, $products->pluck('id')->all(), $percent);
            });

        $message = __('Quota KMoney aggiornata su :count prodotti.', ['count' => $count]);

        if ($skipped) {
            $message .= ' '.__(':skipped prodotti lasciati com\'erano: KMoney non ammette :percent% per il loro venditore.', [
                'skipped' => $skipped, 'percent' => $percent,
            ]);
        }

        return $message;
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', __('Prodotto eliminato.'));
    }
}
