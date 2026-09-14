<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ProductCategory;
use App\Payments\KMoney\KMoneyPercentages;
use App\Payments\KMoney\KMoneyShare;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Le quote KMoney decise dal venditore: per categoria e per prodotti selezionati.
 *
 * Con il conto KMoney in debito non si decide niente: la quota e' 100
 * su tutto, finche' il conto non torna in positivo.
 */
class VendorKMoneyController extends Controller
{
    public function __construct(private readonly KMoneyPercentages $percentages)
    {
    }

    /** Una riga per ogni categoria in cui il venditore ha prodotti. */
    public function edit(Request $request): View
    {
        $company = $request->user()->company;
        $categoryIds = $company->products()->whereNotNull('category_id')->distinct()->pluck('category_id');

        return view('vendor.kmoney', [
            'settings' => $company->paymentSettings,
            'categories' => ProductCategory::whereKey($categoryIds)->orderBy('name')->get(['id', 'name']),
            'rules' => KMoneyPercentages::rulesFor($company->id),
            'steps' => KMoneyShare::STEPS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->ensureNotInDebt($company);

        $data = $request->validate([
            'rules' => ['nullable', 'array'],
            'rules.*' => ['nullable', Rule::in(KMoneyShare::STEPS)],
        ]);

        $this->percentages->saveCategoryRules($company, $data['rules'] ?? []);

        return back()->with('success', __('Quote KMoney per categoria salvate.'));
    }

    /** La stessa quota su piu' prodotti selezionati insieme. */
    public function bulk(Request $request): RedirectResponse
    {
        $company = $request->user()->company;
        $this->ensureNotInDebt($company);

        $data = $request->validate([
            'products' => ['required', 'array'],
            'products.*' => ['integer'],
            'percent' => ['required', Rule::in(array_merge(['auto'], KMoneyShare::STEPS))],
        ], [
            'products.required' => __('Seleziona almeno un prodotto.'),
        ]);

        $count = $this->percentages->setProductPercent(
            $company,
            $data['products'],
            $data['percent'] === 'auto' ? null : (int) $data['percent']
        );

        return back()->with('success', __('Quota KMoney aggiornata su :count prodotti.', ['count' => $count]));
    }

    private function ensureNotInDebt(Company $company): void
    {
        abort_if(
            (bool) $company->paymentSettings?->kmoney_in_debt,
            403,
            __('Con il conto KMoney in debito la quota e al 100% e non si puo cambiare.')
        );
    }
}
