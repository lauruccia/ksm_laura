<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    /** Il sito da cui arriva il messaggio: KSM o il dominio della rete. */
    public string $siteName;

    public function __construct(
        public array $data,
        public ?string $recipientName = null,
    ) {
        $this->siteName = (string) config('app.name');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data['subject'] ?? __('Nuovo messaggio da :site', ['site' => $this->siteName]),
            replyTo: [$this->data['email']],
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.contact');
    }
}
