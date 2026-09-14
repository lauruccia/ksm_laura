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
 * L'editor delle descrizioni: nei moduli c'e', e il server salva solo HTML pulito.
 */
class RichTextEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Ecommerce', 'slug' => 'ecommerce', 'price' => 2400, 'priority' => 40,
            'duration_days' => null, 'is_active' => true,
            'capabilities' => [
                PlanCapabilities::DIRECTORY, PlanCapabilities::SHOP,
                PlanCapabilities::SHOWCASE, PlanCapabilities::DESCRIPTION,
            ],
        ]);

        $this->vendor = User::create([
            'name' => 'Venditore', 'email' => 'venditore@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $this->vendor->id, 'plan_id' => $plan->id,
            'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true,
        ]);
    }

    public function test_i_moduli_delle_descrizioni_hanno_l_editor(): void
    {
        $this->actingAs($this->vendor)
            ->get(route('vendor.profile.edit'))
            ->assertOk()
            ->assertSee('name="company_description" data-richtext', false)
            ->assertSee('js/richtext.js', false);

        $this->actingAs($this->vendor)
            ->get(route('vendor.products.create'))
            ->assertOk()
            ->assertSee('name="description" data-richtext', false);
    }

    public function test_la_descrizione_del_prodotto_si_salva_ripulita_e_si_mostra_formattata(): void
    {
        $this->actingAs($this->vendor)
            ->post(route('vendor.products.store'), [
                'name' => 'Nduja di Spilinga',
                'price' => 8.5,
                'stock' => 10,
                'product_type' => 'simple',
                'status' => 'active',
                'description' => '<p>Piccante <strong>davvero</strong></p><img src=x onerror="alert(1)"><ul><li>200 g</li></ul>',
            ])
            ->assertSessionHasNoErrors();

        $product = Product::where('name', 'Nduja di Spilinga')->firstOrFail();

        $this->assertStringContainsString('<strong>davvero</strong>', $product->description);
        $this->assertStringContainsString('<li>200 g</li>', $product->description);
        $this->assertStringNotContainsString('onerror', $product->description);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('<strong>davvero</strong>', false)
            ->assertDontSee('&lt;strong&gt;', false);
    }

    public function test_la_descrizione_dell_azienda_dal_profilo_si_salva_ripulita(): void
    {
        $this->actingAs($this->vendor)
            ->put(route('vendor.profile.update'), [
                'name' => 'Calabria Sapori',
                'company_description' => '<p onclick="rubare()">Prodotti <em>tipici</em></p><script>alert(1)</script>',
            ])
            ->assertSessionHasNoErrors();

        $description = $this->company->fresh()->company_description;

        $this->assertSame('<p>Prodotti <em>tipici</em></p>', $description);
    }

    public function test_il_testo_semplice_resta_testo(): void
    {
        $this->actingAs($this->vendor)
            ->put(route('vendor.profile.update'), [
                'name' => 'Calabria Sapori',
                'company_description' => "Prodotti tipici\nda Tropea",
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame("Prodotti tipici\nda Tropea", $this->company->fresh()->company_description);
    }
}
