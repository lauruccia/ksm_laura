<?php

namespace App\Payments;

use App\Models\CompanyPaymentSetting;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Pagamento con carta tramite Stripe Checkout.
 *
 * La pagina di pagamento e' ospitata da Stripe: qui non transitano
 * mai i dati della carta.
 */
class StripeGateway implements PaymentGateway, WebhookAware
{
    /** Eventi che dichiarano l'incasso avvenuto. */
    private const PAID_EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
    ];

    public function __construct(private readonly CompanyPaymentSetting $settings)
    {
    }

    public function start(Order $order, Payment $payment): string
    {
        try {
            $session = $this->client()->checkout->sessions->create([
                'mode' => 'payment',
                'line_items' => $this->lineItems($order, $payment),
                'client_reference_id' => (string) $order->id,
                'customer_email' => $order->billing_email,
                'success_url' => route('checkout.return', $order).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('checkout.cancelled', $order),
                'metadata' => [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'company_id' => $order->company_id,
                ],
            ]);
        } catch (ApiErrorException $e) {
            throw new PaymentException('Stripe non ha accettato la richiesta: '.$e->getMessage(), 0, $e);
        }

        // Serve al rientro per richiedere l'esito alla sessione giusta.
        $payment->update(['transaction_id' => $session->id]);

        return $session->url;
    }

    public function confirm(Order $order, Payment $payment, Request $request): array
    {
        if (blank($payment->transaction_id)) {
            return ['paid' => false, 'reference' => null, 'response' => ['error' => 'sessione assente']];
        }

        try {
            $session = $this->client()->checkout->sessions->retrieve($payment->transaction_id);
        } catch (ApiErrorException $e) {
            throw new PaymentException('Stripe non ha risposto: '.$e->getMessage(), 0, $e);
        }

        // Non basta che risulti pagato: deve essere pagato l'importo giusto, cioe'
        // quello di questo pagamento, che con una quota KMoney e' solo la parte in euro.
        $expected = $this->cents($payment->amount);
        $matches = (int) $session->amount_total === $expected
            && strtolower((string) $session->currency) === strtolower($payment->currency);

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

    public function webhookReference(Request $request): ?string
    {
        $secret = $this->settings->stripe_webhook_secret;

        if (blank($secret)) {
            throw new PaymentException('Segreto del webhook Stripe non configurato per questa azienda.');
        }

        try {
            // Va firmato il corpo grezzo: il payload decodificato non basta.
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret
            );
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new PaymentException('Firma della notifica Stripe non valida.', 0, $e);
        }

        if (! in_array($event->type, self::PAID_EVENTS, true)) {
            return null;
        }

        return $event->data->object->id ?? null;
    }

    private function client(): StripeClient
    {
        $secret = $this->settings->stripeKeys()['secret'];

        if (blank($secret)) {
            throw new PaymentException('Chiave segreta Stripe non configurata per questa azienda.');
        }

        return new StripeClient($secret);
    }

    /** Righe d'ordine piu' la spedizione, se prevista. */
    private function lineItems(Order $order, Payment $payment): array
    {
        $currency = strtolower($payment->currency);

        // Con una quota KMoney le righe a prezzo pieno non tornano col totale: una riga sola.
        if ($order->hasKmoney()) {
            return [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $this->cents($payment->amount),
                    'product_data' => ['name' => "Ordine {$order->reference} · parte in euro"],
                ],
            ]];
        }

        $items = $order->items->map(fn ($item) => [
            'quantity' => $item->quantity,
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => $this->cents($item->product_price),
                'product_data' => ['name' => $item->product_name],
            ],
        ])->all();

        if ((float) $order->shipping > 0) {
            $items[] = [
                'quantity' => 1,
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => $this->cents($order->shipping),
                    'product_data' => ['name' => 'Spedizione'],
                ],
            ];
        }

        return $items;
    }

    private function cents(float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
