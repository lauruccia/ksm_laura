<?php

namespace App\Payments;

use App\Models\CompanyPaymentSetting;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Pagamento con PayPal, API Orders versione 2.
 *
 * Non usa il vecchio SDK REST, che PayPal ha dismesso: le chiamate
 * passano dal client HTTP del framework.
 */
class PayPalGateway implements PaymentGateway, WebhookAware
{
    /** Intestazioni che PayPal firma e che vanno rimandate indietro per la verifica. */
    private const SIGNATURE_HEADERS = [
        'auth_algo' => 'PAYPAL-AUTH-ALGO',
        'cert_url' => 'PAYPAL-CERT-URL',
        'transmission_id' => 'PAYPAL-TRANSMISSION-ID',
        'transmission_sig' => 'PAYPAL-TRANSMISSION-SIG',
        'transmission_time' => 'PAYPAL-TRANSMISSION-TIME',
    ];

    public function __construct(private readonly CompanyPaymentSetting $settings)
    {
    }

    public function start(Order $order, Payment $payment): string
    {
        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [$this->purchaseUnit($order, $payment)],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'user_action' => 'PAY_NOW',
                        'return_url' => route('checkout.return', $order),
                        'cancel_url' => route('checkout.cancelled', $order),
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new PaymentException('PayPal non ha accettato la richiesta: '.$response->status());
        }

        $payment->update(['transaction_id' => $response->json('id')]);

        return PayPalHttp::approvalLink($response->json('links', []));
    }

    public function confirm(Order $order, Payment $payment, Request $request): array
    {
        if (blank($payment->transaction_id)) {
            return ['paid' => false, 'reference' => null, 'response' => ['error' => 'ordine PayPal assente']];
        }

        $response = $this->client()
            // Evita la doppia cattura se l'acquirente ricarica la pagina di rientro.
            ->withHeaders(['PayPal-Request-Id' => 'order-'.$order->id])
            ->post("/v2/checkout/orders/{$payment->transaction_id}/capture");

        if ($response->status() === 422 && $this->alreadyCaptured($response->json())) {
            return $this->readState($order, $payment);
        }

        if ($response->failed()) {
            throw new PaymentException('PayPal non ha risposto alla cattura: '.$response->status());
        }

        return $this->outcome($order, $payment, $response->json() ?? []);
    }

    /** Rilegge lo stato quando la cattura risulta gia' avvenuta. */
    private function readState(Order $order, Payment $payment): array
    {
        $response = $this->client()->get("/v2/checkout/orders/{$payment->transaction_id}");

        return $this->outcome($order, $payment, $response->json() ?? []);
    }

    /**
     * Esito della cattura.
     *
     * Non basta lo stato COMPLETED: l'importo incassato deve essere
     * quello dovuto, nella stessa valuta.
     */
    private function outcome(Order $order, Payment $payment, array $body): array
    {
        $capture = data_get($body, 'purchase_units.0.payments.captures.0', []);

        // L'importo di questo pagamento: con una quota KMoney e' solo la parte in euro.
        $matches = $this->amount(data_get($capture, 'amount.value')) === $this->amount($payment->amount)
            && strtoupper((string) data_get($capture, 'amount.currency_code')) === strtoupper($payment->currency);

        return [
            'paid' => data_get($body, 'status') === 'COMPLETED' && $matches,
            'reference' => data_get($capture, 'id') ?? $payment->transaction_id,
            'response' => [
                'status' => data_get($body, 'status'),
                'order' => $payment->transaction_id,
                'captured' => data_get($capture, 'amount.value'),
                'currency' => data_get($capture, 'amount.currency_code'),
                'amount_matches' => $matches,
                'expected_total' => $this->amount($payment->amount),
            ],
        ];
    }

    public function webhookReference(Request $request): ?string
    {
        $webhookId = $this->settings->paypal_webhook_id;

        if (blank($webhookId)) {
            throw new PaymentException('Identificativo del webhook PayPal non configurato per questa azienda.');
        }

        $headers = collect(self::SIGNATURE_HEADERS)
            ->map(fn ($header) => $request->header($header, ''))
            ->all();

        $verification = $this->client()->post('/v1/notifications/verify-webhook-signature', $headers + [
            'webhook_id' => $webhookId,
            'webhook_event' => $request->json()->all(),
        ]);

        if ($verification->json('verification_status') !== 'SUCCESS') {
            throw new PaymentException('Firma della notifica PayPal non valida.');
        }

        // In entrambi i casi serve l'identificativo dell'ordine PayPal,
        // che e' quello salvato sul pagamento.
        return match ($request->json('event_type')) {
            'CHECKOUT.ORDER.APPROVED' => $request->json('resource.id'),
            'PAYMENT.CAPTURE.COMPLETED' => $request->json('resource.supplementary_data.related_ids.order_id'),
            default => null,
        };
    }

    private function alreadyCaptured(?array $body): bool
    {
        return collect($body['details'] ?? [])
            ->contains(fn ($detail) => ($detail['issue'] ?? '') === 'ORDER_ALREADY_CAPTURED');
    }

    private function purchaseUnit(Order $order, Payment $payment): array
    {
        $currency = strtoupper($payment->currency);

        // Con una quota KMoney righe e spedizione a prezzo pieno non tornano col totale.
        if ($order->hasKmoney()) {
            return [
                'reference_id' => (string) $order->id,
                'custom_id' => (string) $order->id,
                'description' => "Ordine {$order->reference} · parte in euro",
                'amount' => ['currency_code' => $currency, 'value' => $this->amount($payment->amount)],
            ];
        }

        return [
            'reference_id' => (string) $order->id,
            'custom_id' => (string) $order->id,
            'amount' => [
                'currency_code' => $currency,
                'value' => $this->amount($order->total),
                'breakdown' => [
                    'item_total' => ['currency_code' => $currency, 'value' => $this->amount($order->subtotal)],
                    'shipping' => ['currency_code' => $currency, 'value' => $this->amount($order->shipping)],
                ],
            ],
            'items' => $order->items->map(fn ($item) => [
                'name' => Str::limit($item->product_name, 120, ''),
                'quantity' => (string) $item->quantity,
                'unit_amount' => ['currency_code' => $currency, 'value' => $this->amount($item->product_price)],
            ])->all(),
        ];
    }

    private function client(): PendingRequest
    {
        return PayPalHttp::client($this->settings->paypalKeys(), $this->settings->isLive(), 'questa azienda');
    }

    private function amount(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
