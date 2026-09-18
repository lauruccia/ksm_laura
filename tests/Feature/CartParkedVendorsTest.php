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
 * Un ordine resta di un venditore solo, ma il carrello dell'altro
 * non si perde: aspetta e si riapre quando serve.
 */
class CartParkedVendorsTest extends TestCase
{
    use RefreshDatabase;

    private Product $rossi;

    private Product $bianchi;

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

        $this->rossi = $this->productFor($plan, 'Caseificio Rossi', 'caseificio-rossi', 'Mozzarella', 'mozzarella', 5);
        $this->bianchi = $this->productFor($plan, 'Forno Bianchi', 'forno-bianchi', 'Pane', 'pane', 3);
    }

    private function productFor(Plan $plan, string $companyName, string $companySlug, string $name, string $slug, float $price): Product
    {
        $owner = User::create([
            'name' => $companyName,
            'email' => $companySlug.'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        $company = Company::create([
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'name' => $companyName,
            'slug' => $companySlug,
            'is_active' => true,
        ]);

        return Product::create([
            'company_id' => $company->id,
            'name' => $name,
            'slug' => $slug,
            'price' => $price,
            'stock' => 10,
            'product_type' => 'simple',
            'status' => 'active',
        ]);
    }

    public function test_il_prodotto_di_un_altro_venditore_mette_in_attesa_il_carrello_invece_di_bloccare(): void
    {
        $this->post(route('cart.add', $this->rossi->slug), ['quantita' => 2])->assertSessionHasNoErrors();
        $this->post(route('cart.add', $this->bianchi->slug))->assertSessionHas('success')->assertSessionMissing('error');

        // In corso c'e' il carrello del secondo venditore, il primo aspetta intero.
        $this->assertSame([$this->bianchi->company_id], collect(session('cart'))->pluck('company_id')->unique()->all());
        $this->assertSame(2, session('cart_parked')[$this->rossi->company_id][$this->rossi->id]['quantity']);

        $this->get(route('cart.index'))->assertOk()->assertSee('Forno Bianchi')->assertSee('Caseificio Rossi');
    }

    public function test_riapre_il_carrello_in_attesa_senza_perdere_l_altro(): void
    {
        $this->post(route('cart.add', $this->rossi->slug), ['quantita' => 2]);
        $this->post(route('cart.add', $this->bianchi->slug));

        $this->post(route('cart.open', $this->rossi->company_id))->assertRedirect(route('cart.index'));

        $this->assertSame(2, session('cart')[$this->rossi->id]['quantity']);
        $this->assertSame(1, session('cart_parked')[$this->bianchi->company_id][$this->bianchi->id]['quantity']);
    }

    public function test_tornare_sul_venditore_gia_in_attesa_somma_le_quantita(): void
    {
        $this->post(route('cart.add', $this->rossi->slug), ['quantita' => 2]);
        $this->post(route('cart.add', $this->bianchi->slug));
        $this->post(route('cart.add', $this->rossi->slug), ['quantita' => 3]);

        $this->assertSame(5, session('cart')[$this->rossi->id]['quantity']);
        $this->assertSame(1, session('cart_parked')[$this->bianchi->company_id][$this->bianchi->id]['quantity']);
    }

    public function test_quantita_rifiutata_non_sposta_il_carrello_in_corso(): void
    {
        $this->post(route('cart.add', $this->rossi->slug));
        $this->post(route('cart.add', $this->bianchi->slug), ['quantita' => 99])->assertSessionHasErrors('quantita');

        $this->assertSame([$this->rossi->company_id], collect(session('cart'))->pluck('company_id')->unique()->all());
        $this->assertEmpty(session('cart_parked', []));
    }

    public function test_il_carrello_mostra_le_schede_dei_venditori_anche_se_quello_in_corso_e_vuoto(): void
    {
        $this->post(route('cart.add', $this->rossi->slug), ['quantita' => 2]);
        $this->post(route('cart.add', $this->bianchi->slug));

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Hai prodotti di 2 venditori')
            ->assertSee(route('cart.open', $this->rossi->company_id), false)
            // L'icona in testata conta i pezzi di tutti i carrelli.
            ->assertSee('Carrello (3)', false);

        // Come dopo un pagamento: il carrello in corso si svuota, l'altro resta.
        $this->delete(route('cart.clear'));

        $this->get(route('cart.index'))->assertOk()
            ->assertSee('Hai ancora prodotti salvati')
            ->assertSee('Caseificio Rossi')
            ->assertDontSee('Il carrello e vuoto');
    }

    public function test_elimina_solo_il_carrello_scelto(): void
    {
        $this->post(route('cart.add', $this->rossi->slug));
        $this->post(route('cart.add', $this->bianchi->slug));

        $this->delete(route('cart.discard', $this->rossi->company_id))->assertRedirect(route('cart.index'));

        $this->assertEmpty(session('cart_parked', []));
        $this->assertSame(1, session('cart')[$this->bianchi->id]['quantity']);
    }
}
