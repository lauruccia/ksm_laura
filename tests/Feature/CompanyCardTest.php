<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Plan;
use App\Models\User;
use App\Support\CategoryIcon;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il biglietto dell'azienda negli elenchi: dati di contatto e icona del settore.
 */
class CompanyCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_sottocategoria_prende_l_icona_della_madre(): void
    {
        $root = CompanyCategory::create(['name' => 'Mangiare e Bere', 'slug' => 'mangiare-e-bere']);
        $middle = CompanyCategory::create(['name' => 'Ristoranti', 'slug' => 'ristoranti', 'parent_id' => $root->id]);
        $leaf = CompanyCategory::create(['name' => 'Pizzerie', 'slug' => 'pizzerie', 'parent_id' => $middle->id]);
        $unknown = CompanyCategory::create(['name' => 'Nuovo settore', 'slug' => 'nuovo-settore']);

        $this->assertSame('utensils', CategoryIcon::for($leaf));
        $this->assertSame('utensils', CategoryIcon::for($root));
        $this->assertSame(CategoryIcon::FALLBACK, CategoryIcon::for($unknown));
        $this->assertSame(CategoryIcon::FALLBACK, CategoryIcon::for(null));
    }

    public function test_il_biglietto_mostra_i_contatti_e_il_settore(): void
    {
        $plan = Plan::create([
            'name' => 'Anagrafica',
            'slug' => 'anagrafica',
            'price' => 240,
            'priority' => 10,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD, PlanCapabilities::REVIEWS],
        ]);

        $root = CompanyCategory::create(['name' => 'Salute e Bellezza', 'slug' => 'salute-e-bellezza']);
        $sector = CompanyCategory::create(['name' => 'Beauty Center', 'slug' => 'beauty-center', 'parent_id' => $root->id]);

        $user = User::create([
            'name' => 'Iris',
            'email' => 'iris@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        Company::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'category_id' => $sector->id,
            'name' => 'Iris Studio Estetica',
            'slug' => 'iris-studio-estetica',
            'address' => 'Via Stilicone, 3b',
            'city' => 'Guidonia Montecelio',
            'region' => 'Lazio',
            'email' => 'iris@ksm.test',
            'phone' => '+390774283714',
            'website' => 'https://www.irisstudioestetica.it/',
            'is_active' => true,
        ]);

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Iris Studio Estetica')
            ->assertSee('Guidonia Montecelio, Lazio')
            ->assertSee('Via Stilicone, 3b')
            ->assertSee('Beauty Center')
            ->assertSee('iris@ksm.test')
            ->assertSee('+390774283714')
            ->assertSee('irisstudioestetica.it')
            ->assertSee('ksm-bizcard__rating', false)
            ->assertSee('data-sector-icon="heart"', false);
    }

    private function companyOn(array $capabilities, string $name): Company
    {
        $plan = Plan::create([
            'name' => "Piano $name", 'slug' => str("piano $name")->slug()->toString(), 'price' => 600, 'priority' => 10,
            'duration_days' => 365, 'is_active' => true, 'capabilities' => $capabilities,
        ]);

        $user = User::create([
            'name' => $name, 'email' => str($name)->slug()->toString().'@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'city' => 'Roma',
            'banner' => 'companies/banner-di-prova.jpg',
            'is_active' => true,
        ]);
    }

    public function test_con_la_vetrina_completa_il_biglietto_ha_banner_e_porta_alla_pagina(): void
    {
        $company = $this->companyOn([
            PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD,
            PlanCapabilities::BANNER, PlanCapabilities::SHOWCASE,
        ], 'Vetrina Roma');

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertSee(route('companies.show', $company->slug), false)
            ->assertSee('storage/companies/banner-di-prova.jpg', false)
            ->assertDontSee('ksm-bizcard--compact', false);

        $this->get(route('companies.show', $company->slug))->assertOk();
    }

    public function test_biglietto_e_anagrafica_non_hanno_pagina_link_ne_banner(): void
    {
        // Anche con la voce banner spuntata: senza pagina il banner non si mostra.
        $company = $this->companyOn([
            PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD,
            PlanCapabilities::LOGO, PlanCapabilities::BANNER,
        ], 'Biglietto Roma');

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Biglietto Roma')
            ->assertSee('ksm-bizcard--compact', false)
            ->assertDontSee(route('companies.show', $company->slug), false)
            ->assertDontSee('ksm-bizcard__cover', false)
            ->assertDontSee('banner-di-prova.jpg', false);

        $this->get(route('companies.show', $company->slug))->assertNotFound();
        $this->post(route('companies.contact', $company->slug), [])->assertNotFound();
        $this->post(route('companies.reviews.store', $company->slug), [])->assertNotFound();
    }
}
