<?php

namespace Tests\Feature;

use App\Models\AdminPaymentSetting;
use App\Models\AdminTransaction;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il percorso di chi si registra come azienda: profilo, piano, quota.
 *
 * Quello che conta e' che l'azienda resti invisibile finche' la quota
 * non risulta incassata.
 */
class SubscriptionFlowTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $attributes = []): Plan
    {
        return Plan::create($attributes + [
            'name' => 'Vetrina',
            'slug' => 'vetrina-'.uniqid(),
            'price' => 199,
            'duration_days' => 365,
            'priority' => 30,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD],
        ]);
    }

    private function vendor(): User
    {
        return User::create([
            'name' => 'Titolare',
            'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);
    }

    private function companyFor(User $user): Company
    {
        return Company::create([
            'user_id' => $user->id,
            'name' => 'Azienda di prova',
            'slug' => 'azienda-di-prova-'.uniqid(),
            'email' => $user->email,
            'is_active' => false,
        ]);
    }

    public function test_le_pagine_del_percorso_si_aprono(): void
    {
        $plan = $this->plan();
        $user = $this->vendor();
        $this->companyFor($user);

        $this->get(route('plans.index'))->assertOk()->assertSee($plan->name);
        $this->get(route('register.vendor', ['piano' => $plan->slug]))->assertOk()->assertSee($plan->name);

        $this->actingAs($user)->get(route('onboarding.create'))->assertRedirect(route('subscription.index'));
        $this->actingAs($user)->get(route('subscription.index'))->assertOk()->assertSee($plan->name);
    }

    public function test_l_area_azienda_rimanda_a_creare_il_profilo(): void
    {
        $this->actingAs($this->vendor())
            ->get(route('vendor.dashboard'))
            ->assertRedirect(route('onboarding.create'));
    }

    public function test_senza_piano_attivo_l_area_azienda_rimanda_all_abbonamento(): void
    {
        $user = $this->vendor();
        $this->companyFor($user);

        $this->actingAs($user)
            ->get(route('vendor.dashboard'))
            ->assertRedirect(route('subscription.index'));
    }

    public function test_il_profilo_nasce_spento_e_senza_piano(): void
    {
        $user = $this->vendor();

        $this->actingAs($user)
            ->post(route('onboarding.store'), ['name' => 'Panificio Rossi'])
            ->assertRedirect(route('subscription.index'));

        $company = $user->fresh()->company;

        $this->assertSame('panificio-rossi', $company->slug);
        $this->assertFalse($company->is_active);
        $this->assertNull($company->plan_id);
    }

    public function test_scegliere_un_piano_a_pagamento_non_attiva_niente(): void
    {
        $user = $this->vendor();
        $company = $this->companyFor($user);
        $plan = $this->plan();

        $this->actingAs($user)->post(route('subscription.store'), ['plan_id' => $plan->id]);

        $subscription = CompanySubscription::firstOrFail();

        $this->assertSame(CompanySubscription::PENDING, $subscription->status);
        $this->assertFalse($company->fresh()->is_active);
        $this->assertNull($company->fresh()->plan_id);
    }

    public function test_un_piano_gratuito_parte_subito(): void
    {
        $user = $this->vendor();
        $company = $this->companyFor($user);
        $plan = $this->plan(['price' => 0]);

        $this->actingAs($user)->post(route('subscription.store'), ['plan_id' => $plan->id]);

        $subscription = CompanySubscription::firstOrFail();

        $this->assertSame(CompanySubscription::ACTIVE, $subscription->status);
        $this->assertTrue($company->fresh()->is_active);
        $this->assertSame($plan->id, $company->fresh()->plan_id);
    }

    public function test_il_bonifico_mostra_le_coordinate_e_lascia_tutto_in_attesa(): void
    {
        AdminPaymentSetting::create([
            'mode' => 'test',
            'enable_bank_transfer' => true,
            'bank_holder' => 'Circuito KSM',
            'bank_iban' => 'IT60X0542811101000000123456',
            'is_active' => true,
        ]);

        $user = $this->vendor();
        $company = $this->companyFor($user);
        $plan = $this->plan();

        $this->actingAs($user)->post(route('subscription.store'), ['plan_id' => $plan->id]);
        $subscription = CompanySubscription::firstOrFail();

        $this->actingAs($user)
            ->post(route('subscription.pay', $subscription), ['method' => 'bank_transfer'])
            ->assertRedirect(route('subscription.bank', $subscription));

        $this->actingAs($user)
            ->get(route('subscription.bank', $subscription))
            ->assertOk()
            ->assertSee('IT60X0542811101000000123456');

        $this->assertSame(CompanySubscription::PENDING, $subscription->fresh()->status);
        $this->assertFalse($company->fresh()->is_active);
        $this->assertSame('pending', AdminTransaction::firstOrFail()->status);
    }

    public function test_la_conferma_in_amministrazione_attiva_il_piano(): void
    {
        $user = $this->vendor();
        $company = $this->companyFor($user);
        $plan = $this->plan();

        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => CompanySubscription::PENDING,
            'payment_method' => 'bank_transfer',
            'price' => $plan->price,
            'currency' => 'EUR',
        ]);

        AdminTransaction::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'subscription_id' => $subscription->id,
            'payment_method' => 'bank_transfer',
            'amount' => $plan->price,
            'currency' => 'EUR',
            'status' => 'pending',
        ]);

        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );

        $admin = User::create([
            'name' => 'Amministratore',
            'email' => 'admin'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'admin',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.subscriptions.index'))
            ->assertOk()
            ->assertSee($company->name);

        $this->actingAs($admin)
            ->patch(route('admin.subscriptions.confirm', $subscription))
            ->assertRedirect();

        $subscription->refresh();
        $company->refresh();

        $this->assertSame(CompanySubscription::ACTIVE, $subscription->status);
        $this->assertNotNull($subscription->ends_at);
        $this->assertTrue($company->is_active);
        $this->assertSame($plan->id, $company->plan_id);
        $this->assertSame('completed', AdminTransaction::firstOrFail()->status);
    }

    public function test_la_scadenza_spegne_l_azienda_senza_cancellare_niente(): void
    {
        $user = $this->vendor();
        $company = $this->companyFor($user);
        $plan = $this->plan();

        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => CompanySubscription::ACTIVE,
            'price' => $plan->price,
            'currency' => 'EUR',
            'starts_at' => now()->subYear()->subDay(),
            'ends_at' => now()->subDay(),
        ]);

        $company->update(['plan_id' => $plan->id, 'is_active' => true]);

        $this->artisan('subscriptions:renewals')->assertSuccessful();

        $this->assertSame(CompanySubscription::EXPIRED, $subscription->fresh()->status);
        $this->assertNull($company->fresh()->plan_id);
        $this->assertFalse($company->fresh()->is_active);
        // I dati restano: basta un rinnovo pagato per riaccendere tutto.
        $this->assertSame('Azienda di prova', $company->fresh()->name);
    }

    public function test_nessuno_puo_pagare_l_abbonamento_di_un_altra_azienda(): void
    {
        $owner = $this->vendor();
        $company = $this->companyFor($owner);
        $plan = $this->plan();

        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => CompanySubscription::PENDING,
            'price' => $plan->price,
            'currency' => 'EUR',
        ]);

        $intruder = $this->vendor();
        $this->companyFor($intruder);

        $this->actingAs($intruder)
            ->get(route('subscription.payment', $subscription))
            ->assertForbidden();
    }
}
