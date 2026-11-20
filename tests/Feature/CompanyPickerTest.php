<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il campo "azienda" con ricerca: le aziende sono migliaia, l'elenco arriva a pezzi.
 */
class CompanyPickerTest extends TestCase
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

        $plan = Plan::create([
            'name' => 'Ecommerce', 'slug' => 'ecommerce', 'price' => 349, 'priority' => 40, 'duration_days' => 365,
            'is_active' => true, 'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);
        foreach (['Calabria Sapori', 'Sapori di Sicilia', 'Caseificio Rossi'] as $i => $name) {
            $owner = User::create(['name' => $name, 'email' => "v{$i}@example.test", 'password' => 'password', 'user_type' => 'vendor']);
            Company::create(['user_id' => $owner->id, 'plan_id' => $plan->id, 'name' => $name, 'slug' => str($name)->slug(), 'is_active' => true]);
        }
    }

    public function test_cerca_le_aziende_che_contengono_il_testo(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.products.companies', ['q' => 'sapori']))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['name' => 'Calabria Sapori'])
            ->assertJsonFragment(['name' => 'Sapori di Sicilia'])
            ->assertJsonMissing(['name' => 'Caseificio Rossi']);
    }

    public function test_con_meno_di_due_lettere_non_cerca(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.products.companies', ['q' => 's']))
            ->assertOk()->assertExactJson([]);
    }

    public function test_chi_non_e_amministratore_non_la_usa(): void
    {
        $vendor = User::where('user_type', 'vendor')->firstOrFail();

        $this->assertNotSame(200, $this->actingAs($vendor)->getJson(route('admin.products.companies', ['q' => 'sapori']))->getStatusCode());
    }

    public function test_l_elenco_prodotti_non_carica_le_aziende_ma_ha_il_campo_con_ricerca(): void
    {
        $this->actingAs($this->admin)->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('data-company-picker', false)
            ->assertDontSee('Caseificio Rossi');
    }
}
