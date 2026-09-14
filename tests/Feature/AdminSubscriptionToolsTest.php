<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Payments\Subscriptions\SubscriptionActivator;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Gli strumenti di Abbonamenti pensati per centomila aziende.
 *
 * Promemoria spegnibili, rinnovo in blocco con un solo UPDATE, e la
 * scelta dell'azienda per ricerca invece che da un elenco completo.
 */
class AdminSubscriptionToolsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
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

    private function plan(string $name, ?int $duration): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'price' => 240,
            'priority' => 10,
            'duration_days' => $duration,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY],
        ]);
    }

    private function company(string $name = 'Azienda di prova', ?string $city = null): Company
    {
        $user = User::create([
            'name' => 'Titolare',
            'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $user->id,
            'name' => $name,
            'city' => $city,
            'slug' => 'azienda-'.uniqid(),
            'email' => 'azienda'.uniqid().'@example.test',
            'is_active' => false,
        ]);
    }

    public function test_senza_promemoria_non_parte_nessuna_email_ma_la_scadenza_si(): void
    {
        Mail::fake();
        $company = $this->company();
        $subscription = app(SubscriptionActivator::class)->assign($company, $this->plan('Anagrafica', 365));
        $subscription->update(['send_reminders' => false]);

        $this->travelTo($subscription->ends_at->copy()->subDays(10));
        $this->artisan('subscriptions:renewals')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(CompanySubscription::ACTIVE, $subscription->fresh()->status);

        $this->travelTo($subscription->ends_at->copy()->addDay());
        $this->artisan('subscriptions:renewals')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(CompanySubscription::EXPIRED, $subscription->fresh()->status);
        $this->assertFalse($company->fresh()->is_active);
    }

    public function test_rinnovando_restano_spenti_i_promemoria(): void
    {
        $company = $this->company();
        $plan = $this->plan('Anagrafica', 365);
        app(SubscriptionActivator::class)->assign($company, $plan)->update(['send_reminders' => false]);

        $renewal = app(SubscriptionActivator::class)->assign($company->fresh(), $plan);

        $this->assertFalse($renewal->fresh()->send_reminders);
    }

    public function test_il_rinnovo_in_blocco_allunga_solo_gli_abbonamenti_scelti(): void
    {
        $activator = app(SubscriptionActivator::class);
        $anagrafica = $this->plan('Anagrafica', 365);

        $vicina = $activator->assign($this->company(), $anagrafica);
        $vicina->update(['ends_at' => now()->addDays(20), 'reminders_sent' => [30]]);

        $lontana = $activator->assign($this->company(), $anagrafica);
        $lontana->update(['ends_at' => now()->addDays(200)]);

        $senzaScadenza = $activator->assign($this->company(), $this->plan('Vetrina', null));

        $this->actingAs($this->admin())
            ->post(route('admin.subscriptions.extend'), [
                'extend_plan_id' => $anagrafica->id,
                'extend_ending_before' => now()->addDays(60)->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $vicina->ends_at->copy()->addDays(365)->toDateTimeString(),
            $vicina->fresh()->ends_at->toDateTimeString()
        );
        $this->assertNull($vicina->fresh()->reminders_sent);
        $this->assertSame($lontana->ends_at->toDateTimeString(), $lontana->fresh()->ends_at->toDateTimeString());
        $this->assertNull($senzaScadenza->fresh()->ends_at);
    }

    public function test_i_piani_senza_scadenza_non_si_rinnovano_in_blocco(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.subscriptions.extend'), ['extend_plan_id' => $this->plan('Vetrina', null)->id])
            ->assertSessionHasErrors('extend_plan_id');
    }

    public function test_la_pagina_non_elenca_tutte_le_aziende(): void
    {
        $this->company('Grano Salis');

        $this->actingAs($this->admin())
            ->get(route('admin.subscriptions.index'))
            ->assertOk()
            ->assertDontSee('Grano Salis');
    }

    public function test_la_ricerca_delle_aziende_restituisce_l_id_nell_etichetta(): void
    {
        $company = $this->company('Grano Salis', 'Roma');
        $this->company('Calabria Sapori');

        $this->actingAs($this->admin())
            ->getJson(route('admin.subscriptions.companies', ['q' => 'grano']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $company->id, 'label' => "Grano Salis · Roma · #$company->id"]);
    }

    public function test_si_attiva_un_piano_scegliendo_l_azienda_dalla_ricerca(): void
    {
        $company = $this->company('Grano Salis', 'Roma');
        $plan = $this->plan('Anagrafica', 365);

        $this->actingAs($this->admin())
            ->post(route('admin.subscriptions.store'), [
                'company' => "Grano Salis · Roma · #$company->id",
                'plan_id' => $plan->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals($plan->id, $company->fresh()->plan_id);
    }

    public function test_i_promemoria_si_spengono_da_amministrazione(): void
    {
        $subscription = app(SubscriptionActivator::class)->assign($this->company(), $this->plan('Anagrafica', 365));

        $this->actingAs($this->admin())
            ->patch(route('admin.subscriptions.reminders', $subscription))
            ->assertRedirect();

        $this->assertFalse($subscription->fresh()->send_reminders);
    }
}
