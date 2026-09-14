<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Payment;
use App\Payments\GatewayManager;
use App\Payments\PaymentCompletion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Chiude un pagamento annunciato da una notifica gia' verificata.
 *
 * La firma si controlla subito, dentro la richiesta del gestore: qui
 * arriva solo il suo riferimento. Cosi' il gestore riceve risposta in
 * pochi millisecondi, e se la verifica dell'incasso non riesce il
 * lavoro viene ripetuto con attese crescenti.
 */
class ProcessPaymentNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly int $companyId,
        public readonly string $method,
        public readonly string $reference,
    ) {
    }

    /** Attese fra un tentativo e l'altro, in secondi. */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(GatewayManager $gateways, PaymentCompletion $completion): void
    {
        $company = Company::with('paymentSettings')->find($this->companyId);

        if (! $company?->paymentSettings) {
            return;
        }

        $payment = Payment::query()
            ->where('company_id', $company->id)
            ->where('method', $this->method)
            ->where('transaction_id', $this->reference)
            ->first();

        // La notifica puo' riguardare la parte in euro o la quota KMoney.
        $order = $payment?->belongingOrder();

        if (! $payment || ! $order) {
            Log::info('Notifica senza pagamento corrispondente', [
                'company' => $company->id,
                'reference' => $this->reference,
            ]);

            return;
        }

        if ($payment->status === 'completed') {
            return;
        }

        // Stessa verifica del rientro: per PayPal e' qui che avviene la cattura.
        // Se il gestore non risponde l'eccezione fa ripetere il lavoro.
        $result = $gateways
            ->driver($this->method, $company->paymentSettings)
            ->confirm($order, $payment, Request::create('/'));

        if (! $result['paid']) {
            Log::info('Incasso non confermato da notifica', ['payment' => $payment->id]);

            return;
        }

        $completion->markPaid($order, $payment, $result['reference'], $result['response']);

        Log::info('Ordine confermato da notifica', ['order' => $order->id]);
    }
}
