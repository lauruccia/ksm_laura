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
 * Gestione delle varianti dal modulo prodotto dell'area azienda.
 */
class VendorProductVariantsTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mozzarella',
            'price' => 5,
            'stock' => 10,
            'product_type' => 'variable',
            'status' => 'active',
        ], $overrides);
    }

    public function test_crea_il_prodotto_con_le_varianti_compilate_e_salta_le_righe_vuote(): void
    {
        $this->actingAs($this->vendor)
            ->post(route('vendor.products.store'), $this->payload([
                'variants' => [
                    ['type' => 'Formato', 'value' => '250 g', 'price' => '4.50', 'stock' => '8', 'sku' => 'MZ-250'],
                    ['type' => 'Formato', 'value' => '500 g', 'price' => '8.00', 'stock' => '3', 'sku' => 'MZ-500'],
                    ['type' => '', 'value' => '', 'price' => '', 'stock' => '', 'sku' => ''],
                ],
            ]))
            ->assertRedirect(route('vendor.products.index'));

        $product = Product::where('company_id', $this->company->id)->firstOrFail();

        $this->assertSame(['250 g', '500 g'], $product->variants()->orderBy('id')->pluck('variant_value')->all());
    }

    public function test_aggiorna_toglie_e_aggiunge_varianti(): void
    {
        $product = Product::create($this->payload(['company_id' => $this->company->id, 'slug' => 'mozzarella']));
        $small = $product->variants()->create(['variant_type' => 'Formato', 'variant_value' => '250 g']);
        $large = $product->variants()->create(['variant_type' => 'Formato', 'variant_value' => '500 g']);

        $this->actingAs($this->vendor)
            ->put(route('vendor.products.update', $product), $this->payload([
                'variants' => [
                    ['id' => $small->id, 'type' => 'Formato', 'value' => '300 g', 'price' => '5', 'stock' => '4'],
                    ['id' => $large->id, 'type' => '', 'value' => ''],
                    ['type' => 'Formato', 'value' => '1 kg', 'price' => '15', 'stock' => '2'],
                ],
            ]))
            ->assertRedirect();

        $this->assertSame('300 g', $small->fresh()->variant_value);
        $this->assertNull($large->fresh());
        $this->assertSame(['300 g', '1 kg'], $product->variants()->orderBy('id')->pluck('variant_value')->all());
    }

    public function test_un_prodotto_semplice_non_tiene_varianti(): void
    {
        $product = Product::create($this->payload(['company_id' => $this->company->id, 'slug' => 'mozzarella']));
        $product->variants()->create(['variant_type' => 'Formato', 'variant_value' => '250 g']);

        $this->actingAs($this->vendor)
            ->put(route('vendor.products.update', $product), $this->payload([
                'product_type' => 'simple',
                'variants' => [['type' => 'Formato', 'value' => '250 g']],
            ]))
            ->assertRedirect();

        $this->assertSame(0, $product->variants()->count());
    }

    public function test_non_si_possono_modificare_le_varianti_di_un_altro_prodotto(): void
    {
        $mine = Product::create($this->payload(['company_id' => $this->company->id, 'slug' => 'mozzarella']));

        $otherUser = User::create([
            'name' => 'Altro venditore',
            'email' => 'altro@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);
        $otherCompany = Company::create([
            'user_id' => $otherUser->id,
            'plan_id' => $this->company->plan_id,
            'name' => 'Bottega Verdi',
            'slug' => 'bottega-verdi',
            'is_active' => true,
        ]);
        $theirs = Product::create($this->payload(['company_id' => $otherCompany->id, 'slug' => 'burrata']));
        $foreign = $theirs->variants()->create(['variant_type' => 'Formato', 'variant_value' => 'Originale']);

        // L'id di una variante altrui non la sposta ne' la modifica: si crea una riga nuova.
        $this->actingAs($this->vendor)
            ->put(route('vendor.products.update', $mine), $this->payload([
                'variants' => [['id' => $foreign->id, 'type' => 'Formato', 'value' => 'Manomessa']],
            ]))
            ->assertRedirect();

        $this->assertSame('Originale', $foreign->fresh()->variant_value);
        $this->assertSame($theirs->id, $foreign->fresh()->product_id);
        $this->assertSame(['Manomessa'], $mine->variants()->pluck('variant_value')->all());
        $this->assertSame(1, ProductVariant::where('product_id', $theirs->id)->count());
    }
}
