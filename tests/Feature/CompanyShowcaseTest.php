<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Support\PlanCapabilities;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La scheda pubblica di un'azienda: descrizione, orari e galleria.
 */
class CompanyShowcaseTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $capabilities, array $attributes = []): Company
    {
        $plan = Plan::create([
            'name' => 'Piano '.uniqid(), 'slug' => 'piano-'.uniqid(), 'price' => 1200, 'priority' => 10,
            'duration_days' => null, 'is_active' => true, 'capabilities' => $capabilities,
        ]);

        $owner = User::create([
            'name' => 'Titolare', 'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password', 'user_type' => 'vendor',
        ]);

        return Company::create($attributes + [
            'user_id' => $owner->id,
            'plan_id' => $plan->id,
            'name' => 'Decina Bus',
            'slug' => 'decina-bus-'.uniqid(),
            'is_active' => true,
            'company_description' => '<p>Noleggio <strong>bus</strong> dal 1968<script>alert("x")</script></p>',
            'working_hours' => ['Monday' => ['start' => '09:00', 'end' => '18:00']],
            'offer_gallery' => ['companies/1/galleria/bus.jpg'],
        ]);
    }

    public function test_la_vetrina_mostra_descrizione_formattata_orari_e_galleria(): void
    {
        $company = $this->company([
            PlanCapabilities::DIRECTORY, PlanCapabilities::SHOWCASE,
            PlanCapabilities::DESCRIPTION, PlanCapabilities::GALLERY,
        ]);

        $this->get(route('companies.show', $company->slug))
            ->assertOk()
            ->assertSee('<strong>bus</strong>', false)
            ->assertDontSee('alert("x")', false)
            ->assertSee('Orari di apertura')
            ->assertSee('09:00 – 18:00')
            ->assertSee('Chiuso')
            ->assertSee('storage/companies/1/galleria/bus.jpg', false);
    }

    public function test_senza_le_voci_del_piano_restano_nascoste(): void
    {
        $company = $this->company([PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD]);

        $this->get(route('companies.show', $company->slug))
            ->assertOk()
            ->assertDontSee('Orari di apertura')
            ->assertDontSee('galleria/bus.jpg', false)
            ->assertDontSee('dal 1968');
    }

    public function test_il_testo_semplice_va_a_capo_e_resta_testo(): void
    {
        $this->assertSame(
            'Noleggio bus<br>'."\n".'Prezzi &lt; 20 &amp; sconti',
            (string) RichText::render("Noleggio bus\nPrezzi < 20 & sconti")
        );
    }

    public function test_dall_html_restano_solo_formattazione_e_collegamenti_sicuri(): void
    {
        $html = (string) RichText::render(
            '<div style="color:red"><p onclick="x()">Ciao <em>tutti</em></p>'
            .'<a href="javascript:alert(1)">trappola</a> <a href="https://decinabus.it">sito</a>'
            .'<img src="https://esterno.test/x.png"><iframe src="https://esterno.test"></iframe></div>'
        );

        $this->assertStringContainsString('<p>Ciao <em>tutti</em></p>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringContainsString('href="https://decinabus.it"', $html);
        $this->assertStringContainsString('rel="noopener nofollow"', $html);
    }
}
