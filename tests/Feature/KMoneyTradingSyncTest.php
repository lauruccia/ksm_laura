<?php

namespace Tests\Feature;

use App\Jobs\SyncKMoneyTradingStatus;
use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\Product;
use App\Models\User;
use App\Payments\KMoney\KMoneyTradingSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Debito e capacita' di vendita letti da GET /balance, con le chiamate intercettate.
 */
class KMoneyTradingSyncTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://kmoney.test/api/v1';

    private const SECRET = 'segreto-del-webhook';

    private Company $company;

    private CompanyPaymentSetting $settings;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ksm.kmoney.base_url' => self::BASE]);

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
        $this->settings->forceFill(['kmoney_contract_percent' => 25])->save();
    }

    private function product(int $percent): Product
    {
        return Product::create([
            'company_id' => $this->company->id, 'name' => 'Prodotto', 'slug' => 'prodotto-'.uniqid(),
            'price' => 10, 'stock' => 5, 'status' => 'active', 'kmoney_percent' => $percent,
        ]);
    }

    private function fakeBalance(bool $inDebit, bool $canSell = true): void
    {
        Http::fake([self::BASE.'/balance' => Http::response([
            'account_number' => 'KYB1A2B3C4D5E6F7', 'currency' => 'KY',
            'balance' => $inDebit ? -5000 : 5000, 'credit_limit' => 10000,
            'is_in_debit' => $inDebit, 'is_at_ceiling' => false,
            'can_sell' => $canSell, 'allowed_ky_percentages' => $inDebit ? [100] : [25, 50, 100],
        ])]);
    }

    public function test_il_debito_letto_da_kmoney_porta_i_prodotti_al_100(): void
    {
        $product = $this->product(25);
        $this->fakeBalance(inDebit: true);

        $this->assertTrue(app(KMoneyTradingSync::class)->sync($this->company));

        $settings = $this->settings->fresh();
        $this->assertTrue($settings->kmoney_in_debt);
        $this->assertSame([100], $settings->kmoney_allowed_percentages);
        $this->assertNotNull($settings->kmoney_synced_at);
        $this->assertSame(100, $product->fresh()->kmoney_percent);

        Http::assertSent(fn ($request) => $request->url() === self::BASE.'/balance'
            && $request->hasHeader('Authorization', 'Bearer km_test_token'));
    }

    public function test_tornato_in_positivo_riprende_la_quota_del_contratto(): void
    {
        $this->settings->forceFill(['kmoney_in_debt' => true])->save();
        $product = $this->product(100);
        $this->fakeBalance(inDebit: false, canSell: false);

        app(KMoneyTradingSync::class)->sync($this->company);

        $this->assertFalse($this->settings->fresh()->kmoney_in_debt);
        $this->assertFalse($this->settings->fresh()->kmoney_can_sell);
        $this->assertSame(25, $product->fresh()->kmoney_percent);
    }

    public function test_senza_token_non_chiama_kmoney(): void
    {
        Http::fake();
        $this->settings->forceFill(['kmoney_api_token' => null])->save();

        $this->assertFalse(app(KMoneyTradingSync::class)->sync($this->company));
        Http::assertNothingSent();
    }

    public function test_la_notifica_firmata_fa_rileggere_lo_stato(): void
    {
        Queue::fake();
        $raw = json_encode(['event' => 'company.trading_status_changed', 'payload' => ['can_sell' => false]]);

        $this->call('POST', route('webhooks.kmoney', $this->company), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KMONEY_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, self::SECRET),
        ], $raw)->assertOk();

        Queue::assertPushed(SyncKMoneyTradingStatus::class, fn ($job) => $job->companyId === $this->company->id);
    }

    public function test_la_notifica_con_firma_sbagliata_non_fa_niente(): void
    {
        Queue::fake();
        $raw = json_encode(['event' => 'company.trading_status_changed']);

        $this->call('POST', route('webhooks.kmoney', $this->company), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_KMONEY_SIGNATURE' => 'sha256=inventata',
        ], $raw)->assertStatus(400);

        Queue::assertNothingPushed();
    }

    public function test_una_quota_non_piu_ammessa_sale_alla_prima_ammessa(): void
    {
        $product = $this->product(0);
        $product->forceFill(['kmoney_discount_percent' => 75])->save();
        Http::fake([self::BASE.'/balance' => Http::response([
            'is_in_debit' => false, 'can_sell' => true, 'allowed_ky_percentages' => [100, 25, 50],
        ])]);

        app(KMoneyTradingSync::class)->sync($this->company);

        $this->assertSame([25, 50, 100], $this->settings->fresh()->kmoney_allowed_percentages);
        $this->assertSame(100, $product->fresh()->kmoney_percent);
    }

    public function test_il_giro_orario_aggiorna_i_venditori_collegati(): void
    {
        $this->fakeBalance(inDebit: true);

        $this->artisan('kmoney:sync')->expectsOutputToContain('Venditori aggiornati: 1')->assertSuccessful();

        $this->assertTrue($this->settings->fresh()->kmoney_in_debt);
    }
}
