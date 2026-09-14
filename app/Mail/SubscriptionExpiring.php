<?php

namespace App\Mail;

use App\Models\CompanySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Promemoria di rinnovo.
 *
 * Lo stesso messaggio copre tutte le tappe: cambia il tono, non il
 * contenuto. A zero giorni non e' piu' un promemoria, e' l'avviso che
 * l'azienda e' stata spenta.
 */
class SubscriptionExpiring extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public CompanySubscription $subscription,
        public int $daysLeft,
    ) {
    }

    public function envelope(): Envelope
    {
        $name = $this->subscription->plan->name;

        return new Envelope(subject: match (true) {
            $this->daysLeft <= 0 => __('Il piano :piano e scaduto', ['piano' => $name]),
            $this->daysLeft === 1 => __('Il piano :piano scade domani', ['piano' => $name]),
            default => __('Il piano :piano scade fra :giorni giorni', [
                'piano' => $name,
                'giorni' => $this->daysLeft,
            ]),
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.subscription-expiring', with: [
            'company' => $this->subscription->company,
            'plan' => $this->subscription->plan,
            'endsAt' => $this->subscription->ends_at,
            'url' => route('subscription.index'),
        ]);
    }
}
