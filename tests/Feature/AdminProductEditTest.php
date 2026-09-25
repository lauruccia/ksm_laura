<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La modifica di un prodotto dall'amministrazione.
 *
 * Usa lo stesso modulo dell'area azienda: qui si prova che la pagina si
 * apra, che ogni campo arrivi sul prodotto senza spostarlo di azienda e
 * che chi puo' solo vedere il catalogo non possa cambiarlo.
 */
class AdminProductEditTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = Role::SUPER_ADMIN, array $permissions = []): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $role],
            ['name' => $role, 'is_system' => $role === Role::SUPER_ADMIN, 'permissions' => $permissions]
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

    private function product(): Product
    {
        $owner = User::create([
            'name' => 'Titolare',
            'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        $company = Company::create([
            'user_id' => $owner->id,
            'name' => 'Caseificio Rossi',
            'slug' => 'caseificio-rossi',
            'is_active' => true,
        ]);

        return Product::create([
            'company_id' => $company->id,
            'name' => 'Mozzarella',
            'slug' => 'mozzarella-abcde',
            'price' => 5,
            'product_type' => 'simple',
            'status' => 'active',
        ]);
    }

    public function test_la_pagina_di_modifica_si_apre_con_i_valori_del_prodotto(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin())
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Modifica prodotto')
            ->assertSee('value="Mozzarella"', false)
            ->assertSee('Caseificio Rossi');

        $this->actingAs($this->admin())
            ->get(route('admin.products.index'))
            ->assertSee(route('admin.products.edit', $product), false);
    }

    public function test_l_amministratore_modifica_tutti_i_campi_e_le_varianti(): void
    {
        $product = $this->product();
        $category = ProductCategory::create(['name' => 'Latticini', 'slug' => 'latticini']);

        $this->actingAs($this->admin())
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'name' => 'Mozzarella di bufala',
                'sku' => 'MOZ-01',
                'category_id' => $category->id,
                'short_description' => 'Fresca ogni mattina',
                'description' => '<p>Buona</p><script>alert(1)</script>',
                'price' => 8,
                'discount_price' => 7,
                'weight_kg' => 0.25,
                'product_type' => 'variable',
                'status' => 'inactive',
                'variants' => [
                    ['type' => 'Formato', 'value' => '250 g', 'price' => 7, 'stock' => 4],
                    ['type' => '', 'value' => ''],
                ],
            ])
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('Mozzarella di bufala', $product->name);
        $this->assertSame('MOZ-01', $product->sku);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('7.00', $product->discount_price);
        $this->assertSame('inactive', $product->status);
        $this->assertStringNotContainsString('script', $product->description);
        $this->assertSame('mozzarella-abcde', $product->slug);
        $this->assertSame('Caseificio Rossi', $product->company->name);
        $this->assertSame(['250 g'], $product->variants()->pluck('variant_value')->all());
    }

    public function test_chi_vede_solo_il_catalogo_non_modifica(): void
    {
        $product = $this->product();
        $viewer = $this->admin('catalogo', [\App\Support\Permissions::CATALOG_VIEW]);

        $this->actingAs($viewer)->get(route('admin.products.edit', $product))->assertForbidden();
        $this->actingAs($viewer)
            ->put(route('admin.products.update', $product), ['name' => 'X', 'price' => 1, 'product_type' => 'simple', 'status' => 'active'])
            ->assertForbidden();

        $this->assertSame('Mozzarella', $product->fresh()->name);
    }
}
