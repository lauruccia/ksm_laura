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

    public function test_ripristino_stock_originale_preciso_e_ripetibile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'stock-dump');
        $rows = [];
        foreach (['unmanaged', 'sold', 'edited', 'positive'] as $slug) {
            $product = Product::create($this->payload(['company_id' => $this->company->id, 'slug' => $slug, 'product_type' => 'variant', 'stock' => $slug === 'positive' ? 4 : 0]));
            \Illuminate\Support\Facades\DB::table('products')->where('id', $product->id)->update(['updated_at' => $slug === 'edited' ? '2026-09-16 12:00:00' : '2025-12-01 10:00:00']);
            $stock = $slug === 'sold' ? '0' : 'NULL';
            $rows[] = "({$product->id}, {$this->company->id}, '{$slug}', {$stock}, 'variant', '2025-12-01 10:00:00')";
        }
        file_put_contents($path, "INSERT INTO `products` (`id`, `company_id`, `slug`, `stock`, `product_type`, `updated_at`) VALUES\n".implode(",\n", $rows).";\n");
        try {
            $this->artisan('legacy:restore-stock', ['file' => $path])->expectsOutputToContain('Prodotti da ripristinare: 1')->assertSuccessful();
            $this->assertSame(0, Product::where('slug', 'unmanaged')->first()->stock);
            $this->artisan('legacy:restore-stock', ['file' => $path, '--apply' => true])->expectsOutputToContain('Prodotti ripristinati: 1')->assertSuccessful();
            $this->artisan('legacy:restore-stock', ['file' => $path, '--apply' => true])->expectsOutputToContain('Prodotti ripristinati: 0')->assertSuccessful();
            $this->assertNull(Product::where('slug', 'unmanaged')->first()->stock);
            $this->assertSame('variable', Product::where('slug', 'unmanaged')->first()->product_type);
            $this->assertSame('variable', Product::where('slug', 'positive')->first()->product_type);
            $this->assertSame('variant', Product::where('slug', 'edited')->first()->product_type);
            $this->assertSame(0, Product::where('slug', 'sold')->first()->stock);
            $this->assertSame(0, Product::where('slug', 'edited')->first()->stock);
            $this->assertSame(4, Product::where('slug', 'positive')->first()->stock);
        } finally {
            unlink($path);
        }
    }

    public function test_stock_non_gestito_disponibile_in_catalogo_e_carrello_ma_zero_esaurito(): void
    {
        $this->actingAs($this->vendor)->post(route('vendor.products.store'), $this->payload([
            'product_type' => 'simple', 'stock' => '',
        ]))->assertSessionHasNoErrors();
        $product = $this->company->products()->firstOrFail();
        $this->assertNull($product->stock);
        $this->assertTrue($product->isInStock());
        $this->get(route('products.index', ['disponibili' => 1]))->assertSee($product->name);
        $this->post(route('cart.add', $product->slug), ['quantita' => 12])->assertSessionHasNoErrors();
        $this->assertSame(12, session('cart')[$product->id]['quantity']);
        $product->update(['stock' => 0]);
        $this->assertFalse($product->fresh()->isInStock());
        $this->get(route('products.index', ['disponibili' => 1]))->assertDontSee($product->name);
        $this->post(route('cart.add', $product->slug))->assertSessionHas('error');
    }

    public function test_varianti_separate_nel_carrello_con_prezzi_e_limiti_di_giacenza(): void
    {
        $product = Product::create($this->payload(['company_id' => $this->company->id, 'slug' => 'variabile', 'stock' => 0]));
        $small = $product->variants()->create(['variant_type' => 'Formato', 'variant_value' => '250 g', 'variant_price' => 4, 'variant_stock' => 2]);
        $large = $product->variants()->create(['variant_type' => 'Formato', 'variant_value' => '500 g', 'variant_price' => 7, 'variant_stock' => null]);
        $sold = $product->variants()->create(['variant_type' => 'Formato', 'variant_value' => '1 kg', 'variant_stock' => '0']);
        $this->get(route('products.show', $product->slug))->assertOk()->assertSee('250 g')->assertSee('500 g')->assertSee('name="variant_id"', false);
        $this->get(route('products.index', ['disponibili' => 1]))->assertSee($product->name);
        $this->post(route('cart.add', $product->slug))->assertSessionHasErrors('variant_id');
        $this->post(route('cart.add', $product->slug), ['variant_id' => $small->id, 'quantita' => 2])->assertSessionHasNoErrors();
        $this->post(route('cart.add', $product->slug), ['variant_id' => $small->id])->assertSessionHasErrors('quantita');
        $this->post(route('cart.add', $product->slug), ['variant_id' => $sold->id])->assertSessionHasErrors('quantita');
        $this->post(route('cart.add', $product->slug), ['variant_id' => $large->id, 'quantita' => 9])->assertSessionHasNoErrors();
        $cart = session('cart');
        $this->assertCount(2, $cart);
        $this->assertEquals(4, $cart[$product->id.':'.$small->id]['price']);
        $this->assertEquals(7, $cart[$product->id.':'.$large->id]['price']);
        $this->patch(route('cart.update', $product->slug), ['variant_id' => $small->id, 'quantita' => 3])->assertSessionHasErrors('quantita');
        $this->patch(route('cart.update', $product->slug), ['variant_id' => $large->id, 'quantita' => 8])->assertSessionHasNoErrors();
        $this->delete(route('cart.remove', $product->slug), ['variant_id' => $small->id])->assertSessionHasNoErrors();
        $this->assertCount(1, session('cart'));
        $this->assertSame(8, session('cart')[$product->id.':'.$large->id]['quantity']);
    }

    public function test_non_accetta_varianti_di_altri_prodotti_o_prodotti_variabili_vuoti(): void
    {
        $product = Product::create($this->payload(['company_id' => $this->company->id, 'slug' => 'variabile']));
        $other = Product::create($this->payload(['company_id' => $this->company->id, 'slug' => 'altro']));
        $product->variants()->create(['variant_type' => 'Taglia', 'variant_value' => 'M']);
        $foreign = $other->variants()->create(['variant_type' => 'Taglia', 'variant_value' => 'L']);
        $this->post(route('cart.add', $product->slug), ['variant_id' => $foreign->id])->assertNotFound();
        $this->actingAs($this->vendor)->post(route('vendor.products.store'), $this->payload())->assertSessionHasErrors('variants');
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
