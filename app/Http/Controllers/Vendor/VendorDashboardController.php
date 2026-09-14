<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\PlanCapabilities;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Riepilogo dell'area azienda.
 *
 * Quello che conta per chi apre questa pagina e' se il profilo e'
 * acceso, fino a quando, e cosa c'e' da fare oggi. I numeri dello
 * shop hanno senso solo se il piano comprende la vendita: senza,
 * non vengono nemmeno contati.
 */
class VendorDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $company = $request->user()->company;
        $sells = $company->allows(PlanCapabilities::SHOP);

        return view('vendor.dashboard', [
            'company' => $company,
            'sells' => $sells,
            'subscription' => $company->activeSubscription(),
            'pendingSubscription' => $company->pendingSubscription(),
            'todo' => $this->todo($company, $sells),
            'productCount' => $sells ? $company->products()->count() : 0,
            'activeProducts' => $sells ? $company->products()->active()->count() : 0,
            'orderCount' => $sells ? $company->orders()->count() : 0,
            'pending' => $sells ? $company->orders()->where('status', 'pending')->count() : 0,
            'revenue' => $sells
                ? $company->orders()->whereIn('status', ['paid', 'shipped', 'completed'])->sum('total')
                : 0,
            'reviewCount' => $company->reviews()->count(),
            'latestOrders' => $sells
                ? $company->orders()->with('items')->latest()->take(8)->get()
                : collect(),
            'statuses' => Order::STATUSES,
        ]);
    }

    /**
     * Cosa manca per essere presentabili.
     *
     * Elenco corto e concreto: ogni voce e' una cosa che si puo' fare
     * subito, con il collegamento alla pagina che la risolve.
     */
    private function todo($company, bool $sells): array
    {
        $todo = [];

        if (blank($company->company_description)) {
            $todo[] = ['text' => 'Scrivi la descrizione dell attivita', 'url' => route('vendor.profile.edit')];
        }

        if (blank($company->logo) && $company->allows(PlanCapabilities::LOGO)) {
            $todo[] = ['text' => 'Carica il logo', 'url' => route('vendor.profile.edit')];
        }

        if (blank($company->phone) && blank($company->email)) {
            $todo[] = ['text' => 'Aggiungi un recapito', 'url' => route('vendor.profile.edit')];
        }

        if ($sells && $company->products()->count() === 0) {
            $todo[] = ['text' => 'Inserisci il primo prodotto', 'url' => route('vendor.products.create')];
        }

        if ($sells && ! $company->paymentSettings?->availableMethods()) {
            $todo[] = ['text' => 'Attiva un metodo di incasso', 'url' => route('vendor.payments.edit')];
        }

        return $todo;
    }
}
