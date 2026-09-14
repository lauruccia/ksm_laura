<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Payments\KMoneyGateway;
use App\Payments\PaymentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Il driver KMoney contro l'API v1, con le chiamate intercettate.
 *
 * Non serve un conto KMoney: si prova come parliamo all'API e come
 * giudichiamo le risposte, non il servizio.
 */
class KMoneyGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://kmoney.test/api/v1';

    private const SECRET = 'segreto-del-webhook';

    private Company $company;

    private CompanyPaymentSetting $settings;

    private Order $order;

    private Payment $kmoney;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ksm.kmoney.base_url' => self::BASE]);

        $buyer = User::create([
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
            'enable_kmoney' => true,
            'kmoney_api_token' => 'km_test_token',
            'kmoney_webhook_secret' => self::SECRET,
        ]);

        $this->kmoney = Payment::create([
            'user_id' => $buyer->id, 'company_id' => $this->company->id, 'method' => 'kmoney',
            'mode' => 'test', 'amount' => 15.00, 'currency' => 'KY', 'status' => 'pending',
        ]);
        $euro = Payment::create([
            'user_id' => $buyer->id, 'company_id' => $this->company->id, 'method' => 'stripe',
            'mode' => 'test', 'amount' => 5.00, 'currency' => 'EUR', 'status' => 'pending',
        ]);

        $this->order = Order::create([
            'user_id' => $buyer->id, 'company_id' => $this->company->id,
            'payment_id' => $euro->id, 'kmoney_payment_id' => $this->kmoney->id,
            'subtotal' => 20.00, 'total' => 20.00, 'kmoney_total' => 15.00,
            'currency' => 'EUR', 'status' => 'pending',
        ]);
    }

    private function gateway(): KMoneyGateway
    {
        return new KMoneyGateway($this->settings->fresh());
    }

    private function paidRequest(array $overrides = []): array
    {
        return $overrides + [
            'uuid' => 'pr-1',
            'status' => 'paid',
            'external_reference' => 'ksm-pagamento-'.$this->kmoney->id,
            'amount' => 1500,
            'transfer_uuid' => 'tr-9',
        ];
    }

    private function fakeStatus(array $overrides = []): void
    {
        $this->kmoney->update(['transaction_id' => 'pr-1']);

        Http::fake([
            self::BASE.'/payment-requests/pr-1' => Http::response(['data' => $this->paidRequest($overrides)]),
        ]);
    }

    private function signed(string $raw, ?string $signature = null): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KMONEY_SIGNATURE' => $signature ?? 'sha256='.hash_hmac('sha256', $raw, self::SECRET),
        ];
    }

    public function test_apre_la_richiesta_in_centesimi_con_il_token_del_venditore(): void
    {
        Http::fake([
            self::BASE.'/payment-requests' => Http::response(['data' => [
                'uuid' => 'pr-1', 'token' => 'tk', 'pay_url' => 'https://kmoney.test/paga/pr-1',
            ]], 201),
        ]);

        $url = $this->gateway()->start($this->order, $this->kmoney);

        $this->assertSame('https://kmoney.test/paga/pr-1', $url);
        $this->assertSame('pr-1', $this->kmoney->fresh()->transaction_id);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer km_test_token')
            && $request['amount'] === 1500
            && $request['external_reference'] === 'ksm-pagamento-'.$this->kmoney->id
            && $request['return_url'] === route('checkout.return', $this->order));
    }

    public function test_senza_pagina_di_pagamento_segnala_l_errore(): void
    {
        Http::fake([self::BASE.'/payment-requests' => Http::response(['data' => ['uuid' => 'pr-1']], 201)]);

        $this->expectException(PaymentException::class);

        $this->gateway()->start($this->order, $this->kmoney);
    }

    public function test_il_rifiuto_dell_api_diventa_un_errore_di_pagamento(): void
    {
        Http::fake([self::BASE.'/payment-requests' => Http::response(['error' => 'token non valido'], 401)]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('token non valido');

        $this->gateway()->start($this->order, $this->kmoney);
    }

    public function test_senza_token_non_parte_nessuna_chiamata(): void
    {
        Http::fake();
        $this->settings->forceFill(['kmoney_api_token' => null])->save();

        try {
            $this->gateway()->start($this->order, $this->kmoney);
            $this->fail('Serviva un errore di configurazione.');
        } catch (PaymentException) {
            Http::assertNothingSent();
        }
    }

    public function test_conferma_la_richiesta_pagata_con_il_nostro_riferimento_e_importo(): void
    {
        $this->fakeStatus();

        $result = $this->gateway()->confirm($this->order, $this->kmoney->fresh(), Request::create('/'));

        $this->assertTrue($result['paid']);
        $this->assertSame('tr-9', $result['response']['transfer_uuid']);
    }

    public function test_rifiuta_un_importo_diverso(): void
    {
        $this->fakeStatus(['amount' => 1000]);

        $result = $this->gateway()->confirm($this->order, $this->kmoney->fresh(), Request::create('/'));

        $this->assertFalse($result['paid']);
        $this->assertFalse($result['response']['amount_matches']);
    }

    public function test_rifiuta_la_richiesta_di_un_altro_pagamento(): void
    {
        $this->fakeStatus(['external_reference' => 'ksm-pagamento-999']);

        $this->assertFalse($this->gateway()->confirm($this->order, $this->kmoney->fresh(), Request::create('/'))['paid']);
    }

    public function test_rifiuta_una_richiesta_non_pagata(): void
    {
        $this->fakeStatus(['status' => 'pending']);

        $this->assertFalse($this->gateway()->confirm($this->order, $this->kmoney->fresh(), Request::create('/'))['paid']);
    }

    public function test_accetta_la_notifica_firmata(): void
    {
        $raw = json_encode(['event' => 'payment_request.paid', 'payload' => ['uuid' => 'pr-1']]);
        $request = Request::create('/', 'POST', [], [], [], $this->signed($raw), $raw);

        $this->assertSame('pr-1', $this->gateway()->webhookReference($request));
    }

    public function test_rifiuta_una_firma_sbagliata(): void
    {
        $raw = json_encode(['event' => 'payment_request.paid', 'payload' => ['uuid' => 'pr-1']]);
        $request = Request::create('/', 'POST', [], [], [], $this->signed($raw, 'sha256=inventata'), $raw);

        $this->expectException(PaymentException::class);

        $this->gateway()->webhookReference($request);
    }

    public function test_ignora_gli_altri_eventi(): void
    {
        $raw = json_encode(['event' => 'payment_request.expired', 'payload' => ['uuid' => 'pr-1']]);
        $request = Request::create('/', 'POST', [], [], [], $this->signed($raw), $raw);

        $this->assertNull($this->gateway()->webhookReference($request));
    }

    public function test_la_notifica_chiude_la_quota_kmoney_ma_non_l_ordine_senza_la_parte_in_euro(): void
    {
        $this->fakeStatus();
        $raw = json_encode(['event' => 'payment_request.paid', 'payload' => ['uuid' => 'pr-1']]);

        $this->call('POST', route('webhooks.kmoney', $this->company), [], [], [], $this->signed($raw), $raw)
            ->assertOk();

        $this->assertSame('completed', $this->kmoney->fresh()->status);
        $this->assertSame('pending', $this->order->fresh()->status);
    }
}
