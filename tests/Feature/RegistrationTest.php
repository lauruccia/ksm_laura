<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le due registrazioni: il privato non vede piani, l'azienda si.
 * Il tipo di account lo decide la rotta, non il modulo.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Vetrina', 'slug' => 'vetrina', 'price' => 199,
            'duration_days' => 365, 'is_active' => true,
        ]);
    }

    private function account(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Mario Rossi',
            'email' => 'mario@example.test',
            'password' => 'PasswordProva123',
            'password_confirmation' => 'PasswordProva123',
        ];
    }

    public function test_le_pagine_di_accesso_e_registrazione_si_aprono(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Sono un privato')->assertSee("Sono un'azienda", false);
        $this->get(route('register'))->assertOk()->assertSee(route('register.buyer'))->assertSee(route('register.vendor'));
    }

    public function test_il_privato_non_vede_i_piani(): void
    {
        $plan = $this->plan();

        $this->get(route('register.buyer'))
            ->assertOk()
            ->assertDontSee($plan->name)
            ->assertDontSee('name="piano"', false)
            ->assertDontSee('name="user_type"', false);
    }

    public function test_l_azienda_sceglie_il_piano(): void
    {
        $plan = $this->plan();

        $response = $this->get(route('register.vendor', ['piano' => $plan->slug]))
            ->assertOk()
            ->assertSee($plan->name);

        // Il piano arrivato dal link e' gia' spuntato.
        $this->assertMatchesRegularExpression('/value="vetrina"\s+checked/', $response->getContent());
    }

    public function test_il_vecchio_link_con_il_piano_porta_alla_registrazione_azienda(): void
    {
        $this->get(route('register', ['piano' => 'vetrina']))
            ->assertRedirect(route('register.vendor', ['piano' => 'vetrina']));
    }

    public function test_il_privato_resta_acquirente_anche_se_il_modulo_dice_altro(): void
    {
        $this->plan();

        $this->post(route('register.buyer.store'), $this->account(['user_type' => 'vendor', 'piano' => 'vetrina']))
            ->assertRedirect(route('verification.show'));

        $user = User::where('email', 'mario@example.test')->firstOrFail();
        $this->assertSame('buyer', $user->user_type);
        $this->assertNull(session('piano_scelto'));
    }

    public function test_l_azienda_nasce_venditore_con_il_piano_scelto(): void
    {
        $this->plan();

        $this->post(route('register.vendor.store'), $this->account(['user_type' => 'buyer', 'piano' => 'vetrina']))
            ->assertRedirect(route('verification.show'))
            ->assertSessionHas('piano_scelto', 'vetrina');

        $this->assertSame('vendor', User::where('email', 'mario@example.test')->value('user_type'));
        $this->assertAuthenticated();
    }

    public function test_piano_inesistente_rifiutato(): void
    {
        $this->from(route('register.vendor'))
            ->post(route('register.vendor.store'), $this->account(['piano' => 'non-esiste']))
            ->assertRedirect(route('register.vendor'))
            ->assertSessionHasErrors('piano');

        $this->assertGuest();
    }
}
