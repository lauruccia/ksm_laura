<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->company->orders()
            ->with('items')
            ->when($request->string('stato')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('vendor.orders.index', ['orders' => $orders, 'statuses' => Order::STATUSES]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOrder($request, $order);

        return view('vendor.orders.show', ['order' => $order->load('items', 'user')]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
        ]);

        $order->update($data);

        return back()->with('success', __('Stato ordine aggiornato.'));
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->company_id === $request->user()->company->id, 403);
    }
}
