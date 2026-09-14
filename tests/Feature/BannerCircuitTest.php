<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\AdvertisementStat;
use App\Models\Advertiser;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\Domain;
use App\Models\Role;
use App\Models\User;
use App\Support\Ads\AdContext;
use App\Support\Ads\AdServer;
use App\Support\Ads\Placements;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Il circuito banner: cosa compare dove, cosa si conta, chi vede cosa.
 */
class BannerCircuitTest extends TestCase
{
    use RefreshDatabase;

    private function campaign(array $attributes = [], array $counters = []): Advertisement
    {
        $campaign = Advertisement::create($attributes + [
            'name' => 'Kosmoprof',
            'link' => 'https://profumeriafiano.it/',
            'img' => 'advertisements/kosmoprof.png',
            'locations' => [Placements::HOME_BELOW_HEADER],
            'billing' => 'period',
            'status' => 1,
        ]);

        if ($counters) {
            $campaign->forceFill($counters)->save();
        }

        return $campaign->fresh();
    }

    private function user(string $type, string $email): User
    {
        return User::create([
            'name' => ucfirst(strtok($email, '@')), 'email' => $email,
            'password' => 'NuovaPassword2026!', 'user_type' => $type, 'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test',
            'password' => 'password', 'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function company(array $attributes = []): Company
    {
        return Company::create($attributes + [
            'user_id' => $this->user('vendor', 'titolare'.uniqid().'@example.test')->id,
            'name' => 'Decina Bus', 'slug' => 'decina-bus-'.uniqid(), 'is_active' => true,
        ]);
    }

    public function test_una_campagna_in_corso_compare_in_home_con_il_clic_contato(): void
    {
        $campaign = $this->campaign();

        $this->get('/')
            ->assertOk()
            ->assertSee('storage/advertisements/kosmoprof.png', false)
            ->assertSee(route('ads.click', $campaign), false);
    }

    public function test_solo_le_campagne_accese_nelle_date_e_sotto_i_limiti_sono_in_corso(): void
    {
        $inCorso = $this->campaign(['name' => 'In corso']);
        $ultimoGiorno = $this->campaign(['name' => 'Ultimo giorno', 'ends_at' => now()->endOfDay()]);

        $this->campaign(['status' => 0]);
        $this->campaign(['starts_at' => now()->addDay()]);
        $this->campaign(['ends_at' => now()->subDay()->endOfDay()]);
        $this->campaign(['billing' => 'impressions', 'max_impressions' => 10], ['impressions' => 10]);
        $this->campaign(['billing' => 'clicks', 'max_clicks' => 5], ['clicks' => 5]);

        $sospeso = Advertiser::create(['name' => 'Sospeso', 'is_active' => false]);
        $this->campaign(['advertiser_id' => $sospeso->id]);

        $this->assertEqualsCanonicalizing(
            [$inCorso->id, $ultimoGiorno->id],
            Advertisement::query()->running()->pluck('id')->all()
        );
    }

    public function test_i_bersagli_scelgono_dominio_citta_e_categoria(): void
    {
        $domain = Domain::create(['name' => 'mangiareebere', 'domain' => 'mangiareebere.it', 'type' => 'home', 'is_active' => true]);
        $mangiare = CompanyCategory::create(['name' => 'Mangiare e bere', 'slug' => 'mangiare-e-bere']);
        $ristoranti = CompanyCategory::create(['name' => 'Ristoranti', 'slug' => 'ristoranti', 'parent_id' => $mangiare->id]);

        $suDominio = $this->campaign(['target_domains' => [$domain->id]]);
        $suSitoPrincipale = $this->campaign(['target_domains' => [AdContext::MAIN_SITE]]);
        $aRoma = $this->campaign(['target_cities' => ['Roma']]);
        $perCategoria = $this->campaign(['target_categories' => [$mangiare->id]]);
        $ovunque = $this->campaign();

        $laggiu = new AdContext($domain->id, 'roma', [$ristoranti->id, $mangiare->id]);
        $qui = new AdContext(AdContext::MAIN_SITE, 'Milano', []);

        $this->assertTrue($suDominio->matches($laggiu));
        $this->assertFalse($suSitoPrincipale->matches($laggiu));
        $this->assertTrue($aRoma->matches($laggiu));
        $this->assertTrue($perCategoria->matches($laggiu));

        $this->assertFalse($suDominio->matches($qui));
        $this->assertTrue($suSitoPrincipale->matches($qui));
        $this->assertFalse($aRoma->matches($qui));
        $this->assertFalse($perCategoria->matches($qui));
        $this->assertTrue($ovunque->matches($qui));
    }

    public function test_sul_dominio_della_rete_compare_la_campagna_mirata_su_quel_dominio(): void
    {
        $domain = Domain::create(['name' => 'mangiareebere', 'domain' => 'mangiareebere.it', 'type' => 'home', 'is_active' => true]);

        $this->campaign(['img' => 'advertisements/dominio.png', 'target_domains' => [$domain->id]]);
        $this->campaign(['img' => 'advertisements/principale.png', 'target_domains' => [AdContext::MAIN_SITE]]);

        $this->get('http://mangiareebere.it/')
            ->assertOk()
            ->assertSee('advertisements/dominio.png', false)
            ->assertDontSee('advertisements/principale.png', false);

        // Indirizzo completo: dopo una richiesta su un altro host, "/" resterebbe su quell'host.
        $this->get('http://localhost/')
            ->assertSee('advertisements/principale.png', false)
            ->assertDontSee('advertisements/dominio.png', false);
    }

    public function test_sui_siti_propri_delle_aziende_non_compaiono_banner(): void
    {
        $this->campaign();
        app(TenantContext::class)->useCompany($this->company(['custom_domain' => 'decinabus.it']));

        $this->assertNull(app(AdServer::class)->pick(Placements::HOME_BELOW_HEADER, AdContext::current()));
    }

    public function test_il_clic_si_conta_una_volta_all_ora_per_persona(): void
    {
        $campaign = $this->campaign();

        $this->get(route('ads.click', $campaign))->assertRedirect('https://profumeriafiano.it/');
        $this->get(route('ads.click', $campaign))->assertRedirect('https://profumeriafiano.it/');

        $this->assertSame(1, $campaign->fresh()->clicks);
        $this->assertSame(1, AdvertisementStat::where('advertisement_id', $campaign->id)->value('clicks'));
    }

    public function test_su_una_campagna_ferma_il_clic_porta_al_sito_ma_non_si_conta(): void
    {
        $campaign = $this->campaign(['status' => 0]);

        $this->get(route('ads.click', $campaign))->assertRedirect('https://profumeriafiano.it/');

        $this->assertSame(0, $campaign->fresh()->clicks);
    }

    public function test_la_visualizzazione_si_conta_solo_con_la_firma_e_una_volta_per_firma(): void
    {
        $campaign = $this->campaign();
        $url = $campaign->viewUrl();

        $this->post($url)->assertNoContent();
        $this->post($url)->assertNoContent();
        $this->assertSame(1, $campaign->fresh()->impressions);

        $this->post(route('ads.view', $campaign, false))->assertForbidden();

        // Un'altra persona, con una firma nuova, conta.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post($campaign->viewUrl())
            ->assertNoContent();
        $this->assertSame(2, $campaign->fresh()->impressions);
        $this->assertSame(2, AdvertisementStat::where('advertisement_id', $campaign->id)->value('impressions'));
    }

    public function test_raggiunte_le_visualizzazioni_acquistate_la_campagna_si_ferma(): void
    {
        $campaign = $this->campaign(['billing' => 'impressions', 'max_impressions' => 1]);

        $this->post($campaign->viewUrl())->assertNoContent();

        $this->assertSame('Visualizzazioni esaurite', $campaign->fresh()->stateLabel());
        $this->get('/')->assertDontSee('advertisements/kosmoprof.png', false);
    }

    public function test_il_popup_compare_in_home(): void
    {
        $this->campaign(['locations' => [Placements::HOME_POPUP]]);

        $this->get('/')->assertOk()->assertSee('data-ad-popup', false);
    }

    public function test_un_inserzionista_esterno_si_registra_e_trova_la_sua_area(): void
    {
        Mail::fake();

        $this->post(route('advertiser.register.store'), [
            'business_name' => 'Profumeria Fiano',
            'name' => 'Anna Fiano',
            'email' => 'anna@profumeriafiano.test',
            'password' => 'NuovaPassword2026!',
            'password_confirmation' => 'NuovaPassword2026!',
        ])->assertRedirect(route('verification.show'));

        $user = User::where('email', 'anna@profumeriafiano.test')->firstOrFail();

        $this->assertSame('advertiser', $user->user_type);
        $this->assertSame('Profumeria Fiano', $user->advertiser->name);

        $this->get(route('advertiser.dashboard'))->assertOk()->assertSee('Profumeria Fiano');
    }

    public function test_accedendo_l_inserzionista_arriva_nella_sua_area(): void
    {
        $user = $this->user('advertiser', 'anna@example.test');
        Advertiser::create(['user_id' => $user->id, 'name' => 'Profumeria Fiano']);

        $this->post(route('login.store'), ['email' => 'anna@example.test', 'password' => 'NuovaPassword2026!'])
            ->assertRedirect(route('advertiser.dashboard'));
    }

    public function test_l_inserzionista_vede_solo_le_sue_campagne(): void
    {
        $anna = $this->user('advertiser', 'anna@example.test');
        $bruno = $this->user('advertiser', 'bruno@example.test');
        $diAnna = $this->campaign(['name' => 'Campagna di Anna', 'advertiser_id' => Advertiser::create(['user_id' => $anna->id, 'name' => 'Anna'])->id]);
        $diBruno = $this->campaign(['name' => 'Campagna di Bruno', 'advertiser_id' => Advertiser::create(['user_id' => $bruno->id, 'name' => 'Bruno'])->id]);

        $this->actingAs($anna)
            ->get(route('advertiser.dashboard'))
            ->assertOk()
            ->assertSee('Campagna di Anna')
            ->assertDontSee('Campagna di Bruno');

        $this->actingAs($anna)->get(route('advertiser.campaigns.show', $diAnna))->assertOk()->assertSee('Ultimi 60 giorni');
        $this->actingAs($anna)->get(route('advertiser.campaigns.show', $diBruno))->assertNotFound();
    }

    public function test_senza_profilo_inserzionista_l_area_non_si_apre(): void
    {
        $this->actingAs($this->user('buyer', 'cliente@example.test'))
            ->get(route('advertiser.dashboard'))
            ->assertForbidden();
    }

    public function test_l_azienda_inserzionista_vede_le_statistiche_nell_area_azienda(): void
    {
        $company = $this->company();
        $advertiser = Advertiser::create(['company_id' => $company->id, 'name' => $company->name]);
        $this->campaign(['name' => 'Bus per matrimoni', 'advertiser_id' => $advertiser->id]);

        $this->actingAs($company->user)
            ->get(route('vendor.advertising.index'))
            ->assertOk()
            ->assertSee('Bus per matrimoni');
    }

    public function test_l_amministratore_crea_una_campagna_mirata(): void
    {
        Storage::fake('public');
        $categoria = CompanyCategory::create(['name' => 'Bellezza', 'slug' => 'bellezza']);
        $advertiser = Advertiser::create(['name' => 'Profumeria Fiano']);

        $this->actingAs($this->admin())
            ->post(route('admin.advertisements.store'), [
                'advertiser_id' => $advertiser->id,
                'name' => 'Autunno',
                'link' => 'https://profumeriafiano.it/',
                'image' => UploadedFile::fake()->image('banner.png', 728, 90),
                'locations' => [Placements::HOME_BELOW_HEADER, Placements::COMPANIES_LIST],
                'billing' => 'clicks',
                'max_clicks' => 500,
                'target_domains' => [AdContext::MAIN_SITE],
                'target_cities' => 'Roma, Ostia',
                'target_categories' => [$categoria->id],
                'status' => '1',
            ])
            ->assertSessionHasNoErrors();

        $campaign = Advertisement::firstOrFail();

        $this->assertSame(['Roma', 'Ostia'], $campaign->target_cities);
        $this->assertSame([AdContext::MAIN_SITE], $campaign->target_domains);
        $this->assertSame(500, $campaign->max_clicks);
        Storage::disk('public')->assertExists($campaign->img);
    }

    public function test_ogni_modo_di_pagare_chiede_il_suo_limite(): void
    {
        $admin = $this->admin();
        $payload = [
            'name' => 'Autunno',
            'link' => 'https://profumeriafiano.it/',
            'img' => 'x',
            'locations' => [Placements::HOME_BELOW_HEADER],
        ];

        $this->actingAs($admin)
            ->post(route('admin.advertisements.store'), ['billing' => 'clicks'] + $payload)
            ->assertSessionHasErrors(['max_clicks', 'image']);

        $this->actingAs($admin)
            ->post(route('admin.advertisements.store'), ['billing' => 'period'] + $payload)
            ->assertSessionHasErrors('ends_at');

        $this->assertSame(0, Advertisement::count());
    }

    public function test_l_amministratore_crea_un_inserzionista_esterno_con_accesso(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.advertisers.store'), [
                'kind' => 'external',
                'name' => 'Profumeria Fiano',
                'login_email' => 'fiano@example.test',
                'password' => 'NuovaPassword2026!',
                'password_confirmation' => 'NuovaPassword2026!',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $advertiser = Advertiser::firstOrFail();

        $this->assertSame('fiano@example.test', $advertiser->user->email);
        $this->assertSame('advertiser', $advertiser->user->user_type);
    }

    public function test_un_azienda_del_sito_diventa_inserzionista(): void
    {
        $company = $this->company();

        $this->actingAs($this->admin())
            ->post(route('admin.advertisers.store'), [
                'kind' => 'company',
                'company' => "Decina Bus · #$company->id",
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $advertiser = Advertiser::firstOrFail();

        $this->assertSame($company->id, $advertiser->company_id);
        $this->assertSame('Decina Bus', $advertiser->name);
        $this->assertNull($advertiser->user_id);
    }

    public function test_le_pagine_di_amministrazione_del_circuito_si_aprono(): void
    {
        $campaign = $this->campaign();
        $admin = $this->admin();

        foreach ([
            route('admin.advertisements.index'),
            route('admin.advertisements.create'),
            route('admin.advertisements.show', $campaign),
            route('admin.advertisements.edit', $campaign),
            route('admin.advertisers.index'),
            route('admin.advertisers.create'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
