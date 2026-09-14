<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\AdminPaymentSetting;
use App\Models\AdminTransaction;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Plan;
use App\Payments\PaymentException;
use App\Payments\Subscriptions\SubscriptionActivator;
use App\Payments\Subscriptions\SubscriptionGatewayManager;
use App\Payments\Subscriptions\SubscriptionPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Scelta del piano e incasso della quota.
 *
 * Il percorso e' quello del carrello, con due differenze: incassa la
 * piattaforma, e c'e' il bonifico, che nessun gestore puo' confermare.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionGatewayManager $gateways,
        private readonly SubscriptionActivator $activator,
        private readonly SubscriptionPricing $pricing,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $company = $this->company($request);

        if (! $company) {
            return redirect()->route('onboarding.create');
        }

        $plans = Plan::active()->byRank()->get();

        return view('vendor.subscription.index', [
            'company' => $company,
            'plans' => $plans,
            // Il preventivo accanto a ogni piano: chi cambia a meta'
            // periodo deve vedere la differenza prima di decidere.
            'quotes' => $plans->mapWithKeys(fn ($plan) => [
                $plan->id => $this->pricing->quote($company, $plan),
            ]),
            'current' => $company->activeSubscription(),
            'pending' => $company->pendingSubscription(),
            'history' => $company->subscriptions()->with('plan')->latest()->take(10)->get(),
        ]);
    }

    /** Scelta del piano: apre un periodo in attesa di incasso. */
    public function store(Request $request): RedirectResponse
    {
        $company = $this->companyOrFail($request);

        $data = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::active()->findOrFail($data['plan_id']);
        $quote = $this->pricing->quote($company, $plan);

        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'replaces_id' => $quote->replaces?->id,
            'status' => CompanySubscription::PENDING,
            'price' => $quote->amount,
            'credit' => $quote->credit,
            'currency' => config('ksm.currency'),
            'starts_at' => $quote->startsAt,
            'ends_at' => $quote->endsAt,
        ]);

        // Niente da incassare: piano gratuito, oppure un cambio che il
        // residuo gia' pagato copre per intero. Parte subito.
        if ($quote->isFree()) {
            $this->activator->activate($subscription);

            return redirect()->route('subscription.success', $subscription);
        }

        return redirect()->route('subscription.payment', $subscription);
    }

    public function payment(Request $request, CompanySubscription $subscription): View|RedirectResponse
    {
        $this->authorizeSubscription($request, $subscription);

        if ($subscription->status === CompanySubscription::ACTIVE) {
            return redirect()->route('subscription.success', $subscription);
        }

        $settings = AdminPaymentSetting::current();

        return view('vendor.subscription.payment', [
            'subscription' => $subscription->load('plan'),
            'methods' => $this->gateways->availableFor($settings),
            'settings' => $settings,
        ]);
    }

    public function pay(Request $request, CompanySubscription $subscription): RedirectResponse
    {
        $this->authorizeSubscription($request, $subscription);

        $settings = AdminPaymentSetting::current();
        $methods = $this->gateways->availableFor($settings);

        $data = $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', $methods)],
        ]);

        $subscription->update(['payment_method' => $data['method']]);
        $transaction = $this->openTransaction($subscription, $data['method'], $settings->mode);

        if ($this->gateways->isBankTransfer($data['method'])) {
            // Nessun gestore da interrogare: si aspetta la conferma in amministrazione.
            return redirect()->route('subscription.bank', $subscription);
        }

        try {
            $redirectUrl = $this->gateways->driver($data['method'], $settings)->start($subscription, $transaction);
        } catch (PaymentException $e) {
            Log::warning('Avvio pagamento piano non riuscito', [
                'subscription' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            $this->activator->markFailed($transaction, ['error' => $e->getMessage()]);

            return back()->with('error', __('Non siamo riusciti ad aprire il pagamento. Riprova o scegli un altro metodo.'));
        }

        return redirect()->away($redirectUrl);
    }

    /** Rientro dal gestore: l'esito si chiede al gestore, non ai parametri. */
    public function returnFromGateway(Request $request, CompanySubscription $subscription): RedirectResponse
    {
        $this->authorizeSubscription($request, $subscription);

        $transaction = $this->latestTransaction($subscription);

        if (! $transaction) {
            return redirect()->route('subscription.cancelled', $subscription);
        }

        if ($transaction->status === 'completed') {
            return redirect()->route('subscription.success', $subscription);
        }

        try {
            $result = $this->gateways
                ->driver((string) $transaction->payment_method, AdminPaymentSetting::current())
                ->confirm($subscription, $transaction, $request);
        } catch (PaymentException $e) {
            Log::error('Verifica pagamento piano non riuscita', [
                'subscription' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('subscription.cancelled', $subscription)
                ->with('error', __('Non siamo riusciti a verificare il pagamento.'));
        }

        if (! $result['paid']) {
            $this->activator->markFailed($transaction, $result['response']);

            return redirect()->route('subscription.cancelled', $subscription)
                ->with('error', __('Il pagamento non risulta completato.'));
        }

        $this->activator->markPaid($subscription, $transaction, $result['reference'], $result['response']);

        return redirect()->route('subscription.success', $subscription);
    }

    public function bankTransfer(Request $request, CompanySubscription $subscription): View
    {
        $this->authorizeSubscription($request, $subscription);

        return view('vendor.subscription.bank', [
            'subscription' => $subscription->load('plan'),
            'settings' => AdminPaymentSetting::current(),
        ]);
    }

    public function success(Request $request, CompanySubscription $subscription): View
    {
        $this->authorizeSubscription($request, $subscription);

        return view('vendor.subscription.success', ['subscription' => $subscription->load('plan')]);
    }

    public function cancelled(Request $request, CompanySubscription $subscription): View
    {
        $this->authorizeSubscription($request, $subscription);

        return view('vendor.subscription.cancelled', ['subscription' => $subscription->load('plan')]);
    }

    /**
     * Il movimento su cui si appoggia il tentativo in corso.
     *
     * Se il precedente e' ancora aperto viene riusato, cosi' un
     * ripensamento sul metodo non lascia movimenti orfani.
     */
    private function openTransaction(CompanySubscription $subscription, string $method, string $mode): AdminTransaction
    {
        $transaction = $this->latestTransaction($subscription);

        if ($transaction && $transaction->status === 'pending') {
            $transaction->update(['payment_method' => $method, 'mode' => $mode, 'transaction_id' => null]);

            return $transaction;
        }

        return AdminTransaction::create([
            'company_id' => $subscription->company_id,
            'plan_id' => $subscription->plan_id,
            'subscription_id' => $subscription->id,
            'payment_method' => $method,
            'mode' => $mode,
            'amount' => $subscription->price,
            'currency' => $subscription->currency,
            'status' => 'pending',
        ]);
    }

    private function latestTransaction(CompanySubscription $subscription): ?AdminTransaction
    {
        return $subscription->transactions()->latest('id')->first();
    }

    private function company(Request $request): ?Company
    {
        return $request->user()->company;
    }

    private function companyOrFail(Request $request): Company
    {
        $company = $this->company($request);

        abort_unless($company, 403, __('Serve prima creare il profilo azienda.'));

        return $company;
    }

    private function authorizeSubscription(Request $request, CompanySubscription $subscription): void
    {
        abort_unless($subscription->company_id === $this->company($request)?->id, 403);
    }
}
