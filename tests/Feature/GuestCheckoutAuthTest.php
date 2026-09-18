<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyPaymentSetting;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Accesso e registrazione dentro l'acquisto.
 *
 * Chi arriva al carrello senza account non viene mandato via: il modulo
 * sta nella pagina e, appena fatto, si prosegue dalla cassa con il carrello
 * ancora pieno.
 */
class GuestCheckoutAuthTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $vendor = User::create([
            'name' => 'Venditore', 'email' => 'venditore@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        $this->company = Company::create([
            'user_id' => $vendor->id, 'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true,
        ]);

        CompanyPaymentSetting::create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'enable_stripe' => true,
            'stripe_test_secret_key' => 'sk_test_x',
        ]);

        $this->product = Product::create([
            'company_id' => $this->company->id, 'name' => 'Nduja', 'slug' => 'nduja',
            'price' => 20, 'stock' => 5, 'status' => 'active', 'kmoney_discount_percent' => 0,
        ]);
    }

    private function withCart(): self
    {
        $this->withSession(['cart' => [
            (string) $this->product->id => [
                'product_id' => $this->product->id, 'company_id' => $this->company->id,
                'name' => $this->product->name, 'slug' => $this->product->slug,
                'image' => null, 'price' => 20, 'quantity' => 1,
            ],
        ]]);

        return $this;
    }

    private function buyer(): User
    {
        return User::create([
            'name' => 'Acquirente', 'email' => 'acquirente@example.test',
            'password' => 'password', 'user_type' => 'buyer',
        ]);
    }

    public function test_il_carrello_propone_registrazione_e_accesso_agli_ospiti(): void
    {
        $this->withCart()->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Registrati e prosegui')
            ->assertSee('Ho già un account');
    }

    public function test_la_cassa_si_apre_da_ospiti_con_il_modulo_al_posto_della_fatturazione(): void
    {
        $this->withCart()->get(route('checkout.show'))
            ->assertOk()
            ->assertSee('Crea account e continua')
            ->assertSee('Riepilogo')
            ->assertSee('Nduja')
            ->assertDontSee('Paga ora');
    }

    public function test_la_scheda_accesso_mostra_il_modulo_di_accesso(): void
    {
        $this->withCart()->get(route('checkout.show', ['modulo' => 'accesso']))
            ->assertOk()
            ->assertSee('Accedi e continua')
            ->assertDontSee('Crea account e continua');
    }

    public function test_la_registrazione_dal_carrello_porta_alla_cassa_con_il_carrello_intatto(): void
    {
        $this->withCart()->post(route('register.buyer.store'), [
            'ritorno' => 'pagamento',
            'name' => 'Mario Rossi',
            'email' => 'mario@example.test',
            'password' => 'PasswordProva123',
            'password_confirmation' => 'PasswordProva123',
        ])->assertRedirect(route('checkout.show'));

        $this->assertTrue(Auth::check());
        $this->assertSame('mario@example.test', Auth::user()->email);

        // Il carrello e' in sessione e sopravvive alla registrazione.
        $this->get(route('checkout.show'))->assertOk()->assertSee('Paga ora');
    }

    public function test_l_accesso_dal_carrello_porta_alla_cassa(): void
    {
        $buyer = $this->buyer();

        $this->withCart()->post(route('login.store'), [
            'ritorno' => 'pagamento',
            'email' => $buyer->email,
            'password' => 'password',
        ])->assertRedirect(route('checkout.show'));

        $this->assertAuthenticatedAs($buyer);
    }

    public function test_credenziali_sbagliate_tornano_al_carrello_con_l_errore_nella_sacca_dell_accesso(): void
    {
        $this->buyer();

        $this->withCart()->from(route('cart.index'))
            ->post(route('login.store'), [
                'ritorno' => 'pagamento',
                'email' => 'acquirente@example.test',
                'password' => 'sbagliata',
            ])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHasErrors(['email'], null, 'accesso');

        $this->assertGuest();
    }

    public function test_il_ritorno_accetta_solo_destinazioni_note(): void
    {
        $buyer = $this->buyer();

        // Un indirizzo scritto nel modulo non sposta nessuno fuori dal sito.
        $this->withCart()->post(route('login.store'), [
            'ritorno' => 'https://esterno.test/phishing',
            'email' => $buyer->email,
            'password' => 'password',
        ])->assertRedirect(route('home'));
    }

    public function test_senza_ritorno_la_registrazione_resta_quella_di_sempre(): void
    {
        $this->post(route('register.buyer.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.test',
            'password' => 'PasswordProva123',
            'password_confirmation' => 'PasswordProva123',
        ])->assertRedirect(route('verification.show'));
    }
}
