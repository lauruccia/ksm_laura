<?php

namespace App\Payments\Subscriptions;

use App\Models\AdminTransaction;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

/**
 * Apre e chiude i periodi di abbonamento.
 *
 * E' idempotente: un rientro ricaricato due volte non fa partire due
 * periodi, e una conferma ripetuta non sposta la scadenza.
 */
class SubscriptionActivator
{
    public function __construct(
        private readonly SubscriptionPricing $pricing = new SubscriptionPricing,
    ) {
    }

    /** Incasso confermato dal gestore: il piano entra in vigore. */
    public function markPaid(
        CompanySubscription $subscription,
        AdminTransaction $transaction,
        ?string $reference,
        array $response
    ): bool {
        return DB::transaction(function () use ($subscription, $transaction, $reference, $response) {
            $fresh = AdminTransaction::whereKey($transaction->id)->lockForUpdate()->first();

            if (! $fresh || $fresh->status === 'completed') {
                return false;
            }

            $fresh->update([
                'status' => 'completed',
                'transaction_id' => $reference ?: $fresh->transaction_id,
                'response_data' => $response,
            ]);

            $this->activate($subscription);

            return true;
        });
    }

    public function markFailed(AdminTransaction $transaction, array $response): void
    {
        if ($transaction->status === 'completed') {
            return;
        }

        $transaction->update(['status' => 'failed', 'response_data' => $response]);
    }

    /**
     * Assegna un piano dall'amministrazione, subito attivo.
     *
     * Non nasce nessun movimento: qui non entrano soldi, e segnarne
     * uno incassato falserebbe i conti. Resta scritto sull'abbonamento
     * che e' stato assegnato in amministrazione.
     */
    public function assign(Company $company, Plan $plan, ?string $notes = null): CompanySubscription
    {
        $quote = $this->pricing->quote($company, $plan);

        return $this->activate(CompanySubscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'replaces_id' => $quote->replaces?->id,
            'status' => CompanySubscription::PENDING,
            'payment_method' => 'admin',
            'price' => $quote->amount,
            'credit' => $quote->credit,
            'currency' => config('ksm.currency'),
            'starts_at' => $quote->startsAt,
            'ends_at' => $quote->endsAt,
            'notes' => $notes,
            // Chi aveva i promemoria spenti non se li ritrova accesi al rinnovo.
            'send_reminders' => $quote->replaces?->send_reminders ?? true,
        ]));
    }

    /**
     * Fa partire il periodo e allinea l'azienda.
     *
     * `companies.plan_id` e' la copia di comodo del piano in vigore:
     * la directory ordina su quella, senza rileggere gli abbonamenti.
     */
    public function activate(CompanySubscription $subscription): CompanySubscription
    {
        return DB::transaction(function () use ($subscription) {
            $subscription->refresh();

            if ($subscription->status === CompanySubscription::ACTIVE) {
                return $subscription;
            }

            // Un'azienda ha un piano solo: gli altri periodi si chiudono.
            CompanySubscription::where('company_id', $subscription->company_id)
                ->whereKeyNot($subscription->id)
                ->where('status', CompanySubscription::ACTIVE)
                ->update(['status' => CompanySubscription::CANCELLED, 'cancelled_at' => now()]);

            // La scadenza la decide il preventivo, non il momento
            // dell'incasso: un rinnovo si attacca in coda, un cambio di
            // piano a meta' periodo tiene la scadenza che c'era. Un piano
            // senza scadenza non ne ha nessuna.
            $plan = $subscription->plan;
            $endsAt = $plan->isLifetime() ? null : ($subscription->ends_at ?: $plan->endsFrom(now()));

            $subscription->update([
                'status' => CompanySubscription::ACTIVE,
                'starts_at' => $subscription->starts_at ?: now(),
                'ends_at' => $endsAt,
            ]);

            $subscription->company->update([
                'plan_id' => $subscription->plan_id,
                'is_active' => true,
            ]);

            return $subscription;
        });
    }

    /**
     * Periodo scaduto senza pagamento: l'azienda si spegne.
     *
     * I dati restano tutti. Basta un rinnovo pagato per riaccenderla,
     * senza rifare niente.
     */
    public function expire(CompanySubscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $subscription->update(['status' => CompanySubscription::EXPIRED]);

            Company::whereKey($subscription->company_id)
                ->where('plan_id', $subscription->plan_id)
                ->update(['plan_id' => null, 'is_active' => false]);
        });
    }

    /** Chiusura decisa in amministrazione: come la scadenza, ma segnata come annullata. */
    public function cancel(CompanySubscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $this->expire($subscription);

            $subscription->update([
                'status' => CompanySubscription::CANCELLED,
                'cancelled_at' => now(),
            ]);
        });
    }
}
