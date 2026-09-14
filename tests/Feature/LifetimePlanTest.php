<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Plan;
use App\Models\User;
use App\Payments\Subscriptions\SubscriptionActivator;
use App\Payments\Subscriptions\SubscriptionPricing;
use App\Payments\Subscriptions\SubscriptionQuote;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Piani che non scadono: Ecommerce, Vetrina e Biglietto.
 *
 * Una volta attivi restano attivi, il giro dei rinnovi non li tocca, e
 * nei cambi di piano valgono per l'intera quota.
 */
class LifetimePlanTest extends TestCase
{
    use RefreshDatabase;

    private function plan(string $name, float $price, ?int $duration): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'price' => $price,
            'priority' => 10,
            'duration_days' => $duration,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY],
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

    public function test_un_piano_senza_durata_attivato_non_scade(): void
    {
        $company = $this->company();

        $subscription = app(SubscriptionActivator::class)
            ->assign($company, $this->plan('Vetrina', 1200, null))
            ->fresh();

        $this->assertNull($subscription->ends_at);
        $this->assertTrue($subscription->isActive());
        $this->assertTrue($company->fresh()->is_active);
    }

    public function test_il_giro_dei_rinnovi_non_tocca_i_piani_senza_scadenza(): void
    {
        Mail::fake();
        $subscription = app(SubscriptionActivator::class)
            ->assign($this->company(), $this->plan('Vetrina', 1200, null));

        $this->travel(5)->years();
        $this->artisan('subscriptions:renewals')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertSame(CompanySubscription::ACTIVE, $subscription->fresh()->status);
    }

    public function test_da_annuale_a_senza_scadenza_si_paga_la_quota_meno_il_residuo(): void
    {
        $company = $this->company();
        app(SubscriptionActivator::class)->assign($company, $this->plan('Anagrafica', 240, 365));

        $quote = app(SubscriptionPricing::class)->quote($company->fresh(), $this->plan('Vetrina', 1200, null));

        $this->assertSame(SubscriptionQuote::CHANGE, $quote->kind);
        $this->assertNull($quote->endsAt);
        // Appena attivato, il residuo e' quasi tutta la quota annuale.
        $this->assertEqualsWithDelta(960, $quote->amount, 1.0);
    }

    public function test_lasciare_un_piano_senza_scadenza_sconta_tutta_la_quota(): void
    {
        $company = $this->company();
        app(SubscriptionActivator::class)->assign($company, $this->plan('Vetrina', 1200, null));
        $company->refresh();

        $pricing = app(SubscriptionPricing::class);
        $toEcommerce = $pricing->quote($company, $this->plan('Ecommerce', 2400, null));
        $toAnagrafica = $pricing->quote($company, $this->plan('Anagrafica', 240, 365));

        $this->assertEquals(1200, $toEcommerce->amount);
        $this->assertNull($toEcommerce->endsAt);

        $this->assertEquals(0, $toAnagrafica->amount);
        $this->assertTrue($toAnagrafica->endsAt->isFuture());
    }

    public function test_rinnovare_un_piano_senza_scadenza_non_costa_niente(): void
    {
        $company = $this->company();
        $plan = $this->plan('Biglietto', 600, null);
        app(SubscriptionActivator::class)->assign($company, $plan);

        $quote = app(SubscriptionPricing::class)->quote($company->fresh(), $plan);

        $this->assertSame(SubscriptionQuote::RENEWAL, $quote->kind);
        $this->assertTrue($quote->isFree());
    }
}
