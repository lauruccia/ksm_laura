<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Barra laterale dell'area azienda: tutte le voci, per sezione.
 */
class VendorNavTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Ecommerce',
            'slug' => 'ecommerce',
            'price' => 349,
            'priority' => 40,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);

        $this->vendor = User::create([
            'name' => 'Venditore',
            'email' => 'venditore@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $this->vendor->id,
            'plan_id' => $plan->id,
            'name' => 'Caseificio Rossi',
            'slug' => 'caseificio-rossi',
            'is_active' => true,
        ]);
    }

    public function test_la_barra_laterale_porta_a_tutte_le_pagine_dell_azienda(): void
    {
        $this->actingAs($this->vendor)->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Riepilogo', 'Negozio', 'Prodotti', 'Nuovo prodotto', 'Ordini ricevuti', 'Quote KMoney',
                'Azienda', 'Profilo azienda', 'Metodi di incasso', 'Piano', 'Sul sito', 'La mia scheda pubblica', 'Catalogo', 'Carrello',
                'Altre aree', 'Il mio account'])
            ->assertSee(route('vendor.products.create'), false)
            ->assertSee(route('companies.show', ['company' => 'caseificio-rossi']), false);
    }

    public function test_nuovo_prodotto_e_la_voce_aperta_nel_modulo(): void
    {
        $html = $this->actingAs($this->vendor)->get(route('vendor.products.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('vendor.products.create'), '#').'"\s+aria-current="page"#', $html);
        $this->assertDoesNotMatchRegularExpression('#href="'.preg_quote(route('vendor.products.index'), '#').'"\s+aria-current="page"#', $html);
    }

    public function test_senza_shop_restano_solo_le_voci_dell_azienda(): void
    {
        $this->company->plan->update(['capabilities' => [PlanCapabilities::DIRECTORY]]);

        $this->actingAs($this->vendor)->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSee('Profilo azienda')
            ->assertDontSee('Nuovo prodotto')
            ->assertDontSee('Metodi di incasso');
    }
}
