<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Al cliente, quando l'ordine e' spedito o annullato. Parla con il nome del
 * sito su cui ha comprato e rimanda li'; le risposte vanno all'azienda.
 */
class OrderStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        $site = $this->order->siteName();
        $email = $this->order->company?->email;

        return new Envelope(
            subject: match ($this->order->status) {
                'shipped' => __('Il tuo ordine :ref è stato spedito', ['ref' => $this->order->reference]),
                default => __('Il tuo ordine :ref è stato annullato', ['ref' => $this->order->reference]),
            }.' · '.$site,
            replyTo: $email ? [new Address($email, (string) $this->order->company?->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.order-status', with: [
            'order' => $this->order,
            'site' => $this->order->siteName(),
            'company' => $this->order->company,
            // Chi ha un account ritrova l'ordine nella sua area; gli altri lo tracciano con numero ed email.
            'url' => $this->order->user_id
                ? $this->order->siteUrl('/account/ordini/'.$this->order->id)
                : $this->order->siteUrl('/traccia-ordine'),
        ]);
    }
}
