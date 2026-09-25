<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\BulkSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.orders.index', [
            'orders' => $this->filters(Order::with(['company', 'user']), $request)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'statuses' => Order::STATUSES,
        ]);
    }

    /** I filtri dell'elenco, gli stessi per la pagina e per "tutti i risultati". */
    public function filters(Builder $query, Request $request): Builder
    {
        $term = trim($request->string('cerca')->toString());
        // "KSM-000123" o "123": si cerca anche per numero d'ordine.
        $number = (int) preg_replace('/\D/', '', $term);

        return $query
            ->when($request->string('stato')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($term !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('billing_name', 'like', "%$term%")
                ->orWhere('billing_email', 'like', "%$term%")
                ->when($number > 0, fn ($q) => $q->orWhere('id', $number))));
    }

    /** Cambia lo stato o elimina piu' ordini insieme; eliminare vale solo sulle righe spuntate. */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate(BulkSelection::rules(['status', 'delete'], onlySelected: ['delete']) + [
            'status' => ['required_if:action,status', 'nullable', 'in:'.implode(',', Order::STATUSES)],
        ], BulkSelection::messages());

        $query = BulkSelection::query($request, Order::query(), $this->filters(...));

        $message = match ($request->input('action')) {
            'status' => trans_choice(':count ordine segnato come «:status».|:count ordini segnati come «:status».',
                $query->update(['status' => $request->input('status')]),
                ['status' => Order::STATUS_LABELS[$request->input('status')]]),
            'delete' => trans_choice(':count ordine eliminato.|:count ordini eliminati.', BulkSelection::deleteEach($query)),
        };

        return back()->with('success', $message);
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
