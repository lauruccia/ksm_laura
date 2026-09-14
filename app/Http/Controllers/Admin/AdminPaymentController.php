<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminTransaction;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.payments.index', [
            'payments' => Payment::with(['user', 'company'])
                ->when($request->string('stato')->toString(), fn ($q, $s) => $q->where('status', $s))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'subscriptions' => AdminTransaction::with(['company', 'plan'])->latest()->take(10)->get(),
        ]);
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $payment->delete();

        return back()->with('success', __('Movimento eliminato.'));
    }
}
