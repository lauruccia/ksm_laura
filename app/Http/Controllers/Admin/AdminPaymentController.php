<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTransaction;
use App\Models\Payment;
use App\Support\BulkSelection;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.payments.index', [
            'payments' => $this->filters(Payment::with(['user', 'company']), $request)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'subscriptions' => AdminTransaction::with(['company', 'plan'])->latest()->take(10)->get(),
            'statuses' => Payment::query()->distinct()->orderBy('status')->pluck('status')->filter()->values(),
            'methods' => Payment::query()->distinct()->orderBy('method')->pluck('method')->filter()->values(),
        ]);
    }

    /** I filtri dell'elenco, gli stessi per la pagina e per "tutti i risultati". */
    public function filters(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->string('stato')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('metodo')->toString(), fn ($q, $m) => $q->where('method', $m));
    }

    /** Elimina piu' movimenti insieme, solo fra quelli spuntati. */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate(BulkSelection::rules(['delete'], onlySelected: ['delete']), BulkSelection::messages());

        $count = BulkSelection::deleteEach(BulkSelection::query($request, Payment::query(), $this->filters(...)));

        return back()->with('success', trans_choice(':count movimento eliminato.|:count movimenti eliminati.', $count));
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->delete();

        return back()->with('success', __('Movimento eliminato.'));
    }
}
