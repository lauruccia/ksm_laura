<?php

namespace Tests\Feature;

use App\Models\AdminPaymentSetting;
use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\KMoneyCategoryRule;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chi puo' cambiare le quote KMoney, e da dove.
 *
 * Il venditore sceglie per categoria e per prodotti selezionati, ma non
 * con il conto in debito. L'amministrazione imposta contratto, debito e
 * tutto il resto, e decide se chi non ha KMoney puo' pagare in euro.
 */
class KMoneyRulesTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private User $vendor;

    private Company $company;

    private CompanyPaymentSetting $settings;

    private ProductCategory $vini;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'name' => 'Ecommerce', 'slug' => 'ecommerce', 'price' => 2400, 'priority' => 40,
            'duration_days' => null, 'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);

        $this->vendor = User::create([
            'name' => 'Venditore', 'email' => 'venditore@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $this->vendor->id, 'plan_id' => $this->plan->id,
            'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true,
        ]);

        $this->settings = CompanyPaymentSetting::create([
            'company_id' => $this->company->id, 'enable_kmoney' => true, 'kmoney_api_token' => 'km_test_token',
        ]);
        $this->settings->forceFill(['kmoney_contract_percent' => 50])->save();

        $this->vini = ProductCategory::create(['name' => 'Vini', 'slug' => 'vini']);
    }

    private function product(array $attributes = [], ?Company $company = null): Product
    {
        return Product::create($attributes + [
            'company_id' => ($company ?? $this->company)->id,
            'name' => 'Prodotto', 'slug' => 'prodotto-'.uniqid(),
            'price' => 10, 'stock' => 5, 'status' => 'active',
        ]);
    }

    private function otherCompany(): Company
    {
        $owner = User::create([
            'name' => 'Altro', 'email' => 'altro'.uniqid().'@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $owner->id, 'name' => 'Altra azienda', 'slug' => 'altra-'.uniqid(), 'is_active' => true,
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

    public function test_il_venditore_sceglie_la_quota_di_piu_prodotti_insieme_ma_solo_dei_suoi(): void
    {
        $scelto = $this->product();
        $altrui = $this->product([], $this->otherCompany());

        $this->actingAs($this->vendor)
            ->patch(route('vendor.products.kmoney'), ['products' => [$scelto->id, $altrui->id], 'percent' => 75])
            ->assertRedirect();

        $this->assertSame(75, $scelto->fresh()->kmoney_percent);
        $this->assertNull($altrui->fresh()->kmoney_discount_percent);
    }

    public function test_automatica_riporta_il_prodotto_alla_quota_del_contratto(): void
    {
        $product = $this->product(['kmoney_discount_percent' => 100, 'kmoney_percent' => 100]);

        $this->actingAs($this->vendor)
            ->patch(route('vendor.products.kmoney'), ['products' => [$product->id], 'percent' => 'auto'])
            ->assertRedirect();

        $this->assertNull($product->fresh()->kmoney_discount_percent);
        $this->assertSame(50, $product->fresh()->kmoney_percent);
    }

    public function test_con_il_conto_in_debito_il_venditore_non_decide(): void
    {
        $this->settings->forceFill(['kmoney_in_debt' => true])->save();
        $product = $this->product(['category_id' => $this->vini->id]);

        $this->actingAs($this->vendor)
            ->patch(route('vendor.products.kmoney'), ['products' => [$product->id], 'percent' => 25])
            ->assertForbidden();

        $this->actingAs($this->vendor)
            ->put(route('vendor.kmoney.update'), ['rules' => [$this->vini->id => 25]])
            ->assertForbidden();

        $this->assertSame(0, KMoneyCategoryRule::count());
    }

    public function test_la_quota_per_categoria_vale_sui_prodotti_e_si_toglie(): void
    {
        $vino = $this->product(['category_id' => $this->vini->id]);

        $this->actingAs($this->vendor)
            ->put(route('vendor.kmoney.update'), ['rules' => [$this->vini->id => 25]])
            ->assertRedirect();

        $this->assertSame(25, $vino->fresh()->kmoney_percent);

        $this->actingAs($this->vendor)
            ->put(route('vendor.kmoney.update'), ['rules' => [$this->vini->id => '']])
            ->assertRedirect();

        $this->assertSame(0, KMoneyCategoryRule::count());
        $this->assertSame(50, $vino->fresh()->kmoney_percent);
    }

    public function test_la_pagina_kmoney_del_venditore_mostra_contratto_e_categorie(): void
    {
        $this->product(['category_id' => $this->vini->id]);

        $this->actingAs($this->vendor)
            ->get(route('vendor.kmoney.edit'))
            ->assertOk()
            ->assertSee('Vini')
            ->assertSee('Quota del contratto:')
            ->assertSee('50%');
    }

    public function test_l_amministratore_imposta_contratto_categorie_e_debito(): void
    {
        $vino = $this->product(['category_id' => $this->vini->id]);
        $olio = $this->product();

        $payload = [
            'name' => $this->company->name,
            'login_email' => $this->vendor->email,
            'plan_id' => $this->plan->id,
            'is_active' => '1',
            'kmoney_contract_percent' => 75,
            'kmoney_in_debt' => '0',
            'kmoney_rules' => [$this->vini->id => 25],
        ];

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $this->company), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(75, $this->settings->fresh()->kmoney_contract_percent);
        $this->assertSame(25, $vino->fresh()->kmoney_percent);
        $this->assertSame(75, $olio->fresh()->kmoney_percent);

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $this->company), ['kmoney_in_debt' => '1'] + $payload)
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->settings->fresh()->kmoney_in_debt);
        $this->assertSame(100, $vino->fresh()->kmoney_percent);
    }

    public function test_l_amministratore_cambia_la_quota_di_prodotti_di_aziende_diverse(): void
    {
        $nostro = $this->product();
        $altrui = $this->product([], $this->otherCompany());

        $this->actingAs($this->admin())
            ->patch(route('admin.products.kmoney'), ['products' => [$nostro->id, $altrui->id], 'percent' => 25])
            ->assertRedirect();

        $this->assertEquals(25, $nostro->fresh()->kmoney_discount_percent);
        $this->assertEquals(25, $altrui->fresh()->kmoney_discount_percent);
    }

    public function test_l_amministratore_consente_di_pagare_tutto_in_euro(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.payments'), ['mode' => 'test', 'kmoney_euro_fallback' => '1'])
            ->assertRedirect();

        $this->assertTrue(AdminPaymentSetting::current()->kmoney_euro_fallback);
    }
}
