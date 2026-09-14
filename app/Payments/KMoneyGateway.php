<?php

namespace App\Payments;

use App\Models\CompanyPaymentSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\KMoney\KMoneyClient;
use App\Payments\KMoney\KMoneySplitter;
use Illuminate\Http\Request;

/**
 * Quota KMoney di un ordine, con il pagamento ospitato da KMoney.
 *
 * Come il plugin WooCommerce 2.0: la richiesta nasce da server a server
 * con il token del venditore, l'acquirente paga sul sito KMoney con le
 * sue credenziali, e l'esito si chiede all'API. Nessuna credenziale KMoney
 * dell'acquirente passa da qui.
 */
class KMoneyGateway implements PaymentGateway, WebhookAware
{
    private const PAID_EVENT = 'payment_request.paid';

    private const SIGNATURE_HEADER = 'X-KMoney-Signature';

    public function __construct(private readonly CompanyPaymentSetting $settings)
    {
    }

    public function start(Order $order, Payment $payment): string
    {
        $request = $this->client()->createPaymentRequest(
            amountCents: KMoneySplitter::cents($payment->amount),
            description: "Ordine {$order->reference} · quota KMoney",
            reference: self::reference($payment),
            returnUrl: route('checkout.return', $order),
            cancelUrl: route('checkout.cancelled', $order),
        );

        if (blank($request['pay_url'] ?? null) || blank($request['uuid'] ?? null)) {
            throw new PaymentException('KMoney non ha restituito la pagina di pagamento.');
        }

        // Rientro e notifiche ritrovano la richiesta da qui.
        $payment->update(['transaction_id' => $request['uuid']]);

        return $request['pay_url'];
    }

    public function confirm(Order $order, Payment $payment, Request $request): array
    {
        if (blank($payment->transaction_id)) {
            return ['paid' => false, 'reference' => null, 'response' => ['error' => 'richiesta KMoney assente']];
        }

        $data = $this->client()->paymentRequest($payment->transaction_id);

        $expected = KMoneySplitter::cents($payment->amount);
        $referenceMatches = (string) ($data['external_reference'] ?? '') === self::reference($payment);
        // L'importo l'abbiamo fissato noi alla creazione: se la risposta lo riporta, deve essere quello.
        $amountMatches = ! array_key_exists('amount', $data) || (int) $data['amount'] === $expected;

        return [
            'paid' => ($data['status'] ?? null) === 'paid' && $referenceMatches && $amountMatches,
            'reference' => $payment->transaction_id,
            'response' => [
                'uuid' => $data['uuid'] ?? $payment->transaction_id,
                'status' => $data['status'] ?? null,
                'transfer_uuid' => $data['transfer_uuid'] ?? null,
                'amount' => $data['amount'] ?? null,
                'expected_amount' => $expected,
                'reference_matches' => $referenceMatches,
                'amount_matches' => $amountMatches,
            ],
        ];
    }

    /** Firma HMAC SHA-256 del corpo grezzo, con il segreto del webhook del venditore. */
    public function webhookReference(Request $request): ?string
    {
        $secret = $this->settings->kmoney_webhook_secret;

        if (blank($secret)) {
            throw new PaymentException('Segreto delle notifiche KMoney non configurato per questa azienda.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, (string) $request->header(self::SIGNATURE_HEADER, ''))) {
            throw new PaymentException('Firma della notifica KMoney non valida.');
        }

        if ($request->json('event') !== self::PAID_EVENT) {
            return null;
        }

        return $request->json('payload.uuid');
    }

    /** Riferimento esterno della richiesta: un pagamento, una richiesta aperta. */
    public static function reference(Payment $payment): string
    {
        return 'ksm-pagamento-'.$payment->id;
    }

    private function client(): KMoneyClient
    {
        return KMoneyClient::forVendor($this->settings);
    }
}
