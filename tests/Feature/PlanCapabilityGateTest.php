<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cosa si vede di un'azienda lo decide il piano, non i dati caricati.
 */
class PlanCapabilityGateTest extends TestCase
{
    use RefreshDatabase;

    private function companyWith(array $capabilities): Company
    {
        $plan = Plan::create([
            'name' => 'Piano '.uniqid(),
            'slug' => 'piano-'.uniqid(),
            'price' => 99,
            'priority' => 20,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => $capabilities,
        ]);

        $user = User::create([
            'name' => 'Titolare',
            'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => 'Azienda di prova',
            'slug' => 'azienda-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function productFor(Company $company): Product
    {
        return Product::create([
            'company_id' => $company->id,
            'name' => 'Pane fresco',
            'slug' => 'pane-fresco-'.uniqid(),
            'price' => 3.50,
            'stock' => 10,
            'status' => 'active',
        ]);
    }

    public function test_senza_shop_nel_piano_il_prodotto_non_e_raggiungibile(): void
    {
        $company = $this->companyWith([PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD]);
        $product = $this->productFor($company);

        $this->get(route('products.show', $product->slug))->assertNotFound();
        $this->post(route('cart.add', $product->slug))->assertNotFound();
        $this->get(route('products.index'))->assertOk()->assertDontSee('Pane fresco');
    }

    public function test_con_lo_shop_nel_piano_il_prodotto_si_vede_e_si_aggiunge(): void
    {
        $company = $this->companyWith([
            PlanCapabilities::DIRECTORY,
            PlanCapabilities::CONTACT_CARD,
            PlanCapabilities::SHOP,
        ]);
        $product = $this->productFor($company);

        $this->get(route('products.show', $product->slug))->assertOk();
        $this->get(route('products.index'))->assertOk()->assertSee('Pane fresco');
    }

    public function test_la_vetrina_completa_compare_solo_se_il_piano_la_comprende(): void
    {
        $essenziale = $this->companyWith([PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD]);
        $essenziale->update(['company_description' => 'Descrizione da non mostrare']);

        $completa = $this->companyWith([
            PlanCapabilities::DIRECTORY,
            PlanCapabilities::CONTACT_CARD,
            PlanCapabilities::SHOWCASE,
        ]);
        $completa->update(['company_description' => 'Descrizione da mostrare']);

        $this->get(route('companies.show', $essenziale->slug))
            ->assertOk()
            ->assertDontSee('Descrizione da non mostrare');

        $this->get(route('companies.show', $completa->slug))
            ->assertOk()
            ->assertSee('Descrizione da mostrare');
    }
}
