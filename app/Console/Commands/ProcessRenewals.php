<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiring;
use App\Models\CompanySubscription;
use App\Payments\Subscriptions\SubscriptionActivator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Il giro quotidiano dei rinnovi.
 *
 * Avvisa a un mese, a quindici giorni e il giorno prima; alla scadenza
 * spegne l'azienda e lo dice. Ogni tappa parte una volta sola, perche'
 * resta segnata sull'abbonamento: il comando si puo' lanciare due volte
 * nello stesso giorno senza rimandare niente.
 */
class ProcessRenewals extends Command
{
    /** Giorni che mancano alla scadenza, dal piu' lontano al piu' vicino. */
    public const MILESTONES = [30, 15, 1];

    protected $signature = 'subscriptions:renewals';

    protected $description = 'Avvisa delle scadenze vicine e spegne le aziende con il piano scaduto';

    public function handle(SubscriptionActivator $activator): int
    {
        $avvisati = 0;
        $scaduti = 0;

        $subscriptions = CompanySubscription::query()
            ->where('status', CompanySubscription::ACTIVE)
            ->whereNotNull('ends_at')
            // Solo chi e' gia' dentro la prima tappa: gli altri oggi non hanno
            // niente da ricevere, e sono quasi centomila righe da non caricare.
            ->where('ends_at', '<=', now()->addDays(max(self::MILESTONES)))
            ->with(['company.user', 'plan'])
            ->lazyById(500);

        foreach ($subscriptions as $subscription) {
            $daysLeft = $subscription->daysLeft();

            if ($daysLeft > 0) {
                $avvisati += $this->remind($subscription, $daysLeft) ? 1 : 0;

                continue;
            }

            $activator->expire($subscription);

            if ($subscription->send_reminders) {
                $this->notify($subscription, 0);
            }

            $scaduti++;
        }

        $this->info("Promemoria inviati: $avvisati. Abbonamenti scaduti: $scaduti.");

        return self::SUCCESS;
    }

    /** La tappa piu' vicina non ancora avvisata, se ce n'e' una. */
    private function remind(CompanySubscription $subscription, int $daysLeft): bool
    {
        // Promemoria spenti, come per le aziende inserite da Gruppo Kosmos.
        // Le tappe non si segnano: riaccesi, parte la piu' vicina.
        if (! $subscription->send_reminders) {
            return false;
        }

        foreach (self::MILESTONES as $milestone) {
            if ($daysLeft > $milestone || $subscription->reminderSent($milestone)) {
                continue;
            }

            $this->notify($subscription, $milestone);
            $subscription->markReminderSent($milestone);

            return true;
        }

        return false;
    }

    /**
     * Manda l'avviso a chi gestisce l'azienda.
     *
     * Se la posta non parte il giro non si ferma: la tappa resta segnata
     * comunque, altrimenti il giorno dopo ripartirebbe da capo per tutti.
     */
    private function notify(CompanySubscription $subscription, int $daysLeft): void
    {
        $address = $subscription->company?->email ?: $subscription->company?->user?->email;

        if (blank($address)) {
            return;
        }

        try {
            Mail::to($address)->send(new SubscriptionExpiring($subscription, $daysLeft));
        } catch (\Throwable $e) {
            Log::warning('Promemoria di rinnovo non inviato', [
                'subscription' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
