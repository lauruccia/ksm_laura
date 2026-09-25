<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Order;
use App\Models\ProductBrand;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Azioni in blocco sugli elenchi di amministrazione: aziende, utenti,
 * ordini e anagrafiche. Le eliminazioni valgono solo sulle righe spuntate.
 */
class AdminBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);
        $this->admin = User::create([
            'name' => 'Amministratore', 'email' => 'admin@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function company(string $name, bool $active = true): Company
    {
        $owner = User::create(['name' => $name, 'email' => str($name)->slug().'@example.test', 'password' => 'password', 'user_type' => 'vendor']);

        return Company::create(['user_id' => $owner->id, 'name' => $name, 'slug' => str($name)->slug(), 'is_active' => $active]);
    }

    public function test_le_aziende_si_spengono_su_tutti_i_risultati_ma_si_eliminano_solo_a_mano(): void
    {
        $bar = $this->company('Bar Centrale');
        $bakery = $this->company('Bar Pasticceria');
        $shop = $this->company('Ferramenta');

        $this->actingAs($this->admin)
            ->patch(route('admin.companies.bulk'), ['action' => 'deactivate', 'scope' => 'all', 'cerca' => 'Bar'])
            ->assertSessionHas('success', '2 aziende spente.');

        $this->assertFalse($bar->fresh()->is_active);
        $this->assertFalse($bakery->fresh()->is_active);
        $this->assertTrue($shop->fresh()->is_active);

        $this->patch(route('admin.companies.bulk'), ['action' => 'delete', 'scope' => 'all', 'cerca' => 'Bar'])
            ->assertSessionHasErrors('scope');
        $this->assertModelExists($bar);

        $this->patch(route('admin.companies.bulk'), ['action' => 'delete', 'ids' => [$bar->id]])
            ->assertSessionHas('success', '1 azienda eliminata.');
        $this->assertModelMissing($bar);
    }

    public function test_gli_utenti_in_blocco_lasciano_fuori_se_stessi_e_chi_ha_un_azienda(): void
    {
        $company = $this->company('Caseificio');
        $buyer = User::create(['name' => 'Cliente', 'email' => 'cliente@example.test', 'password' => 'password', 'user_type' => 'buyer']);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.bulk'), ['action' => 'deactivate', 'scope' => 'all'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->admin->fresh()->is_active);
        $this->assertFalse($buyer->fresh()->is_active);

        $this->patch(route('admin.users.bulk'), ['action' => 'delete', 'ids' => [$this->admin->id, $company->user_id, $buyer->id]])
            ->assertSessionHas('success', "1 utente eliminato. 1 lasciato: ha un'azienda collegata.");

        $this->assertModelExists($this->admin);
        $this->assertModelExists($company->user);
        $this->assertModelMissing($buyer);
    }

    public function test_gli_ordini_cambiano_stato_insieme(): void
    {
        $company = $this->company('Caseificio');
        $orders = collect(range(1, 3))->map(fn () => Order::create([
            'company_id' => $company->id, 'user_id' => $this->admin->id, 'subtotal' => 10, 'total' => 10, 'status' => 'paid', 'billing_name' => 'Mario Rossi',
        ]));

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.bulk'), ['action' => 'status', 'status' => 'shipped', 'ids' => $orders->take(2)->pluck('id')->all()])
            ->assertSessionHas('success', '2 ordini segnati come «Spedito».');

        $this->assertSame(['shipped', 'shipped', 'paid'], $orders->map(fn ($order) => $order->fresh()->status)->all());

        $this->get(route('admin.orders.index', ['stato' => 'shipped']))
            ->assertOk()
            ->assertSee('Spedito');
    }

    public function test_le_anagrafiche_si_eliminano_in_blocco_ma_le_categorie_no(): void
    {
        $brands = collect(['Alfa', 'Beta', 'Gamma'])->map(fn ($name) => ProductBrand::create(['name' => $name, 'slug' => strtolower($name)]));

        $this->actingAs($this->admin)
            ->get(route('admin.brands.index'))
            ->assertOk()
            ->assertSee('data-bulk-item', false);

        $this->patch(route('admin.brands.bulk'), ['action' => 'delete', 'ids' => $brands->take(2)->pluck('id')->all()])
            ->assertSessionHas('success', '2 elementi eliminati.');
        $this->assertSame(['Gamma'], ProductBrand::pluck('name')->all());

        // Le categorie non hanno la rotta: la loro eliminazione sposta prima il ramo.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.company_categories.bulk'));
        CompanyCategory::create(['name' => 'Edilizia', 'slug' => 'edilizia']);
        $this->get(route('admin.company_categories.index'))->assertOk()->assertDontSee('data-bulk-item', false);
    }
}
