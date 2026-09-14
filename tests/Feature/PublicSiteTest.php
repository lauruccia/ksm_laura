<?php

namespace Tests\Feature;

use App\Models\AdminSetting;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Parti pubbliche del sito: ricerca della home, piede con i dati del
 * gestore, mappa del sito, robots e pagine di errore.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private ?Plan $plan = null;

    private function listedCompany(string $name, array $attributes = []): Company
    {
        $this->plan ??= Plan::create([
            'name' => 'Anagrafica',
            'slug' => 'anagrafica',
            'price' => 19,
            'priority' => 10,
            'duration_days' => 365,
            'is_active' => true,
            'capabilities' => [PlanCapabilities::DIRECTORY, PlanCapabilities::CONTACT_CARD],
        ]);

        $slug = str($name)->slug()->toString();

        $user = User::create([
            'name' => $name,
            'email' => $slug.'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        return Company::create(array_merge([
            'user_id' => $user->id,
            'plan_id' => $this->plan->id,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ], $attributes));
    }

    public function test_la_ricerca_per_nome_risponde_e_filtra(): void
    {
        $this->listedCompany('Caseificio Rossi');
        $this->listedCompany('Falegnameria Bianchi');

        // Prima la colonna `name` era ambigua con quella dei piani.
        $this->get(route('companies.index', ['cerca' => 'Caseificio']))
            ->assertOk()
            ->assertSee('Caseificio Rossi')
            ->assertDontSee('Falegnameria Bianchi');
    }

    public function test_la_ricerca_trova_anche_per_settore_e_per_prodotto(): void
    {
        $food = CompanyCategory::create(['name' => 'Alimentari', 'slug' => 'alimentari']);
        $this->listedCompany('Caseificio Rossi', ['category_id' => $food->id]);

        $shop = $this->listedCompany('Bottega Verdi');
        Product::create([
            'company_id' => $shop->id,
            'name' => 'Mozzarella di bufala',
            'slug' => 'mozzarella-di-bufala',
            'price' => 5,
            'stock' => 3,
            'status' => 'active',
        ]);

        $this->listedCompany('Falegnameria Bianchi');

        $this->get(route('companies.index', ['cerca' => 'Alimentari']))
            ->assertOk()
            ->assertSee('Caseificio Rossi')
            ->assertDontSee('Falegnameria Bianchi');

        $this->get(route('companies.index', ['cerca' => 'mozzarella']))
            ->assertOk()
            ->assertSee('Bottega Verdi')
            ->assertDontSee('Falegnameria Bianchi');
    }

    public function test_il_filtro_regione_mostra_solo_quella_regione(): void
    {
        $this->listedCompany('Caseificio Rossi', ['city' => 'Roma', 'region' => 'Lazio']);
        $this->listedCompany('Falegnameria Bianchi', ['city' => 'Milano', 'region' => 'Lombardia']);

        $this->get(route('companies.index', ['regione' => 'Lazio']))
            ->assertOk()
            ->assertSee('Caseificio Rossi')
            ->assertDontSee('Falegnameria Bianchi');
    }

    public function test_una_regione_sconosciuta_viene_ignorata(): void
    {
        $this->listedCompany('Caseificio Rossi', ['region' => 'Lazio']);
        $this->listedCompany('Falegnameria Bianchi', ['region' => 'Lombardia']);

        $this->get(route('companies.index', ['regione' => 'Atlantide']))
            ->assertOk()
            ->assertSee('Caseificio Rossi')
            ->assertSee('Falegnameria Bianchi');
    }

    public function test_il_piede_mostra_ragione_sociale_partita_iva_e_contatti(): void
    {
        AdminSetting::current()->update([
            'company_name' => 'Gruppo Kosmos',
            'vat_number' => '18138671005',
            'website_email' => 'info@ksm.it',
            'address' => 'Via Eurialo, 56, 00181 Roma RM',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Gruppo Kosmos · P.IVA 18138671005')
            ->assertSee('info@ksm.it')
            ->assertSee('Via Eurialo, 56, 00181 Roma RM')
            ->assertSee('Tutti i diritti riservati.');
    }

    public function test_le_icone_social_compaiono_solo_con_un_indirizzo(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('Seguici su Facebook');

        AdminSetting::current()->update(['social_links' => ['facebook' => 'https://www.facebook.com/esempio']]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Seguici su Facebook')
            ->assertDontSee('Seguici su Instagram');
    }

    public function test_la_sitemap_e_un_indice_di_file_con_le_sole_pagine_pubbliche(): void
    {
        $this->listedCompany('Caseificio Rossi');
        $this->listedCompany('Azienda Spenta', ['is_active' => false]);

        $firstFile = route('sitemap.section', ['section' => 'aziende', 'page' => 1]);

        $index = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', (string) $index->headers->get('Content-Type'));
        $index->assertSee('<sitemapindex', false)->assertSee($firstFile, false);

        $this->get($firstFile)
            ->assertOk()
            ->assertSee(route('companies.show', 'caseificio-rossi'), false)
            ->assertDontSee(route('companies.show', 'azienda-spenta'), false);

        // Una sola azienda sta tutta nel primo file: il secondo non esiste.
        $this->get(route('sitemap.section', ['section' => 'aziende', 'page' => 2]))->assertNotFound();
    }

    public function test_i_dati_del_gestore_si_ripristinano_senza_toccare_le_modifiche(): void
    {
        AdminSetting::current()->update(['website_email' => 'modificata@example.test']);

        $this->seed(\Database\Seeders\SiteSettingsSeeder::class);

        $settings = AdminSetting::current();
        $this->assertSame('Gruppo Kosmos', $settings->company_name);
        $this->assertSame('18138671005', $settings->vat_number);
        $this->assertSame('modificata@example.test', $settings->website_email);
    }

    public function test_robots_blocca_le_aree_riservate_e_indica_la_sitemap(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /amministrazione', false)
            ->assertSee('Sitemap: '.route('sitemap'), false);
    }

    public function test_una_pagina_inesistente_mostra_la_pagina_di_errore(): void
    {
        $this->get('/aziende/non-esiste')
            ->assertNotFound()
            ->assertSee('Pagina non trovata');
    }
}
