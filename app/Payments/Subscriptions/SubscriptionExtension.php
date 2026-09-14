<?php

namespace App\Payments\Subscriptions;

use App\Models\CompanySubscription;
use App\Models\Plan;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rinnovo in blocco deciso in amministrazione.
 *
 * Allunga di un periodo gli abbonamenti attivi di un piano che scade,
 * senza incasso e senza aprire periodi nuovi: e' la stessa scadenza che
 * si sposta in avanti. Serve per le aziende inserite da Gruppo Kosmos,
 * che nessuno rinnoverebbe una per una.
 *
 * Un solo UPDATE: su centomila righe un giro in PHP durerebbe minuti, e
 * la pagina andrebbe in timeout prima di finire.
 */
class SubscriptionExtension
{
    /** Gli abbonamenti che il rinnovo toccherebbe. */
    public function query(Plan $plan, ?CarbonInterface $endingBefore = null): Builder
    {
        return CompanySubscription::query()
            ->active()
            ->where('plan_id', $plan->id)
            ->whereNotNull('ends_at')
            ->when($endingBefore, fn ($q, $date) => $q->where('ends_at', '<=', $date));
    }

    /** Restituisce quanti abbonamenti sono stati allungati. */
    public function extend(Plan $plan, ?CarbonInterface $endingBefore = null): int
    {
        if ($plan->isLifetime()) {
            throw new InvalidArgumentException("Il piano $plan->name non scade: non c'e' niente da rinnovare.");
        }

        return $this->query($plan, $endingBefore)->toBase()->update([
            'ends_at' => DB::raw($this->addDays((int) $plan->duration_days)),
            // Periodo nuovo, tappe dei promemoria da capo.
            'reminders_sent' => null,
            'updated_at' => now(),
        ]);
    }

    /** L'aritmetica sulle date cambia da un database all'altro. */
    private function addDays(int $days): string
    {
        return match (DB::getDriverName()) {
            'sqlite' => "datetime(ends_at, '+$days days')",
            'pgsql' => "ends_at + interval '$days days'",
            default => "DATE_ADD(ends_at, INTERVAL $days DAY)",
        };
    }
}
