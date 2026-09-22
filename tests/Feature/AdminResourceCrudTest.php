<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le anagrafiche di amministrazione condividono un solo controller.
 *
 * Qui si prova che modifica, salvataggio e cancellazione arrivino al
 * record giusto: il nome del parametro di rotta cambia da un'anagrafica
 * all'altra, e il controller generico deve reggerli tutti.
 */
class AdminResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );

        return User::create([
            'name' => 'Amministratore',
            'email' => 'admin'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'admin',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Vetrina',
            'slug' => 'vetrina',
            'price' => 199,
            'priority' => 30,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY],
        ]);
    }

    public function test_la_modifica_di_un_piano_si_apre(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->admin())
            ->get(route('admin.plans.edit', $plan))
            ->assertOk()
            ->assertSee('Vetrina');
    }

    public function test_la_modifica_mostra_tutte_le_voci_del_piano(): void
    {
        $plan = $this->plan();
        $plan->update(['features' => array_map(fn ($n) => "Voce $n", range(1, 9))]);

        // Prima c'erano sei caselle fisse: salvando, dalla settima in poi sparivano.
        $this->actingAs($this->admin())
            ->get(route('admin.plans.edit', $plan))
            ->assertOk()
            ->assertSee('value="Voce 9"', false);
    }

    public function test_il_salvataggio_di_un_piano_cambia_le_voci_spuntate(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->admin())
            ->put(route('admin.plans.update', $plan), [
                'name' => 'Vetrina',
                'slug' => 'vetrina',
                'price' => 249,
                'duration_days' => 365,
                'priority' => 30,
                'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
                'is_active' => '1',
            ])
            ->assertRedirect();

        $plan->refresh();

        $this->assertSame('249.00', $plan->price);
        $this->assertTrue($plan->allows(PlanCapabilities::SHOP));
    }

    public function test_la_cancellazione_toglie_il_record(): void
    {
        $category = CompanyCategory::create(['name' => 'Alimentari', 'slug' => 'alimentari']);

        $this->actingAs($this->admin())
            ->delete(route('admin.company_categories.destroy', $category))
            ->assertRedirect(route('admin.company_categories.index'));

        $this->assertNull(CompanyCategory::find($category->id));
    }

    public function test_la_scheda_rimanda_alla_modifica(): void
    {
        $plan = $this->plan();

        $this->actingAs($this->admin())
            ->get(route('admin.plans.show', $plan))
            ->assertRedirect(route('admin.plans.edit', $plan));
    }

    public function test_la_modifica_di_un_azienda_si_apre(): void
    {
        $owner = User::create([
            'name' => 'Titolare',
            'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        $company = Company::create([
            'user_id' => $owner->id,
            'plan_id' => $this->plan()->id,
            'name' => 'Panificio Rossi',
            'slug' => 'panificio-rossi',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.companies.edit', $company))
            ->assertOk()
            ->assertSee('Panificio Rossi');
    }

    public function test_un_record_inesistente_da_404(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.plans.edit', 999))
            ->assertNotFound();
    }
}
