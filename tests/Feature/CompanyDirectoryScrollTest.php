<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Support\CompanyDirectory;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Directory a scorrimento continuo: la pagina intera per chi arriva,
 * le sole schede per lo script che chiede la pagina successiva.
 */
class CompanyDirectoryScrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Anagrafica',
            'slug' => 'anagrafica',
            'price' => 240,
            'priority' => 10,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD],
        ]);

        foreach (range(1, CompanyDirectory::PER_PAGE + 6) as $i) {
            $user = User::create([
                'name' => "Azienda $i",
                'email' => "azienda-$i@example.test",
                'password' => 'password',
                'user_type' => 'vendor',
            ]);

            Company::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'name' => "Azienda $i",
                'slug' => "azienda-$i",
                'is_active' => true,
            ]);
        }
    }

    public function test_la_prima_pagina_ha_la_paginazione_e_il_link_alla_successiva(): void
    {
        $response = $this->get(route('companies.index'))
            ->assertOk()
            ->assertViewHas('companies', fn ($companies) => $companies->perPage() === 24)
            ->assertSee('ksm-pagination', false)
            ->assertSee('data-directory-next', false);

        $this->assertSame(24, substr_count($response->getContent(), 'ksm-card ksm-bizcard'));
    }

    public function test_lo_script_riceve_solo_le_schede_della_pagina_successiva(): void
    {
        $response = $this->get(route('companies.index', ['page' => 2, 'mix' => 7]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('<html', false)
            ->assertDontSee('ksm-pagination', false)
            // L'ultima pagina non rimanda a nessuna successiva.
            ->assertDontSee('data-directory-next', false);

        $this->assertSame(6, substr_count($response->getContent(), 'ksm-card ksm-bizcard'));
    }

    public function test_le_pagine_caricate_non_ripetono_aziende(): void
    {
        $names = fn (int $page) => $this->get(
            route('companies.index', ['page' => $page, 'mix' => 7]),
            ['X-Requested-With' => 'XMLHttpRequest']
        )->viewData('companies')->pluck('name');

        $all = $names(1)->concat($names(2));

        $this->assertCount(30, $all);
        $this->assertCount(30, $all->unique());
    }
}
