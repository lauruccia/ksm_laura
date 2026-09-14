<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Tests\TestCase;

/**
 * Esercita il driver Stripe vero, con le chiamate al gestore intercettate.
 *
 * Serve a provare che la conferma non si accontenta dello stato "pagato":
 * controlla anche importo e valuta.
 */
class StripeConfirmTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private Payment $payment;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(CurlClient::instance());

        parent::tearDown();
    }

    /** Risponde alla chiamata di Stripe con una sessione costruita a mano. */
    private function fakeSession(array $session): void
    {
        ApiRequestor::setHttpClient(new class($session) implements ClientInterface
        {
            public function __construct(private readonly array $session)
            {
            }

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
            {
                return [json_encode($this->session), 200, []];
            }
        });
    }

    private function sessionPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_999',
            'amount_total' => 2000,
            'currency' => 'eur',
        ], $overrides);
    }

    private function gateway(): StripeGateway
    {
        return new StripeGateway(new CompanyPaymentSetting([
            'mode' => 'test',
            'stripe_test_secret_key' => 'sk_test_x',
        ]));
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

        $this->payment = Payment::create([
            'user_id' => $buyer->id, 'company_id' => $company->id, 'method' => 'stripe',
            'mode' => 'test', 'amount' => 20.00, 'currency' => 'EUR', 'status' => 'pending',
            'transaction_id' => 'cs_test_123',
        ]);

        $this->order = Order::create([
            'user_id' => $buyer->id, 'company_id' => $company->id, 'payment_id' => $this->payment->id,
            'subtotal' => 20.00, 'total' => 20.00, 'currency' => 'EUR', 'status' => 'pending',
        ]);
    }

    public function test_conferma_quando_importo_e_valuta_corrispondono(): void
    {
        $this->fakeSession($this->sessionPayload());

        $result = $this->gateway()->confirm($this->order, $this->payment, Request::create('/'));

        $this->assertTrue($result['paid']);
        $this->assertSame('pi_test_999', $result['reference']);
    }

    public function test_rifiuta_un_incasso_di_importo_diverso(): void
    {
        $this->fakeSession($this->sessionPayload(['amount_total' => 1000]));

        $result = $this->gateway()->confirm($this->order, $this->payment, Request::create('/'));

        $this->assertFalse($result['paid']);
        $this->assertFalse($result['response']['amount_matches']);
    }

    public function test_rifiuta_un_incasso_in_un_altra_valuta(): void
    {
        $this->fakeSession($this->sessionPayload(['currency' => 'usd']));

        $this->assertFalse($this->gateway()->confirm($this->order, $this->payment, Request::create('/'))['paid']);
    }

    public function test_rifiuta_una_sessione_non_pagata(): void
    {
        $this->fakeSession($this->sessionPayload(['payment_status' => 'unpaid']));

        $this->assertFalse($this->gateway()->confirm($this->order, $this->payment, Request::create('/'))['paid']);
    }

    public function test_senza_sessione_salvata_non_conferma_nulla(): void
    {
        $this->payment->update(['transaction_id' => null]);

        $result = $this->gateway()->confirm($this->order, $this->payment->fresh(), Request::create('/'));

        $this->assertFalse($result['paid']);
    }
}
