<?php

namespace Tests\Feature;

use App\Models\AdminPaymentSetting;
use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Payments\GatewayManager;
use App\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * La cassa con la quota KMoney: prima i KY, subito dopo gli euro.
 *
 * I gestori sono finti e rispondono "pagato" o no a comando; si prova il
 * percorso fra le due parti, non i gestori.
 */
class KMoneyCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Company $company;

    private CompanyPaymentSetting $settings;

    private GatewayManager $gateways;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::create([
            'name' => 'Acquirente', 'email' => 'acquirente@example.test',
            'password' => 'password', 'user_type' => 'buyer',
        ]);
        $vendor = User::create([
            'name' => 'Venditore', 'email' => 'venditore@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $vendor->id, 'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true,
        ]);

        $this->settings = CompanyPaymentSetting::create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'enable_stripe' => true,
            'stripe_test_secret_key' => 'sk_test_x',
            'enable_kmoney' => true,
            'kmoney_api_token' => 'km_test_token',
        ]);

        $this->fakeGateways();
    }

    /** Ogni metodo apre https://{metodo}.test/paga e conferma secondo $paid. */
    private function fakeGateways(): void
    {
        $this->gateways = new class(['kmoney' => true, 'stripe' => true]) extends GatewayManager
        {
            public function __construct(public array $paid)
            {
            }

            public function driver(string $method, $settings): PaymentGateway
            {
                return new class($method, $this->paid[$method] ?? false) implements PaymentGateway
                {
                    public function __construct(private readonly string $method, private readonly bool $paid)
                    {
                    }

                    public function start(Order $order, Payment $payment): string
                    {
                        $payment->update(['transaction_id' => $this->method.'-'.$payment->id]);

                        return "https://{$this->method}.test/paga";
                    }

                    public function confirm(Order $order, Payment $payment, Request $request): array
                    {
                        return ['paid' => $this->paid, 'reference' => $payment->transaction_id, 'response' => []];
                    }
                };
            }
        };

        $this->app->instance(GatewayManager::class, $this->gateways);
    }

    private function inCart(float $price, ?int $kmoneyPercent): Product
    {
        $product = Product::create([
            'company_id' => $this->company->id, 'name' => 'Nduja', 'slug' => 'nduja-'.uniqid(),
            'price' => $price, 'stock' => 5, 'status' => 'active', 'kmoney_discount_percent' => $kmoneyPercent,
        ]);

        $this->actingAs($this->buyer)->withSession(['cart' => [
            (string) $product->id => [
                'product_id' => $product->id, 'company_id' => $this->company->id, 'name' => $product->name,
                'slug' => $product->slug, 'image' => null, 'price' => $price, 'quantity' => 1,
            ],
        ]]);

        return $product;
    }

    private function checkout(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('checkout.process'), $overrides + [
            'billing_name' => 'Mario Rossi',
            'billing_email' => 'mario@example.test',
            'billing_address' => 'Via Roma 1',
            'billing_city' => 'Roma',
            'billing_zip' => '00100',
            'billing_country' => 'Italia',
            'method' => 'stripe',
            'pagamento_kmoney' => 'conto',
        ]);
    }

    public function test_il_pagamento_non_scala_lo_stock_non_gestito(): void
    {
        $product = $this->inCart(20, 0);
        $product->update(['stock' => null]);
        $this->checkout()->assertRedirect('https://stripe.test/paga');
        $order = Order::firstOrFail();
        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.success', $order));
        $this->assertNull($product->fresh()->stock);
        $this->assertTrue($product->fresh()->isInStock());
    }

    public function test_ordine_con_due_varianti_conserva_prezzi_quote_e_scala_solo_la_variante_gestita(): void
    {
        $product = $this->inCart(20, 50);
        $product->update(['product_type' => 'variable', 'stock' => 0]);
        $small = $product->variants()->create(['variant_type' => 'Formato', 'variant_value' => '250 g', 'variant_price' => 5, 'variant_stock' => 3]);
        $large = $product->variants()->create(['variant_type' => 'Formato', 'variant_value' => '500 g', 'variant_price' => 9, 'variant_stock' => null]);
        $this->withSession(['cart' => collect([$small, $large])->mapWithKeys(fn ($variant) => [$product->id.':'.$variant->id => [
            'product_id' => $product->id, 'variant_id' => $variant->id, 'company_id' => $this->company->id,
            'name' => $product->name, 'slug' => $product->slug, 'price' => 1, 'quantity' => 2,
        ]])->all()]);
        $this->checkout()->assertRedirect('https://kmoney.test/paga');
        $order = Order::firstOrFail();
        $this->assertEquals(28, $order->subtotal);
        $this->assertEquals(14, $order->kmoney_total);
        $this->assertEquals(14, $order->items->sum('kmoney_amount'));
        $this->assertEquals(5, $order->items->firstWhere('product_variant_id', $small->id)->product_price);
        $this->assertStringContainsString('250 g', $order->items->firstWhere('product_variant_id', $small->id)->product_name);
        $this->get(route('checkout.return', $order))->assertRedirect('https://stripe.test/paga');
        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.success', $order));
        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.success', $order));
        $this->assertSame(1, (int) $small->fresh()->variant_stock);
        $this->assertNull($large->fresh()->variant_stock);
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_checkout_rifiuta_giacenza_diventata_insufficiente(): void
    {
        $product = $this->inCart(20, 0);
        $product->update(['stock' => 0]);
        $this->checkout()->assertSessionHasErrors('quantita');
        $this->assertSame(0, Order::count());
    }

    public function test_si_paga_prima_la_quota_kmoney_poi_la_parte_in_euro(): void
    {
        $product = $this->inCart(20, 50);

        $this->checkout()->assertRedirect('https://kmoney.test/paga');

        $order = Order::firstOrFail();
        $this->assertEquals(10, $order->kmoney_total);
        $this->assertEquals(10, $order->payment->amount);
        $this->assertEquals(10, $order->kmoneyPayment->amount);
        $this->assertSame(50, $order->items->first()->kmoney_percent);

        // Rientro da KMoney: la quota e' pagata, si apre subito la parte in euro.
        $this->get(route('checkout.return', $order))->assertRedirect('https://stripe.test/paga');
        $this->assertSame('completed', $order->kmoneyPayment->fresh()->status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock);

        // Rientro dal gestore in euro: ordine pagato, giacenza scalata una volta.
        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.success', $order));
        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.success', $order));

        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_al_cento_per_cento_non_serve_un_metodo_in_euro(): void
    {
        $this->inCart(20, 100);

        $this->checkout(['method' => null])->assertRedirect('https://kmoney.test/paga');

        $order = Order::firstOrFail();
        $this->assertNull($order->payment_id);

        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.success', $order));
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_senza_conto_kmoney_si_paga_tutto_in_euro_se_l_amministrazione_lo_consente(): void
    {
        AdminPaymentSetting::current()->forceFill(['kmoney_euro_fallback' => true])->save();
        $this->inCart(20, 50);

        $this->checkout(['pagamento_kmoney' => 'euro'])->assertRedirect('https://stripe.test/paga');

        $order = Order::firstOrFail();
        $this->assertEquals(0, $order->kmoney_total);
        $this->assertNull($order->kmoney_payment_id);
        $this->assertEquals(20, $order->payment->amount);
    }

    public function test_tutto_in_euro_non_si_puo_senza_il_consenso(): void
    {
        $this->inCart(20, 50);

        $this->checkout(['pagamento_kmoney' => 'euro'])->assertSessionHasErrors('pagamento_kmoney');

        $this->assertSame(0, Order::count());
    }

    public function test_dal_venditore_in_debito_non_si_paga_in_euro(): void
    {
        AdminPaymentSetting::current()->forceFill(['kmoney_euro_fallback' => true])->save();
        $this->settings->forceFill(['kmoney_in_debt' => true])->save();
        $this->inCart(20, 25);

        $this->checkout(['pagamento_kmoney' => 'euro'])->assertSessionHasErrors('pagamento_kmoney');

        $this->assertSame(0, Order::count());
    }

    public function test_senza_kmoney_collegato_non_si_compra_un_prodotto_con_quota(): void
    {
        $this->settings->update(['enable_kmoney' => false]);
        $this->inCart(20, 50);

        $this->checkout()->assertSessionHas('error');

        $this->assertSame(0, Order::count());
    }

    public function test_se_la_parte_in_euro_non_riesce_si_riprova_senza_ripagare_i_ky(): void
    {
        $product = $this->inCart(20, 50);
        $this->gateways->paid['stripe'] = false;

        $this->checkout();
        $order = Order::firstOrFail();

        $this->get(route('checkout.return', $order))->assertRedirect('https://stripe.test/paga');
        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.cancelled', $order));
        $this->assertSame('failed', $order->payment->fresh()->status);

        $this->get(route('checkout.cancelled', $order))
            ->assertOk()
            ->assertSee('già pagata')
            ->assertSee('Riprova il pagamento');

        $this->post(route('checkout.retry', $order), ['method' => 'stripe'])
            ->assertRedirect('https://stripe.test/paga');
        $this->assertSame('pending', $order->payment->fresh()->status);
        $this->assertSame('completed', $order->kmoneyPayment->fresh()->status);

        $this->gateways->paid['stripe'] = true;

        $this->get(route('checkout.return', $order))->assertRedirect(route('checkout.success', $order));
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock);
    }

    public function test_il_riepilogo_mostra_la_quota_in_kmoney(): void
    {
        $this->inCart(20, 50);

        $this->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('10,00 KY')
            ->assertSee('50% in KMoney');
    }
    public function test_la_cassa_salva_l_indirizzo_solo_se_richiesto(): void
    {
        $this->inCart(20, 0);
        $this->checkout(['billing_state' => 'RM'])->assertRedirect('https://stripe.test/paga');
        $this->assertNull($this->buyer->fresh()->billing_address);

        $this->inCart(20, 0);
        $this->checkout(['billing_state' => 'RM', 'salva_dati' => '1'])->assertRedirect('https://stripe.test/paga');

        $buyer = $this->buyer->fresh();
        $this->assertSame('Via Roma 1', $buyer->billing_address);
        $this->assertSame('RM', $buyer->billing_state);
        $this->assertSame('RM', Order::latest('id')->first()->billing_state);

        // Il prossimo ordine parte gia' compilato.
        $this->inCart(20, 0);
        $this->get(route('checkout.show'))->assertOk()->assertSee('value="Via Roma 1"', false);
    }
}
