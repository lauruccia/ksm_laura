<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Payments\GatewayManager;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CheckoutReturnTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(bool $paid): void
    {
        $gateway = new class($paid) implements PaymentGateway
        {
            public function __construct(private readonly bool $paid)
            {
            }

            public function start(Order $order, Payment $payment): string
            {
                return 'https://gateway.test/redirect';
            }

            public function confirm(Order $order, Payment $payment, Request $request): array
            {
                return [
                    'paid' => $this->paid,
                    'reference' => 'REF-123',
                    'response' => ['status' => $this->paid ? 'COMPLETED' : 'DECLINED'],
                ];
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

    private function makeOrder(): Order
    {
        $buyer = User::create([
            'name' => 'Acquirente', 'email' => 'acquirente@example.test',
            'password' => 'password', 'user_type' => 'buyer',
        ]);

        $vendor = User::create([
            'name' => 'Venditore', 'email' => 'venditore@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        $company = Company::create([
            'user_id' => $vendor->id, 'name' => 'Azienda', 'slug' => 'azienda', 'is_active' => true,
        ]);

        $product = Product::create([
            'company_id' => $company->id, 'name' => 'Prodotto', 'slug' => 'prodotto',
            'price' => 10.00, 'stock' => 5, 'status' => 'active',
        ]);

        $payment = Payment::create([
            'user_id' => $buyer->id, 'company_id' => $company->id, 'method' => 'stripe',
            'mode' => 'test', 'amount' => 20.00, 'currency' => 'EUR', 'status' => 'pending',
        ]);

        $order = Order::create([
            'user_id' => $buyer->id, 'company_id' => $company->id, 'payment_id' => $payment->id,
            'subtotal' => 20.00, 'total' => 20.00, 'currency' => 'EUR', 'status' => 'pending',
            'billing_email' => 'acquirente@example.test',
        ]);

        OrderItem::create([
            'order_id' => $order->id, 'product_id' => $product->id, 'product_name' => 'Prodotto',
            'product_price' => 10.00, 'quantity' => 2, 'subtotal' => 20.00,
        ]);

        $this->actingAs($buyer);

        return $order;
    }

    public function test_il_rientro_confermato_segna_l_ordine_pagato_e_scala_la_giacenza(): void
    {
        $this->fakeGateway(paid: true);
        $order = $this->makeOrder();

        $this->get(route('checkout.return', $order))
            ->assertRedirect(route('checkout.success', $order));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('completed', $order->payment->fresh()->status);
        $this->assertSame('REF-123', $order->payment->fresh()->transaction_id);
        $this->assertSame(3, $order->items->first()->product->fresh()->stock);
    }

    public function test_un_secondo_rientro_non_scala_due_volte(): void
    {
        $this->fakeGateway(paid: true);
        $order = $this->makeOrder();

        $this->get(route('checkout.return', $order));
        $this->get(route('checkout.return', $order))
            ->assertRedirect(route('checkout.success', $order));

        $this->assertSame(3, $order->items->first()->product->fresh()->stock);
    }

    public function test_il_rientro_senza_incasso_lascia_l_ordine_in_attesa(): void
    {
        $this->fakeGateway(paid: false);
        $order = $this->makeOrder();

        $this->get(route('checkout.return', $order))
            ->assertRedirect(route('checkout.cancelled', $order));

        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('failed', $order->payment->fresh()->status);
        $this->assertSame(5, $order->items->first()->product->fresh()->stock);
    }

    public function test_un_altro_utente_non_puo_vedere_il_rientro(): void
    {
        $this->fakeGateway(paid: true);
        $order = $this->makeOrder();

        $intruso = User::create([
            'name' => 'Intruso', 'email' => 'intruso@example.test',
            'password' => 'password', 'user_type' => 'buyer',
        ]);

        $this->actingAs($intruso)
            ->get(route('checkout.return', $order))
            ->assertForbidden();
    }
}
