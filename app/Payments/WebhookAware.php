<?php

namespace App\Payments;

use Illuminate\Http\Request;

/**
 * Gateway che sa farsi richiamare a pagamento avvenuto.
 *
 * Serve quando l'acquirente non torna sul sito: la notifica arriva
 * comunque e l'ordine non resta in attesa.
 */
interface WebhookAware
{
    /**
     * Verifica la firma della notifica.
     *
     * @return string|null riferimento del gateway se l'evento riguarda un
     *                     incasso, null se l'evento non ci interessa
     *
     * @throws PaymentException se la firma non e' valida o manca la configurazione
     */
    public function webhookReference(Request $request): ?string;
}
