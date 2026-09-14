<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filtri della barra laterale del catalogo pubblico.
 */
class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Piano shop',
            'slug' => 'piano-shop',
            'price' => 99,
            'priority' => 20,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP],
        ]);

        $user = User::create([
            'name' => 'Titolare',
            'email' => 'titolare@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'name' => 'Azienda di prova',
            'slug' => 'azienda-di-prova',
            'is_active' => true,
        ]);
    }

    private function product(string $name, array $attributes = []): Product
    {
        return Product::create(array_merge([
            'company_id' => $this->company->id,
            'name' => $name,
            'slug' => 'prodotto-'.uniqid(),
            'price' => 10,
            'stock' => 5,
            'status' => 'active',
        ], $attributes));
    }

    public function test_la_categoria_madre_comprende_le_sottocategorie(): void
    {
        $food = ProductCategory::create(['name' => 'Mangiare', 'slug' => 'mangiare']);
        $typical = ProductCategory::create(['name' => 'Tipici', 'slug' => 'tipici', 'parent_id' => $food->id]);
        $clothes = ProductCategory::create(['name' => 'Vestire', 'slug' => 'vestire']);

        $this->product('Nduja piccante', ['category_id' => $typical->id]);
        $this->product('Maglia di lana', ['category_id' => $clothes->id]);

        $this->get(route('products.index', ['categoria' => $food->id]))
            ->assertOk()
            ->assertSee('Nduja piccante')
            ->assertDontSee('Maglia di lana');
    }

    public function test_filtri_disponibili_kmoney_e_offerta(): void
    {
        $this->product('Olio in offerta', ['price' => 20, 'discount_price' => 15]);
        $this->product('Vino col circuito', ['kmoney_percent' => 50]);
        $this->product('Miele finito', ['stock' => 0]);

        $this->get(route('products.index', ['offerta' => 1]))
            ->assertSee('Olio in offerta')
            ->assertDontSee('Vino col circuito');

        $this->get(route('products.index', ['kmoney' => 1]))
            ->assertSee('Vino col circuito')
            ->assertDontSee('Olio in offerta');

        $this->get(route('products.index', ['disponibili' => 1]))
            ->assertSee('Olio in offerta')
            ->assertDontSee('Miele finito');
    }

    public function test_un_numero_per_pagina_non_previsto_torna_al_predefinito(): void
    {
        $this->get(route('products.index', ['per_pagina' => 1000]))
            ->assertOk()
            ->assertViewHas('products', fn ($products) => $products->perPage() === 24);
    }
}
