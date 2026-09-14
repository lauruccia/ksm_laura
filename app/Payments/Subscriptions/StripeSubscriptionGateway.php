<?php

namespace App\Payments\Subscriptions;

use App\Models\AdminPaymentSetting;
use App\Models\AdminTransaction;
use App\Models\CompanySubscription;
use App\Payments\PaymentException;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/** Quota del piano pagata con carta, tramite Stripe Checkout. */
class StripeSubscriptionGateway implements SubscriptionGateway
{
    public function __construct(private readonly AdminPaymentSetting $settings)
    {
    }

    public function start(CompanySubscription $subscription, AdminTransaction $transaction): string
    {
        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($subscription->currency),
                        'unit_amount' => $this->cents($subscription->price),
                        'product_data' => ['name' => $subscription->plan->name],
                    ],
                ]],
                'client_reference_id' => (string) $subscription->id,
                'customer_email' => $subscription->company->email,
                'success_url' => route('subscription.return', $subscription).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription.cancelled', $subscription),
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'company_id' => $subscription->company_id,
                    'plan_id' => $subscription->plan_id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException('Stripe non ha accettato la richiesta: '.$e->getMessage(), 0, $e);
        }

        $transaction->update(['transaction_id' => $session->id]);

        return $session->url;
    }

    public function confirm(CompanySubscription $subscription, AdminTransaction $transaction, Request $request): array
    {
        if (blank($transaction->transaction_id)) {
            return ['paid' => false, 'reference' => null, 'response' => ['error' => 'sessione assente']];
        }

        try {
            $session = $this->client()->checkout->sessions->retrieve($transaction->transaction_id);
        } catch (ApiErrorException $e) {
            throw new PaymentException('Stripe non ha risposto: '.$e->getMessage(), 0, $e);
        }

        // Non basta che risulti pagato: deve essere pagata la quota giusta.
        $expected = $this->cents($subscription->price);
        $matches = (int) $session->amount_total === $expected
            && strtolower((string) $session->currency) === strtolower($subscription->currency);

        return [
            'paid' => $session->payment_status === 'paid' && $matches,
            'reference' => $session->payment_intent ?? $session->id,
            'response' => [
                'session' => $session->id,
                'payment_status' => $session->payment_status,
                'amount_total' => $session->amount_total,
                'currency' => $session->currency,
                'amount_matches' => $matches,
                'expected_total' => $expected,
            ],
        ];
    }

    private function client(): StripeClient
    {
        $secret = $this->settings->stripeKeys()['secret'];

        if (blank($secret)) {
            throw new PaymentException('Chiave segreta Stripe non configurata per la piattaforma.');
        }

        return new StripeClient($secret);
    }

    private function cents(float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
