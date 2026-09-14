<?php

namespace App\Payments\KMoney;

use App\Models\CompanyPaymentSetting;
use App\Payments\PaymentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client dell'API KMoney v1, la stessa del plugin WooCommerce 2.0.
 *
 * Si autentica con il token del venditore ("km_..."), creato sul portale
 * KMoney con il permesso di scrittura. Gli importi viaggiano in centesimi
 * di KY, interi, mai decimali.
 */
class KMoneyClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {
    }

    public static function forVendor(CompanyPaymentSetting $settings): self
    {
        $baseUrl = (string) config('ksm.kmoney.base_url');

        if ($baseUrl === '' || blank($settings->kmoney_api_token)) {
            throw new PaymentException('KMoney non configurato: servono KMONEY_API_BASE_URL e il token del venditore.');
        }

        return new self($baseUrl, $settings->kmoney_api_token);
    }

    /**
     * Crea la richiesta di pagamento ospitata da KMoney.
     *
     * Con lo stesso riferimento e lo stesso importo KMoney restituisce la
     * richiesta ancora aperta invece di crearne un'altra: un nuovo tentativo
     * non ne moltiplica.
     *
     * @return array{uuid?: string, token?: string, pay_url?: string}
     */
    public function createPaymentRequest(
        int $amountCents,
        string $description,
        string $reference,
        string $returnUrl,
        string $cancelUrl,
        int $expiresInMinutes = 30,
    ): array {
        return $this->send(fn (PendingRequest $http) => $http->post('/payment-requests', [
            'amount' => $amountCents,
            'description' => $description,
            'external_reference' => $reference,
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'expires_in_minutes' => $expiresInMinutes,
        ]));
    }

    /** Stato di una richiesta: e' l'unica fonte per dire che e' pagata. */
    public function paymentRequest(string $uuid): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get('/payment-requests/'.rawurlencode($uuid)));
    }

    private function send(callable $call): array
    {
        $http = Http::baseUrl(rtrim((string) $this->baseUrl, '/'))
            ->withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->timeout(20);

        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            throw new PaymentException('KMoney non risponde: '.$e->getMessage(), 0, $e);
        }

        return $this->data($response);
    }

    private function data(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new PaymentException("Risposta KMoney non valida (HTTP {$response->status()}).");
        }

        if ($response->failed()) {
            $reason = $body['error'] ?? $body['message'] ?? 'errore';

            throw new PaymentException("KMoney ha rifiutato la richiesta ({$response->status()}): $reason");
        }

        return is_array($body['data'] ?? null) ? $body['data'] : $body;
    }
}
