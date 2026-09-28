<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Orders\OrderStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VendorOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()->company->orders()
            ->with(['items', 'domain', 'company'])
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

    public function updateStatus(Request $request, Order $order, OrderStatus $orderStatus): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        // Stato, spedizione, disponibilita' ed email al cliente come in amministrazione.
        $data = $request->validate(OrderStatus::rules());

        return back()->with(...$orderStatus->applyForm($order, $data, $request->boolean('notify')));
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->company_id === $request->user()->company->id, 403);
    }
}
