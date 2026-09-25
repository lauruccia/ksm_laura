<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Payments\KMoney\KMoneyPercentages;
use App\Support\BulkSelection;
use App\Support\ProductForm;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VendorProductController extends Controller
{
    public function __construct(private ProductForm $form) {}

    public function index(Request $request): View
    {
        $company = $request->user()->company;

        $products = $this->filters($company->products()->with('category')->getQuery(), $request)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('vendor.products.index', [
            'products' => $products,
            'inDebt' => $this->form->inDebt($company),
            'kmoneySteps' => $this->form->kmoneySteps($company),
        ]);
    }

    /** I filtri dell'elenco, gli stessi per la pagina e per "tutti i risultati". */
    public function filters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->string('cerca')->toString(), fn ($q, $t) => $q->where('name', 'like', "%$t%"))
            ->when(in_array($request->input('stato'), ['active', 'inactive'], true), fn ($q) => $q->where('status', $request->input('stato')));
    }

    /**
     * Attiva, disattiva, cambia la quota KMoney o elimina piu' prodotti insieme,
     * sempre e solo fra quelli dell'azienda.
     */
    public function bulk(Request $request, KMoneyPercentages $percentages): RedirectResponse
    {
        $company = $request->user()->company;
        $inDebt = $this->form->inDebt($company);
        $actions = $inDebt ? ['activate', 'deactivate', 'delete'] : BulkSelection::ACTIONS;

        $request->validate(BulkSelection::rules($actions) + [
            'percent' => ['required_if:action,kmoney', 'nullable', Rule::in(array_merge(['auto'], $this->form->kmoneySteps($company)))],
        ], BulkSelection::messages());

        $query = BulkSelection::query($request, $company->products()->getQuery(), $this->filters(...));

        $message = match ($request->input('action')) {
            'activate' => trans_choice(':count prodotto attivato.|:count prodotti attivati.', $query->update(['status' => 'active'])),
            'deactivate' => trans_choice(':count prodotto disattivato.|:count prodotti disattivati.', $query->update(['status' => 'inactive'])),
            'delete' => trans_choice(':count prodotto eliminato.|:count prodotti eliminati.', $this->deleteAll($query)),
            'kmoney' => __('Quota KMoney aggiornata su :count prodotti.', ['count' => $percentages->setProductPercent(
                $company,
                $query->pluck('id')->all(),
                $request->input('percent') === 'auto' ? null : (int) $request->input('percent'),
            )]),
        };

        return back()->with('success', $message);
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

    public function create(Request $request): View
    {
        return $this->formView($request, new Product());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->form->save($request, new Product(), $request->user()->company);

        return redirect()->route('vendor.products.index')->with('success', __('Prodotto creato.'));
    }

    public function edit(Request $request, Product $product): View
    {
        $this->authorizeProduct($request, $product);

        return $this->formView($request, $product->load('variants'));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $this->form->save($request, $product, $request->user()->company);

        return back()->with('success', __('Prodotto aggiornato.'));
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->delete();

        return redirect()->route('vendor.products.index')->with('success', __('Prodotto eliminato.'));
    }

    public function toggleStatus(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeProduct($request, $product);
        $product->update(['status' => $product->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', __('Stato aggiornato.'));
    }

    private function formView(Request $request, Product $product): View
    {
        $company = $request->user()->company;

        return view('vendor.products.form', [
            'product' => $product,
            'inDebt' => $this->form->inDebt($company),
            'kmoneySteps' => $this->form->kmoneySteps($company),
            'categories' => ProductCategory::orderBy('name')->get(),
            'brands' => ProductBrand::orderBy('name')->get(),
        ]);
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->company_id === $request->user()->company->id, 403);
    }
}
