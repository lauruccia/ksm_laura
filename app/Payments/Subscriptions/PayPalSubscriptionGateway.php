<?php

namespace App\Payments\Subscriptions;

use App\Models\AdminPaymentSetting;
use App\Models\AdminTransaction;
use App\Models\CompanySubscription;
use App\Payments\PaymentException;
use App\Payments\PayPalHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Quota del piano pagata con PayPal, API Orders versione 2. */
class PayPalSubscriptionGateway implements SubscriptionGateway
{
    public function __construct(private readonly AdminPaymentSetting $settings)
    {
    }

    public function start(CompanySubscription $subscription, AdminTransaction $transaction): string
    {
        $currency = strtoupper($subscription->currency);

        $response = $this->client()->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'sub-'.$subscription->id,
                'custom_id' => (string) $subscription->id,
                'description' => Str::limit($subscription->plan->name, 120, ''),
                'amount' => ['currency_code' => $currency, 'value' => $this->amount($subscription->price)],
            ]],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'user_action' => 'PAY_NOW',
                        'return_url' => route('subscription.return', $subscription),
                        'cancel_url' => route('subscription.cancelled', $subscription),
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            throw new PaymentException('PayPal non ha accettato la richiesta: '.$response->status());
        }

        $transaction->update(['transaction_id' => $response->json('id')]);

        return PayPalHttp::approvalLink($response->json('links', []));
    }

    public function confirm(CompanySubscription $subscription, AdminTransaction $transaction, Request $request): array
    {
        if (blank($transaction->transaction_id)) {
            return ['paid' => false, 'reference' => null, 'response' => ['error' => 'ordine PayPal assente']];
        }

        $response = $this->client()
            // Evita la doppia cattura se la pagina di rientro viene ricaricata.
            ->withHeaders(['PayPal-Request-Id' => 'subscription-'.$subscription->id])
            ->post("/v2/checkout/orders/{$transaction->transaction_id}/capture");

        if ($response->status() === 422 && $this->alreadyCaptured($response->json())) {
            $response = $this->client()->get("/v2/checkout/orders/{$transaction->transaction_id}");
        } elseif ($response->failed()) {
            throw new PaymentException('PayPal non ha risposto alla cattura: '.$response->status());
        }

        return $this->outcome($subscription, $transaction, $response->json() ?? []);
    }

    /**
     * Esito della cattura.
     *
     * Non basta lo stato COMPLETED: l'importo incassato deve essere la
     * quota dovuta, nella stessa valuta.
     */
    private function outcome(CompanySubscription $subscription, AdminTransaction $transaction, array $body): array
    {
        $capture = data_get($body, 'purchase_units.0.payments.captures.0', []);

        $matches = $this->amount(data_get($capture, 'amount.value')) === $this->amount($subscription->price)
            && strtoupper((string) data_get($capture, 'amount.currency_code')) === strtoupper($subscription->currency);

        return [
            'paid' => data_get($body, 'status') === 'COMPLETED' && $matches,
            'reference' => data_get($capture, 'id') ?? $transaction->transaction_id,
            'response' => [
                'status' => data_get($body, 'status'),
                'order' => $transaction->transaction_id,
                'captured' => data_get($capture, 'amount.value'),
                'currency' => data_get($capture, 'amount.currency_code'),
                'amount_matches' => $matches,
                'expected_total' => $this->amount($subscription->price),
            ],
        ];
    }

    private function alreadyCaptured(?array $body): bool
    {
        return collect($body['details'] ?? [])
            ->contains(fn ($detail) => ($detail['issue'] ?? '') === 'ORDER_ALREADY_CAPTURED');
    }

    private function client(): PendingRequest
    {
        return PayPalHttp::client($this->settings->paypalKeys(), $this->settings->isLive(), 'la piattaforma');
    }

    private function amount(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
