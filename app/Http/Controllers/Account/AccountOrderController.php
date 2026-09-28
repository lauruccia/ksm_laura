<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Gli ordini di chi ha fatto accesso, e solo i suoi.
 *
 * Il filtro sull'utente sta nella query, non in un controllo dopo:
 * cosi' non c'e' un ramo in cui l'ordine di un altro viene caricato.
 */
class AccountOrderController extends Controller
{
    public function index(Request $request): View
    {
        // Solo gli ordini fatti su questo sito: l'account vale ovunque, gli acquisti no.
        $orders = $request->user()->orders()
            ->forSite()
            ->with('company')
            ->when($request->string('stato')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('account.orders.index', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id
            && Order::query()->forSite()->whereKey($order->getKey())->exists(), 404);

        $order->load(['items.product', 'company', 'payment']);

        return view('account.orders.show', ['order' => $order]);
    }
}
