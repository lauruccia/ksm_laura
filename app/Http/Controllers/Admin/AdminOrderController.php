<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.orders.index', [
            'orders' => Order::with(['company', 'user'])
                ->when($request->string('stato')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'statuses' => Order::STATUSES,
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load('items', 'company', 'user', 'payment'),
            'statuses' => Order::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $order->update($request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
        ]));

        return back()->with('success', __('Stato ordine aggiornato.'));
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return back()->with('success', __('Ordine eliminato.'));
    }
}
