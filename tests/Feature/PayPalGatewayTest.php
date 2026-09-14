<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Payments\PayPalGateway;
use App\Payments\PaymentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Esercita il driver PayPal vero, con le chiamate al gestore intercettate.
 *
 * Le risposte hanno la forma di quelle delle API Orders v2.
 */
class PayPalGatewayTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private Payment $payment;

    private function gateway(?string $webhookId = 'WH-1'): PayPalGateway
    {
        return new PayPalGateway(new CompanyPaymentSetting([
            'mode' => 'test',
            'paypal_test_client_id' => 'client-id',
            'paypal_test_secret' => 'segreto',
            'paypal_webhook_id' => $webhookId,
        ]));
    }

    private function capture(string $value = '20.00', string $currency = 'EUR', string $status = 'COMPLETED'): array
    {
        return [
            'id' => $this->payment->transaction_id,
            'status' => $status,
            'purchase_units' => [[
                'payments' => ['captures' => [[
                    'id' => 'CAP-777',
                    'amount' => ['value' => $value, 'currency_code' => $currency],
                ]]],
            ]],
        ];
    }

    private function fake(array $routes): void
    {
        Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 'tok'])] + $routes);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $buyer = User::create([
            'name' => 'Acquirente', 'email' => 'acquirente@example.test',
            'password' => 'password', 'user_type' => 'buyer',
        ]);

        $company = Company::create([
            'user_id' => $buyer->id, 'name' => 'Azienda', 'slug' => 'azienda', 'is_active' => true,
        ]);

        $product = Product::create([
            'company_id' => $company->id, 'name' => 'Prodotto', 'slug' => 'prodotto',
            'price' => 10.00, 'stock' => 5, 'status' => 'active',
        ]);

        $this->payment = Payment::create([
            'user_id' => $buyer->id, 'company_id' => $company->id, 'method' => 'paypal',
            'mode' => 'test', 'amount' => 20.00, 'currency' => 'EUR', 'status' => 'pending',
            'transaction_id' => 'PAYPAL-ORDER-1',
        ]);

        $this->order = Order::create([
            'user_id' => $buyer->id, 'company_id' => $company->id, 'payment_id' => $this->payment->id,
            'subtotal' => 20.00, 'shipping' => 0, 'total' => 20.00, 'currency' => 'EUR', 'status' => 'pending',
            'billing_email' => 'acquirente@example.test',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id, 'product_id' => $product->id, 'product_name' => 'Prodotto',
            'product_price' => 10.00, 'quantity' => 2, 'subtotal' => 20.00,
        ]);

        $this->order->load('items');
    }

    public function test_apre_l_ordine_e_restituisce_il_collegamento_di_approvazione(): void
    {
        $this->fake(['*/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-ORDER-9',
            'status' => 'PAYER_ACTION_REQUIRED',
            'links' => [
                ['rel' => 'self', 'href' => 'https://api.test/self'],
                ['rel' => 'payer-action', 'href' => 'https://paypal.test/approva'],
            ],
        ])]);

        $url = $this->gateway()->start($this->order, $this->payment);

        $this->assertSame('https://paypal.test/approva', $url);
        $this->assertSame('PAYPAL-ORDER-9', $this->payment->fresh()->transaction_id);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v2/checkout/orders')
                && $request['purchase_units'][0]['amount']['value'] === '20.00'
                && $request['purchase_units'][0]['amount']['currency_code'] === 'EUR';
        });
    }

    public function test_usa_la_sandbox_quando_la_modalita_e_test(): void
    {
        $this->fake(['*/v2/checkout/orders' => Http::response([
            'id' => 'PAYPAL-ORDER-9',
            'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approva']],
        ])]);

        $this->gateway()->start($this->order, $this->payment);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api-m.sandbox.paypal.com'));
    }

    public function test_segnala_il_rifiuto_del_gestore(): void
    {
        $this->fake(['*/v2/checkout/orders' => Http::response(['name' => 'INVALID_REQUEST'], 400)]);

        $this->expectException(PaymentException::class);

        $this->gateway()->start($this->order, $this->payment);
    }

    public function test_segnala_la_risposta_senza_collegamento_di_approvazione(): void
    {
        $this->fake(['*/v2/checkout/orders' => Http::response(['id' => 'X', 'links' => []])]);

        $this->expectException(PaymentException::class);

        $this->gateway()->start($this->order, $this->payment);
    }

    public function test_conferma_quando_la_cattura_corrisponde_all_ordine(): void
    {
        $this->fake(['*/v2/checkout/orders/*/capture' => Http::response($this->capture())]);

        $result = $this->gateway()->confirm($this->order, $this->payment, Request::create('/'));

        $this->assertTrue($result['paid']);
        $this->assertSame('CAP-777', $result['reference']);
    }

    public function test_rifiuta_una_cattura_di_importo_diverso(): void
    {
        $this->fake(['*/v2/checkout/orders/*/capture' => Http::response($this->capture(value: '2.00'))]);

        $result = $this->gateway()->confirm($this->order, $this->payment, Request::create('/'));

        $this->assertFalse($result['paid']);
        $this->assertFalse($result['response']['amount_matches']);
    }

    public function test_rifiuta_una_cattura_in_un_altra_valuta(): void
    {
        $this->fake(['*/v2/checkout/orders/*/capture' => Http::response($this->capture(currency: 'USD'))]);

        $this->assertFalse($this->gateway()->confirm($this->order, $this->payment, Request::create('/'))['paid']);
    }

    public function test_una_cattura_gia_avvenuta_viene_riletta_e_non_ripetuta(): void
    {
        $this->fake([
            '*/v2/checkout/orders/*/capture' => Http::response([
                'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']],
            ], 422),
            '*/v2/checkout/orders/*' => Http::response($this->capture()),
        ]);

        $result = $this->gateway()->confirm($this->order, $this->payment, Request::create('/'));

        $this->assertTrue($result['paid']);
        $this->assertSame('CAP-777', $result['reference']);
    }

    private function notification(array $event, string $verification = 'SUCCESS'): Request
    {
        $this->fake([
            '*/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => $verification,
            ]),
        ]);

        return Request::create(
            '/webhook/paypal/1', 'POST', [], [], [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_PAYPAL_AUTH_ALGO' => 'SHA256withRSA',
                'HTTP_PAYPAL_CERT_URL' => 'https://api.paypal.test/cert.pem',
                'HTTP_PAYPAL_TRANSMISSION_ID' => 'trasmissione-1',
                'HTTP_PAYPAL_TRANSMISSION_SIG' => 'firma',
                'HTTP_PAYPAL_TRANSMISSION_TIME' => '2026-01-01T00:00:00Z',
            ],
            json_encode($event)
        );
    }

    public function test_accetta_l_approvazione_dell_ordine(): void
    {
        $request = $this->notification([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ]);

        $this->assertSame('PAYPAL-ORDER-1', $this->gateway()->webhookReference($request));
    }

    public function test_accetta_la_cattura_completata(): void
    {
        $request = $this->notification([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAP-777',
                'supplementary_data' => ['related_ids' => ['order_id' => 'PAYPAL-ORDER-1']],
            ],
        ]);

        $this->assertSame('PAYPAL-ORDER-1', $this->gateway()->webhookReference($request));
    }

    public function test_ignora_gli_eventi_che_non_riguardano_incassi(): void
    {
        $request = $this->notification([
            'event_type' => 'BILLING.SUBSCRIPTION.CREATED',
            'resource' => ['id' => 'SUB-1'],
        ]);

        $this->assertNull($this->gateway()->webhookReference($request));
    }

    public function test_rifiuta_una_notifica_che_il_gestore_non_riconosce(): void
    {
        $request = $this->notification([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ], verification: 'FAILURE');

        $this->expectException(PaymentException::class);

        $this->gateway()->webhookReference($request);
    }

    public function test_manda_al_gestore_le_intestazioni_firmate(): void
    {
        $request = $this->notification([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ]);

        $this->gateway()->webhookReference($request);

        Http::assertSent(function ($sent) {
            return str_contains($sent->url(), 'verify-webhook-signature')
                && $sent['transmission_id'] === 'trasmissione-1'
                && $sent['transmission_sig'] === 'firma'
                && $sent['webhook_id'] === 'WH-1';
        });
    }

    public function test_rifiuta_se_il_webhook_non_e_configurato(): void
    {
        $request = $this->notification([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => ['id' => 'PAYPAL-ORDER-1'],
        ]);

        $this->expectException(PaymentException::class);

        $this->gateway(webhookId: null)->webhookReference($request);
    }
}
