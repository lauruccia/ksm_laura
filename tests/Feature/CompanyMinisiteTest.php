<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Plan;
use App\Models\Review;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * La pagina dell'azienda come minisito: menu interno, sezioni,
 * modulo contatti e scheda per i motori di ricerca.
 */
class CompanyMinisiteTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $capabilities, array $attributes = []): Company
    {
        $plan = Plan::create([
            'name' => 'Piano '.uniqid(), 'slug' => 'piano-'.uniqid(), 'price' => 1200, 'priority' => 30,
            'duration_days' => 365, 'is_active' => true, 'capabilities' => $capabilities,
        ]);

        $owner = User::create([
            'name' => 'Titolare', 'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        $category = CompanyCategory::create(['name' => 'Ristoranti e pizzerie', 'slug' => 'ristoranti-'.uniqid()]);

        return Company::create($attributes + [
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'category_id' => $category->id,
            'name' => 'Trattoria da Mario',
            'slug' => 'trattoria-da-mario-'.uniqid(),
            'address' => 'Via Roma 12',
            'city' => 'Fondi',
            'region' => 'Lazio',
            'email' => 'info@trattoriadamario.test',
            'phone' => '+39 0771 502790',
            'website' => 'www.trattoriadamario.test',
            'company_description' => '<p>Cucina di mare dal 1968</p>',
            'working_hours' => ['Wednesday' => ['start' => '09:00', 'end' => '18:00']],
            'offer_gallery' => ['companies/1/galleria/sala.jpg'],
            'is_active' => true,
        ]);
    }

    private function full(): array
    {
        return [
            PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD, PlanCapabilities::SHOWCASE,
            PlanCapabilities::DESCRIPTION, PlanCapabilities::GALLERY, PlanCapabilities::REVIEWS,
        ];
    }

    public function test_il_minisito_ha_menu_interno_sezioni_e_contatti(): void
    {
        $company = $this->company($this->full());

        $this->get(route('companies.show', $company->slug))
            ->assertOk()
            ->assertSee('data-minisite-nav', false)
            ->assertSee('href="#chi-siamo"', false)
            ->assertSee('href="#galleria"', false)
            ->assertSee('href="#orari"', false)
            ->assertSee('href="#recensioni"', false)
            ->assertSee('href="#contatti"', false)
            ->assertSee('Cucina di mare dal 1968')
            ->assertSee('Orari di apertura')
            ->assertSee('09:00 – 18:00')
            ->assertSee('Ristoranti e pizzerie')
            ->assertSee('Fondi, Lazio')
            // Il sito arriva senza protocollo: il link lo aggiunge.
            ->assertSee('href="https://www.trattoriadamario.test"', false)
            ->assertSee(route('companies.contact', $company->slug), false);
    }

    public function test_la_scheda_per_i_motori_di_ricerca_riporta_orari_e_voto(): void
    {
        $company = $this->company($this->full());

        Review::create([
            'company_id' => $company->id, 'name' => 'Luca', 'rating' => 4,
            'comment' => 'Ottimo pesce', 'ip' => '127.0.0.1',
        ]);

        $page = $this->get(route('companies.show', $company->slug))->assertOk();

        $this->assertStringContainsString('"@type":"LocalBusiness"', $page->getContent());
        $this->assertStringContainsString('"opens":"09:00"', $page->getContent());
        $this->assertStringContainsString('"reviewCount":1', $page->getContent());
        $page->assertSee('Ottimo pesce');
    }

    public function test_aperto_e_chiuso_seguono_l_ora_italiana(): void
    {
        $company = $this->company($this->full());

        // Mercoledi 16 settembre 2026, ora italiana: dentro e fuori l'orario.
        $this->travelTo(Carbon::parse('2026-09-16 10:00', 'Europe/Rome'));
        $this->get(route('companies.show', $company->slug))->assertOk()->assertSee('Aperto adesso');

        $this->travelTo(Carbon::parse('2026-09-16 20:00', 'Europe/Rome'));
        $this->get(route('companies.show', $company->slug))->assertOk()->assertSee('Chiuso adesso');
    }

    public function test_il_modulo_scrive_all_azienda(): void
    {
        Mail::fake();

        $company = $this->company($this->full());

        $this->post(route('companies.contact', $company->slug), [
            'name' => 'Giulia',
            'email' => 'giulia@example.test',
            'message' => 'Avete posto sabato sera?',
        ])->assertRedirect()->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class);
    }

    public function test_se_la_posta_non_parte_il_testo_scritto_resta(): void
    {
        $company = $this->company($this->full());
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp giu'));

        $this->from(route('companies.show', $company->slug))->post(route('companies.contact', $company->slug), [
            'name' => 'Giulia',
            'email' => 'giulia@example.test',
            'message' => 'Avete posto sabato sera?',
        ])->assertRedirect(route('companies.show', $company->slug))
            ->assertSessionHasErrors('message')
            ->assertSessionHasInput('message', 'Avete posto sabato sera?');
    }

    public function test_senza_le_voci_del_piano_le_sezioni_non_compaiono(): void
    {
        $company = $this->company([PlanCapabilities::DIRECTORY, PlanCapabilities::SHOWCASE]);

        $this->get(route('companies.show', $company->slug))
            ->assertOk()
            // Niente scheda contatti: niente indirizzo, telefono e modulo.
            ->assertDontSee('Via Roma 12')
            ->assertDontSee('info@trattoriadamario.test')
            ->assertDontSee('href="#contatti"', false)
            ->assertDontSee('href="#galleria"', false)
            ->assertDontSee('href="#recensioni"', false);
    }
}
