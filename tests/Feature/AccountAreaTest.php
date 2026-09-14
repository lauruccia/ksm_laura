<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'area di chi acquista.
 *
 * La cosa da provare davvero e' che gli ordini di uno non si aprano
 * conoscendone l'indirizzo.
 */
class AccountAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_pagine_dell_account_si_aprono(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('account.orders.index'))->assertOk();
        $this->actingAs($user)->get(route('account.profile.edit'))->assertOk();
    }

    public function test_senza_accesso_si_finisce_alla_pagina_di_accesso(): void
    {
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
    }

    public function test_ognuno_vede_solo_i_propri_ordini(): void
    {
        $mine = $this->orderFor(User::factory()->create());
        $theirs = $this->orderFor(User::factory()->create());

        $this->actingAs($mine->user)
            ->get(route('account.orders.show', $mine))
            ->assertOk()
            ->assertSee($mine->reference);

        $this->actingAs($mine->user)
            ->get(route('account.orders.show', $theirs))
            ->assertNotFound();

        $this->actingAs($mine->user)
            ->get(route('account.orders.index'))
            ->assertDontSee($theirs->reference);
    }

    public function test_l_indirizzo_salvato_torna_in_cassa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.profile.update'), [
            'name' => 'Anna Rossi',
            'email' => $user->email,
            'billing_address' => 'Via Roma 1',
            'billing_city' => 'Torino',
            'billing_zip' => '10100',
            'billing_country' => 'Italia',
        ])->assertRedirect();

        $defaults = $user->fresh()->billingDefaults();

        $this->assertSame('Via Roma 1', $defaults['billing_address']);
        $this->assertSame('Torino', $defaults['billing_city']);
        $this->assertSame('Anna Rossi', $defaults['billing_name']);
    }

    public function test_la_password_non_cambia_senza_quella_attuale(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('account.profile.password'), [
            'current_password' => 'sbagliata',
            'password' => 'una-password-lunga',
            'password_confirmation' => 'una-password-lunga',
        ])->assertSessionHasErrors('current_password');
    }

    private function orderFor(User $user): Order
    {
        $company = Company::create([
            'user_id' => User::factory()->create(['user_type' => 'vendor'])->id,
            'name' => 'Azienda '.$user->id,
            'slug' => 'azienda-'.$user->id,
            'city' => 'Roma',
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'subtotal' => 10,
            'total' => 10,
            'currency' => 'EUR',
            'status' => 'paid',
            'billing_name' => $user->name,
            'billing_email' => $user->email,
        ]);

        return $order->setRelation('user', $user);
    }
}
