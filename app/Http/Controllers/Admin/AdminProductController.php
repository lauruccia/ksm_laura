<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use App\Payments\KMoney\KMoneyPercentages;
use App\Payments\KMoney\KMoneyShare;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.products.index', [
            'products' => Product::with(['company', 'category'])
                ->when($request->string('cerca')->toString(), fn ($q, $t) => $q->where('name', 'like', "%$t%"))
                ->when($request->integer('azienda'), fn ($q, $id) => $q->where('company_id', $id))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'company' => $request->integer('azienda') ? Company::find($request->integer('azienda')) : null,
        ]);
    }

    public function show(Product $product): View
    {
        return view('admin.products.show', ['product' => $product->load('company', 'variants')]);
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

        $percent = $data['percent'] === 'auto' ? null : (int) $data['percent'];
        $count = 0;

        Product::whereKey($data['products'])
            ->with('company')
            ->get(['id', 'company_id'])
            ->groupBy('company_id')
            ->each(function ($products) use ($percentages, $percent, &$count) {
                $count += $percentages->setProductPercent(
                    $products->first()->company,
                    $products->pluck('id')->all(),
                    $percent
                );
            });

        return back()->with('success', __('Quota KMoney aggiornata su :count prodotti.', ['count' => $count]));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return back()->with('success', __('Prodotto eliminato.'));
    }
}
