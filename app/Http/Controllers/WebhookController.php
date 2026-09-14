<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentNotification;
use App\Models\Company;
use App\Payments\GatewayManager;
use App\Payments\PaymentException;
use App\Payments\WebhookAware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Notifiche dei gestori di pagamento.
 *
 * Servono quando l'acquirente non torna sul sito: senza questa strada
 * l'ordine resterebbe in attesa pur essendo stato pagato.
 *
 * La rotta e' pubblica per forza di cose. A proteggerla e' la firma
 * della notifica, verificata subito dal driver del gestore. Il resto
 * del lavoro va in coda, come raccomandano sia Stripe sia PayPal.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly GatewayManager $gateways)
    {
    }

    public function stripe(Request $request, Company $company): Response
    {
        return $this->handle($request, $company, 'stripe');
    }

    public function paypal(Request $request, Company $company): Response
    {
        return $this->handle($request, $company, 'paypal');
    }

    public function kmoney(Request $request, Company $company): Response
    {
        return $this->handle($request, $company, 'kmoney');
    }

    private function handle(Request $request, Company $company, string $method): Response
    {
        $settings = $company->paymentSettings;

        if (! $settings) {
            return response('azienda senza impostazioni di incasso', 404);
        }

        $driver = $this->gateways->driver($method, $settings);

        if (! $driver instanceof WebhookAware) {
            return response('notifiche non gestite per questo metodo', 404);
        }

        try {
            $reference = $driver->webhookReference($request);
        } catch (PaymentException $e) {
            Log::warning('Notifica di pagamento rifiutata', [
                'company' => $company->id,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            // 400 dice al gestore che la notifica non e' stata accettata.
            return response('firma non valida', 400);
        }

        if (! $reference) {
            return response('evento ignorato', 200);
        }

        ProcessPaymentNotification::dispatch($company->id, $method, $reference);

        return response('accettata', 200);
    }
}
