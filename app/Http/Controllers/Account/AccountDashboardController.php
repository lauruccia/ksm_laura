<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Riepilogo di chi acquista.
 *
 * Risponde alle tre domande che uno si fa quando torna sul sito:
 * cosa ho ordinato, a che punto sta, quanto ho speso.
 */
class AccountDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        // I conteggi e gli ultimi ordini sono quelli di questo sito.
        $orders = $user->orders()->forSite();

        return view('account.dashboard', [
            'user' => $user,
            'orderCount' => (clone $orders)->count(),
            'openCount' => (clone $orders)->whereIn('status', ['pending', 'paid', 'shipped'])->count(),
            'spent' => (clone $orders)->whereIn('status', ['paid', 'shipped', 'completed'])->sum('total'),
            'latestOrders' => (clone $orders)->with('company')->latest()->take(5)->get(),
            'statuses' => Order::STATUSES,
        ]);
    }
}
