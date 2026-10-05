<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Ordine del catalogo: casuale per impostazione predefinita, stabile fra le pagine.
 */
class ProductSortingTest extends TestCase
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
        $product = Product::create(array_merge([
            'company_id' => $this->company->id,
            'name' => $name,
            'slug' => 'prodotto-'.uniqid(),
            'price' => 10,
            'stock' => 5,
            'status' => 'active',
        ], Arr::except($attributes, 'created_at')));

        if (isset($attributes['created_at'])) {
            $product->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        return $product;
    }

    /** @return list<string> nomi nell'ordine mostrato */
    private function names(array $query): array
    {
        return $this->get(route('products.index', $query))
            ->assertOk()
            ->viewData('products')
            ->getCollection()
            ->pluck('name')
            ->all();
    }

    public function test_senza_ordine_il_catalogo_si_mescola_a_ogni_visita(): void
    {
        foreach (range(1, 30) as $n) {
            $this->product("Prodotto $n");
        }

        $orders = collect(range(1, 6))->map(fn () => implode('|', $this->names([])));

        $this->assertGreaterThan(1, $orders->unique()->count());
    }

    public function test_lo_stesso_seme_da_lo_stesso_ordine_e_le_pagine_non_si_ripetono(): void
    {
        foreach (range(1, 30) as $n) {
            $this->product("Prodotto $n");
        }

        $first = $this->names(['mix' => 4242, 'per_pagina' => 12]);
        $again = $this->names(['mix' => 4242, 'per_pagina' => 12]);
        $second = $this->names(['mix' => 4242, 'per_pagina' => 12, 'page' => 2]);
        $third = $this->names(['mix' => 4242, 'per_pagina' => 12, 'page' => 3]);

        $this->assertSame($first, $again);
        $this->assertCount(30, array_unique([...$first, ...$second, ...$third]));
    }

    public function test_i_link_della_paginazione_portano_il_seme(): void
    {
        foreach (range(1, 30) as $n) {
            $this->product("Prodotto $n");
        }

        $this->get(route('products.index', ['per_pagina' => 12]))
            ->assertOk()
            ->assertSee('mix=', false);
    }

    public function test_ordini_per_prezzo_nome_e_data(): void
    {
        $this->product('Beta', ['price' => 20, 'created_at' => now()->subDays(2)]);
        $this->product('Alfa', ['price' => 30, 'created_at' => now()->subDay()]);
        $this->product('Gamma', ['price' => 10, 'created_at' => now()->subDays(3)]);

        $this->assertSame(['Gamma', 'Beta', 'Alfa'], $this->names(['ordina' => 'prezzo']));
        $this->assertSame(['Alfa', 'Beta', 'Gamma'], $this->names(['ordina' => 'prezzo_desc']));
        $this->assertSame(['Alfa', 'Beta', 'Gamma'], $this->names(['ordina' => 'nome']));
        $this->assertSame(['Gamma', 'Beta', 'Alfa'], $this->names(['ordina' => 'nome_desc']));
        $this->assertSame(['Alfa', 'Beta', 'Gamma'], $this->names(['ordina' => 'recenti']));
        $this->assertSame(['Gamma', 'Beta', 'Alfa'], $this->names(['ordina' => 'vecchi']));
    }

    public function test_ordine_per_percentuale_kmoney(): void
    {
        $this->product('Dieci', ['kmoney_percent' => 10]);
        $this->product('Trenta', ['kmoney_percent' => 30]);
        $this->product('Zero', ['kmoney_percent' => 0]);

        $this->assertSame(['Trenta', 'Dieci', 'Zero'], $this->names(['ordina' => 'kmoney']));
        $this->assertSame(['Zero', 'Dieci', 'Trenta'], $this->names(['ordina' => 'kmoney_asc']));
    }

    public function test_maggior_sconto_ordina_per_percentuale_e_lascia_in_fondo_chi_non_e_in_offerta(): void
    {
        $this->product('Meta', ['price' => 100, 'discount_price' => 50]);
        $this->product('Poco', ['price' => 100, 'discount_price' => 90]);
        $this->product('Venti per cento', ['price' => 1000, 'discount_price' => 800]);
        $this->product('Intero', ['price' => 100]);

        $names = $this->names(['ordina' => 'sconto']);

        $this->assertSame(['Meta', 'Venti per cento', 'Poco'], array_slice($names, 0, 3));
        $this->assertSame('Intero', $names[3]);
    }

    public function test_il_menu_offre_le_dieci_voci(): void
    {
        $this->get(route('products.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Casuale', 'Più recenti', 'Meno recenti', 'Prezzo: dal più basso', 'Prezzo: dal più alto',
                'Nome: A → Z', 'Nome: Z → A', '% Kmoney: più alta', '% Kmoney: più bassa', 'Maggior sconto',
            ]);
    }
}
