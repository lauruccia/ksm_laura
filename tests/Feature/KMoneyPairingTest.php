<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Payments\KMoney\KMoneyPairing;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Collegamento a KMoney con il solo numero di conto, con le chiamate intercettate.
 */
class KMoneyPairingTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://kmoney.test/api/v1';

    private const ACCOUNT = 'KYB00000109Z7XBH';

    private User $vendor;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ksm.kmoney.base_url' => self::BASE]);

        $plan = Plan::create([
            'name' => 'Ecommerce', 'slug' => 'ecommerce', 'price' => 2400, 'priority' => 40,
            'duration_days' => null, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);

        $this->vendor = User::create([
            'name' => 'Venditore', 'email' => 'venditore@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $this->vendor->id, 'plan_id' => $plan->id,
            'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test',
            'password' => 'password', 'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function pairing(): KMoneyPairing
    {
        return app(KMoneyPairing::class);
    }

    private function pending(): CompanyPaymentSetting
    {
        Http::fake([self::BASE.'/ecommerce/pairings' => Http::response(['uuid' => 'pa-1', 'status' => 'pending'], 201)]);

        return $this->pairing()->request($this->company, self::ACCOUNT);
    }

    private function fakeClaim(array $response, int $status = 200): void
    {
        Http::fake([
            self::BASE.'/ecommerce/pairings/pa-1*' => Http::response($response, $status),
            self::BASE.'/balance' => Http::response([
                'is_in_debit' => false, 'can_sell' => true, 'allowed_ky_percentages' => [25, 50, 100],
            ]),
        ]);
    }

    public function test_chiede_il_collegamento_senza_token_e_con_un_segreto_suo(): void
    {
        Http::fake([self::BASE.'/ecommerce/pairings' => Http::response(['uuid' => 'pa-1', 'status' => 'pending'], 201)]);

        $settings = $this->pairing()->request($this->company, ' kyb0000 0109z7xbh ');

        $this->assertSame(self::ACCOUNT, $settings->kmoney_account_number);
        $this->assertSame('pa-1', $settings->kmoney_pairing_uuid);
        $this->assertSame(KMoneyPairing::PENDING, $settings->kmoney_pairing_status);

        $secret = $settings->fresh()->kmoney_pairing_secret;
        $this->assertSame(48, strlen($secret));
        // Nel database il segreto e' cifrato.
        $this->assertNotSame($secret, DB::table('company_payment_settings')->value('kmoney_pairing_secret'));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && ! $request->hasHeader('Authorization')
            && $request['account_number'] === self::ACCOUNT
            && $request['claim_secret'] === $secret
            && $request['webhook_url'] === route('webhooks.kmoney', $this->company)
            && $request['platform'] === 'custom');
    }

    public function test_un_numero_di_conto_sbagliato_non_parte(): void
    {
        Http::fake();

        $this->actingAs($this->vendor)
            ->post(route('vendor.payments.kmoney.pair'), ['kmoney_account_number' => 'KYX123'])
            ->assertSessionHasErrors('kmoney_account_number');

        Http::assertNothingSent();
    }

    public function test_approvato_ritira_e_salva_token_e_segreto(): void
    {
        $this->pending();
        $this->fakeClaim(['status' => 'approved', 'api_token' => 'km_nuovo', 'webhook_secret' => 'whk_nuovo']);

        $this->assertSame(KMoneyPairing::APPROVED, $this->pairing()->check($this->company));

        $settings = $this->company->paymentSettings()->first();
        $this->assertSame('km_nuovo', $settings->kmoney_api_token);
        $this->assertSame('whk_nuovo', $settings->kmoney_webhook_secret);
        $this->assertTrue($settings->enable_kmoney);
        $this->assertNull($settings->kmoney_pairing_secret);
        // Subito dopo si legge lo stato del conto con il token nuovo.
        $this->assertSame([25, 50, 100], $settings->kmoney_allowed_percentages);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/ecommerce/pairings/pa-1?claim_secret='));
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'/balance'
            && $request->hasHeader('Authorization', 'Bearer km_nuovo'));
    }

    public function test_in_attesa_resta_in_attesa(): void
    {
        $this->pending();
        $this->fakeClaim(['status' => 'pending']);

        $this->assertSame(KMoneyPairing::PENDING, $this->pairing()->check($this->company));
        $this->assertNotNull($this->company->paymentSettings()->first()->kmoney_pairing_secret);
    }

    public static function closedRequests(): array
    {
        return [
            'rifiutato' => [['status' => 'rejected'], 200, KMoneyPairing::REJECTED],
            'ritirato altrove' => [['status' => 'approved', 'claimed' => true], 200, KMoneyPairing::LOST],
            'sconosciuto' => [['error' => 'Collegamento non trovato.'], 404, KMoneyPairing::LOST],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('closedRequests')]
    public function test_rifiutato_o_gia_ritirato_chiude_la_richiesta(array $response, int $code, string $expected): void
    {
        $this->pending();
        $this->fakeClaim($response, $code);

        $this->assertSame($expected, $this->pairing()->check($this->company));

        $settings = $this->company->paymentSettings()->first();
        $this->assertNull($settings->kmoney_pairing_secret);
        $this->assertNull($settings->kmoney_api_token);
    }

    public function test_il_venditore_collega_dalla_pagina_incassi(): void
    {
        Http::fake([self::BASE.'/ecommerce/pairings' => Http::response(['uuid' => 'pa-1', 'status' => 'pending'], 201)]);

        $this->actingAs($this->vendor)
            ->post(route('vendor.payments.kmoney.pair'), ['kmoney_account_number' => self::ACCOUNT])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->actingAs($this->vendor)->get(route('vendor.payments.edit'))
            ->assertOk()
            ->assertSee('in attesa che KMoney la approvi')
            ->assertSee(self::ACCOUNT)
            ->assertSee('Controlla ora');

        $this->fakeClaim(['status' => 'approved', 'api_token' => 'km_nuovo', 'webhook_secret' => 'whk_nuovo']);

        $this->actingAs($this->vendor)
            ->patch(route('vendor.payments.kmoney.check'))
            ->assertSessionHas('success', 'Conto KMoney collegato.');

        // Utente riletto, come a ogni richiesta vera.
        $this->actingAs($this->vendor->fresh())->get(route('vendor.payments.edit'))
            ->assertOk()
            ->assertSee('Collega di nuovo')
            ->assertDontSee('km_nuovo');
    }

    public function test_l_amministratore_collega_dalla_scheda_azienda(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.companies.edit', $this->company))
            ->assertOk()
            ->assertSee('Chiedi il collegamento');

        Http::fake([self::BASE.'/ecommerce/pairings' => Http::response(['uuid' => 'pa-1', 'status' => 'pending'], 201)]);

        $this->actingAs($admin)
            ->post(route('admin.companies.kmoney.pair', $this->company), ['kmoney_account_number' => self::ACCOUNT])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('admin.companies.edit', $this->company))
            ->assertOk()
            ->assertSee(self::ACCOUNT)
            ->assertSee('Controlla ora');
    }

    public function test_l_errore_di_kmoney_arriva_a_chi_ha_chiesto(): void
    {
        Http::fake([self::BASE.'/ecommerce/pairings' => Http::response(['error' => 'Conto non trovato.'], 422)]);

        $this->actingAs($this->vendor)
            ->post(route('vendor.payments.kmoney.pair'), ['kmoney_account_number' => self::ACCOUNT])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Conto non trovato.'));

        $this->assertNull($this->company->paymentSettings()->first()?->kmoney_pairing_status);
    }

    public function test_il_giro_periodico_ritira_i_collegamenti_approvati(): void
    {
        $this->pending();
        $this->fakeClaim(['status' => 'approved', 'api_token' => 'km_nuovo', 'webhook_secret' => 'whk_nuovo']);

        $this->artisan('kmoney:pairings')->expectsOutputToContain('approved 1')->assertSuccessful();

        $this->assertSame('km_nuovo', $this->company->paymentSettings()->first()->kmoney_api_token);
    }
}
