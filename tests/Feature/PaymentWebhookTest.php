<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Payments\GatewayManager;
use App\Payments\PaymentException;
use App\Payments\PaymentGateway;
use App\Payments\WebhookAware;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Order $order;

    /**
     * Sostituisce il gestore reale con uno finto.
     *
     * @param  string|null  $reference  cosa restituisce la verifica della firma
     * @param  bool  $validSignature  se false la verifica solleva l'eccezione
     */
    private function fakeGateway(?string $reference, bool $paid = true, bool $validSignature = true): void
    {
        $gateway = new class($reference, $paid, $validSignature) implements PaymentGateway, WebhookAware
        {
            public function __construct(
                private readonly ?string $reference,
                private readonly bool $paid,
                private readonly bool $validSignature,
            ) {
            }

            public function start(Order $order, Payment $payment): string
            {
                return 'https://gateway.test/redirect';
            }

            public function confirm(Order $order, Payment $payment, Request $request): array
            {
                return [
                    'paid' => $this->paid,
                    'reference' => 'CAP-999',
                    'response' => ['status' => $this->paid ? 'COMPLETED' : 'DECLINED'],
                ];
            }

            public function webhookReference(Request $request): ?string
            {
                if (! $this->validSignature) {
                    throw new PaymentException('Firma non valida.');
                }

                return $this->reference;
            }
        };

        $this->app->instance(GatewayManager::class, new class($gateway) extends GatewayManager
        {
            public function __construct(private readonly PaymentGateway $gateway)
            {
            }

            public function driver(string $method, $settings): PaymentGateway
            {
                return $this->gateway;
            }
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $buyer = User::create([
            'name' => 'Acquirente', 'email' => 'acquirente@example.test',
            'password' => 'password', 'user_type' => 'buyer',
        ]);

        $vendor = User::create([
            'name' => 'Venditore', 'email' => 'venditore@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $vendor->id, 'name' => 'Azienda', 'slug' => 'azienda', 'is_active' => true,
        ]);

        CompanyPaymentSetting::create([
            'company_id' => $this->company->id, 'mode' => 'test',
            'enable_stripe' => true, 'stripe_test_secret_key' => 'sk_test_x',
            'stripe_webhook_secret' => 'whsec_x',
        ]);

        $product = Product::create([
            'company_id' => $this->company->id, 'name' => 'Prodotto', 'slug' => 'prodotto',
            'price' => 10.00, 'stock' => 5, 'status' => 'active',
        ]);

        $payment = Payment::create([
            'user_id' => $buyer->id, 'company_id' => $this->company->id, 'method' => 'stripe',
            'mode' => 'test', 'amount' => 20.00, 'currency' => 'EUR', 'status' => 'pending',
            'transaction_id' => 'cs_test_123',
        ]);

        $this->order = Order::create([
            'user_id' => $buyer->id, 'company_id' => $this->company->id, 'payment_id' => $payment->id,
            'subtotal' => 20.00, 'total' => 20.00, 'currency' => 'EUR', 'status' => 'pending',
            'billing_email' => 'acquirente@example.test',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id, 'product_id' => $product->id, 'product_name' => 'Prodotto',
            'product_price' => 10.00, 'quantity' => 2, 'subtotal' => 20.00,
        ]);
    }

    public function test_la_notifica_firmata_registra_l_incasso(): void
    {
        $this->fakeGateway(reference: 'cs_test_123');

        $this->postJson(route('webhooks.stripe', $this->company), ['type' => 'checkout.session.completed'])
            ->assertOk();

        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertSame('completed', $this->order->payment->fresh()->status);
        $this->assertSame(3, $this->order->items->first()->product->fresh()->stock);
    }

    public function test_la_notifica_non_ha_bisogno_del_token_csrf(): void
    {
        $this->fakeGateway(reference: 'cs_test_123');

        // Nessun token in sessione: la rotta non passa dal gruppo web.
        $this->post(route('webhooks.stripe', $this->company), ['type' => 'checkout.session.completed'])
            ->assertOk();
    }

    public function test_la_firma_non_valida_viene_respinta(): void
    {
        $this->fakeGateway(reference: 'cs_test_123', validSignature: false);

        $this->postJson(route('webhooks.stripe', $this->company), ['type' => 'checkout.session.completed'])
            ->assertStatus(400);

        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame(5, $this->order->items->first()->product->fresh()->stock);
    }

    public function test_un_evento_che_non_riguarda_incassi_viene_ignorato(): void
    {
        $this->fakeGateway(reference: null);

        $this->postJson(route('webhooks.stripe', $this->company), ['type' => 'customer.created'])
            ->assertOk();

        $this->assertSame('pending', $this->order->fresh()->status);
    }

    public function test_una_notifica_senza_pagamento_corrispondente_non_cambia_nulla(): void
    {
        $this->fakeGateway(reference: 'cs_test_sconosciuto');

        $this->postJson(route('webhooks.stripe', $this->company), ['type' => 'checkout.session.completed'])
            ->assertOk();

        $this->assertSame('pending', $this->order->fresh()->status);
    }

    public function test_notifica_e_rientro_insieme_non_scalano_due_volte(): void
    {
        $this->fakeGateway(reference: 'cs_test_123');

        $this->postJson(route('webhooks.stripe', $this->company), ['type' => 'checkout.session.completed']);

        $this->actingAs($this->order->user)
            ->get(route('checkout.return', $this->order))
            ->assertRedirect(route('checkout.success', $this->order));

        $this->assertSame(3, $this->order->items->first()->product->fresh()->stock);
    }
}
