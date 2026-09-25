<?php

namespace App\Payments\Subscriptions;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Plan;

/**
 * Decide cosa deve pagare un'azienda che sceglie un piano.
 *
 * Tre casi:
 *
 * - primo piano, o piano scaduto: quota intera, periodo che parte oggi;
 * - rinnovo dello stesso piano: quota intera, periodo attaccato in coda
 *   a quello in corso, cosi' chi rinnova in anticipo non perde giorni;
 * - cambio di piano a meta' periodo: si paga la differenza per i giorni
 *   che restano e la scadenza non si sposta. Se il piano nuovo costa
 *   meno, non si paga nulla e non si rimborsa nulla.
 *
 * I piani senza scadenza cambiano il conto in due punti: passare a uno
 * di loro costa la quota intera, scontato il residuo del piano attuale;
 * lasciarne uno vale come sconto tutta la quota pagata.
 */
class SubscriptionPricing
{
    /**
     * @param  CompanySubscription|false|null  $current  il periodo in corso, se chi chiama
     *                                                  lo ha gia' letto (false: da leggere)
     */
    public function quote(Company $company, Plan $plan, CompanySubscription|false|null $current = false): SubscriptionQuote
    {
        if ($current === false) {
            $current = $company->activeSubscription();
        }

        if (! $current) {
            return new SubscriptionQuote(
                plan: $plan,
                kind: SubscriptionQuote::NEW,
                amount: (float) $plan->price,
                credit: 0,
                startsAt: now(),
                endsAt: $plan->endsFrom(now()),
            );
        }

        if (! $current->ends_at) {
            return $this->fromLifetime($current, $plan);
        }

        if ($current->plan_id === $plan->id) {
            return new SubscriptionQuote(
                plan: $plan,
                kind: SubscriptionQuote::RENEWAL,
                amount: (float) $plan->price,
                credit: 0,
                // Il periodo nuovo comincia dove finisce quello in corso.
                startsAt: $current->ends_at->copy(),
                endsAt: $plan->endsFrom($current->ends_at),
                replaces: $current,
            );
        }

        $remaining = $this->remainingDays($current);
        $ratio = $this->ratio($current, $remaining);

        // Il residuo gia' pagato vale come sconto sul piano nuovo.
        $credit = round((float) $current->price * $ratio, 2);

        // Un piano che non scade non si paga a giorni: quota intera.
        $dovuto = $plan->isLifetime()
            ? (float) $plan->price
            : round((float) $plan->price * $ratio, 2);

        return new SubscriptionQuote(
            plan: $plan,
            kind: SubscriptionQuote::CHANGE,
            amount: max(0, round($dovuto - $credit, 2)),
            credit: $credit,
            startsAt: now(),
            endsAt: $plan->isLifetime() ? null : $current->ends_at->copy(),
            replaces: $current,
            remainingDays: $remaining,
        );
    }

    /**
     * Si parte da un piano senza scadenza.
     *
     * Non c'e' un residuo a giorni: vale tutta la quota pagata. Rinnovarlo
     * non costa niente, perche' non c'e' niente da rinnovare.
     */
    private function fromLifetime(CompanySubscription $current, Plan $plan): SubscriptionQuote
    {
        if ($current->plan_id === $plan->id) {
            return new SubscriptionQuote(
                plan: $plan,
                kind: SubscriptionQuote::RENEWAL,
                amount: 0,
                credit: 0,
                startsAt: now(),
                endsAt: $plan->endsFrom(now()),
                replaces: $current,
            );
        }

        $credit = (float) $current->price;

        return new SubscriptionQuote(
            plan: $plan,
            kind: SubscriptionQuote::CHANGE,
            amount: max(0, round((float) $plan->price - $credit, 2)),
            credit: $credit,
            startsAt: now(),
            endsAt: $plan->endsFrom(now()),
            replaces: $current,
        );
    }

    private function remainingDays(CompanySubscription $current): int
    {
        return max(0, (int) ceil(now()->diffInDays($current->ends_at, absolute: false)));
    }

    /**
     * Quanta parte del periodo in corso resta da consumare.
     *
     * La durata si misura sul periodo vero, non su quella del piano:
     * un periodo nato da un cambio puo' essere piu' corto.
     */
    private function ratio(CompanySubscription $current, int $remaining): float
    {
        $start = $current->starts_at ?: $current->created_at;
        $total = max(1, (int) ceil($start->diffInDays($current->ends_at, absolute: true)));

        return min(1, $remaining / $total);
    }
}
