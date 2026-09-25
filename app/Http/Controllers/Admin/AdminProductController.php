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

    /**
     * Attiva, disattiva, cambia la quota KMoney o elimina piu' prodotti insieme:
     * quelli spuntati o tutti i risultati della ricerca, su ogni pagina.
     */
    public function bulk(Request $request, KMoneyPercentages $percentages): RedirectResponse
    {
        $request->validate(BulkSelection::rules() + [
            'percent' => ['required_if:action,kmoney', 'nullable', Rule::in(array_merge(['auto'], KMoneyShare::STEPS))],
        ], BulkSelection::messages());

        $query = BulkSelection::query($request, Product::query(), $this->filters(...));

        $message = match ($request->input('action')) {
            'activate' => trans_choice(':count prodotto attivato.|:count prodotti attivati.', $query->update(['status' => 'active'])),
            'deactivate' => trans_choice(':count prodotto disattivato.|:count prodotti disattivati.', $query->update(['status' => 'inactive'])),
            'delete' => trans_choice(':count prodotto eliminato.|:count prodotti eliminati.', $this->deleteAll($query)),
            'kmoney' => $this->applyKMoney($percentages, $query->pluck('id')->all(), $request->input('percent')),
        };

        return back()->with('success', $message);
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

    /** Uno per uno, cosi' partono gli eventi del modello come nell'eliminazione singola. */
    private function deleteAll(Builder $query): int
    {
        $count = 0;

        $query->chunkById(200, function ($products) use (&$count) {
            $products->each->delete();
            $count += $products->count();
        });

        return $count;
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', __('Prodotto eliminato.'));
    }
}
