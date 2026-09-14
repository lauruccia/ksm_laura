<?php

namespace Tests\Feature;

use App\Mail\SubscriptionExpiring;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Plan;
use App\Models\Role;
use App\Models\User;
use App\Payments\Subscriptions\SubscriptionPricing;
use App\Payments\Subscriptions\SubscriptionQuote;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Rinnovo, promemoria e cambio di piano a meta' periodo.
 */
class SubscriptionRenewalTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $name, float $price, int $priority = 10): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'price' => $price,
            'priority' => $priority,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD],
        ]);
    }

    private function company(): Company
    {
        $user = User::create([
            'name' => 'Titolare',
            'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        return Company::create([
            'user_id' => $user->id,
            'name' => 'Azienda di prova',
            'slug' => 'azienda-'.uniqid(),
            'email' => 'azienda'.uniqid().'@example.test',
            'is_active' => false,
        ]);
    }

    private function activeSince(Company $company, Plan $plan, int $daysAgo): CompanySubscription
    {
        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => CompanySubscription::ACTIVE,
            'price' => $plan->price,
            'currency' => 'EUR',
            'starts_at' => now()->subDays($daysAgo),
            'ends_at' => now()->subDays($daysAgo)->addDays((int) $plan->duration_days),
        ]);

        $company->update(['plan_id' => $plan->id, 'is_active' => true]);

        return $subscription;
    }

    public function test_il_primo_piano_costa_la_quota_intera(): void
    {
        $company = $this->company();
        $plan = $this->plan('Vetrina', 199);

        $quote = (new SubscriptionPricing())->quote($company, $plan);

        $this->assertSame(SubscriptionQuote::NEW, $quote->kind);
        $this->assertSame(199.0, $quote->amount);
        $this->assertSame(0.0, $quote->credit);
    }

    public function test_il_rinnovo_si_attacca_alla_scadenza_attuale(): void
    {
        $company = $this->company();
        $plan = $this->plan('Vetrina', 199);
        $current = $this->activeSince($company, $plan, 300);

        $quote = (new SubscriptionPricing())->quote($company->fresh(), $plan);

        $this->assertSame(SubscriptionQuote::RENEWAL, $quote->kind);
        $this->assertSame(199.0, $quote->amount);
        // Chi rinnova in anticipo non perde i giorni che gli restano.
        $this->assertSame(
            $current->ends_at->copy()->addDays(365)->toDateString(),
            $quote->endsAt->toDateString()
        );
    }

    public function test_l_upgrade_a_meta_periodo_fa_pagare_la_differenza(): void
    {
        $company = $this->company();
        $vetrina = $this->plan('Vetrina', 200, 30);
        $ecommerce = $this->plan('Ecommerce', 400, 40);

        // Meta' periodo esatta: resta da consumare meta' anno.
        $current = $this->activeSince($company, $vetrina, 182);

        $quote = (new SubscriptionPricing())->quote($company->fresh(), $ecommerce);

        $this->assertSame(SubscriptionQuote::CHANGE, $quote->kind);
        // Meta' di 400 meno meta' di 200, cioe' la differenza sul residuo.
        $this->assertEqualsWithDelta(100, $quote->amount, 3);
        $this->assertEqualsWithDelta(100, $quote->credit, 3);
        // La scadenza non si sposta.
        $this->assertSame($current->ends_at->toDateString(), $quote->endsAt->toDateString());
    }

    public function test_passare_a_un_piano_piu_economico_non_fa_pagare_niente(): void
    {
        $company = $this->company();
        $ecommerce = $this->plan('Ecommerce', 400, 40);
        $anagrafica = $this->plan('Anagrafica', 20, 10);

        $this->activeSince($company, $ecommerce, 182);

        $quote = (new SubscriptionPricing())->quote($company->fresh(), $anagrafica);

        $this->assertSame(0.0, $quote->amount);
        $this->assertTrue($quote->isFree());
    }

    public function test_il_cambio_senza_differenza_da_pagare_parte_subito(): void
    {
        $company = $this->company();
        $ecommerce = $this->plan('Ecommerce', 400, 40);
        $anagrafica = $this->plan('Anagrafica', 20, 10);

        $current = $this->activeSince($company, $ecommerce, 182);

        $this->actingAs($company->user)
            ->post(route('subscription.store'), ['plan_id' => $anagrafica->id])
            ->assertRedirect();

        $company->refresh();

        $this->assertSame($anagrafica->id, $company->plan_id);
        $this->assertSame(CompanySubscription::CANCELLED, $current->fresh()->status);
        $this->assertSame(
            $current->ends_at->toDateString(),
            $company->activeSubscription()->ends_at->toDateString()
        );
    }

    public function test_i_promemoria_partono_una_tappa_alla_volta(): void
    {
        Mail::fake();

        $company = $this->company();
        $plan = $this->plan('Vetrina', 199);
        $subscription = $this->activeSince($company, $plan, 365 - 20);

        // Venti giorni alla scadenza: scatta solo la tappa dei trenta.
        $this->artisan('subscriptions:renewals')->assertSuccessful();
        Mail::assertSent(SubscriptionExpiring::class, 1);
        $this->assertTrue($subscription->fresh()->reminderSent(30));
        $this->assertFalse($subscription->fresh()->reminderSent(15));

        // Rilanciato lo stesso giorno non manda niente.
        $this->artisan('subscriptions:renewals')->assertSuccessful();
        Mail::assertSent(SubscriptionExpiring::class, 1);
    }

    public function test_a_scadenza_l_azienda_si_spegne_e_riceve_l_avviso(): void
    {
        Mail::fake();

        $company = $this->company();
        $plan = $this->plan('Vetrina', 199);
        $subscription = $this->activeSince($company, $plan, 366);

        $this->artisan('subscriptions:renewals')->assertSuccessful();

        $this->assertSame(CompanySubscription::EXPIRED, $subscription->fresh()->status);
        $this->assertFalse($company->fresh()->is_active);

        Mail::assertSent(SubscriptionExpiring::class, fn ($mail) => $mail->daysLeft <= 0);
    }

    public function test_l_amministratore_attiva_un_piano_a_un_azienda_qualsiasi(): void
    {
        $company = $this->company();
        $plan = $this->plan('Vetrina', 199);

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
            ->post(route('admin.subscriptions.store'), [
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'notes' => 'Accordo commerciale',
            ])
            ->assertRedirect();

        $company->refresh();
        $subscription = $company->activeSubscription();

        $this->assertTrue($company->is_active);
        $this->assertSame($plan->id, $company->plan_id);
        $this->assertSame('admin', $subscription->payment_method);
        $this->assertSame('Accordo commerciale', $subscription->notes);
        // Nessun incasso inventato: il movimento non esiste.
        $this->assertSame(0, $subscription->transactions()->count());
    }
}
