<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Conversion;
use App\Models\PageView;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\Analytics\Conversions;
use App\Support\Analytics\Geo;
use App\Support\Analytics\Insights;
use App\Support\Analytics\MmdbReader;
use App\Support\Analytics\Period;
use App\Support\Analytics\TrafficSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Statistiche piu' a fondo: archivio geografico, obiettivi, nuovi e di
 * ritorno, parole cercate e i report che ne vengono.
 */
class AnalyticsDeepTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private function fixture(): string
    {
        return base_path('tests/Fixtures/geo-city.mmdb');
    }

    private function row(array $attributes = []): PageView
    {
        return PageView::create($attributes + [
            'uid' => (string) Str::ulid(),
            'host' => 'ksm.it',
            'site_type' => 'platform',
            'path' => '/',
            'session_id' => (string) Str::ulid(),
            'visitor' => sha1(uniqid()),
            'is_entry' => false,
            'device' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'duration' => 0,
            'day' => '2026-10-05',
            'hour' => 10,
        ]);
    }

    private function conversion(string $goal, array $attributes = []): Conversion
    {
        return Conversion::create($attributes + [
            'host' => 'ksm.it', 'goal' => $goal, 'day' => '2026-10-05', 'hour' => 11,
        ]);
    }

    private function insights(?string $host = null): Insights
    {
        return new Insights(new Period(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-07'), 'prova'), $host);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password',
            'user_type' => 'admin', 'is_active' => true, 'role_id' => $role->id,
        ]);
    }

    /* Archivio geografico ------------------------------------------------------ */

    public function test_il_lettore_trova_paese_regione_e_citta_di_un_indirizzo_ipv4(): void
    {
        $reader = new MmdbReader($this->fixture());
        $data = $reader->get('203.0.113.9');

        $this->assertSame('IT', $data['country']['iso_code']);
        $this->assertSame('Lombardy', $data['subdivisions'][0]['names']['en']);
        $this->assertSame('Milan', $data['city']['names']['en']);
        $this->assertSame('Bavaria', $reader->get('192.0.2.5')['subdivisions'][0]['names']['en']);
    }

    public function test_il_lettore_legge_anche_ipv6_e_non_inventa_indirizzi_sconosciuti(): void
    {
        $reader = new MmdbReader($this->fixture());

        $this->assertSame('Tuscany', $reader->get('2001:db8:1::5')['subdivisions'][0]['names']['en']);
        $this->assertNull($reader->get('8.8.8.8'));
        $this->assertNull($reader->get('non-un-indirizzo'));
    }

    public function test_un_file_che_non_e_un_archivio_viene_rifiutato(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'geo');
        file_put_contents($path, 'niente di che');

        $this->expectException(\RuntimeException::class);

        try {
            new MmdbReader($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_geo_traduce_regione_e_citta_in_italiano_solo_per_l_italia(): void
    {
        config(['ksm.analytics.geoip_database' => $this->fixture()]);
        Geo::forget();

        $it = Geo::locate(\Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9']));
        $de = Geo::locate(\Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.5']));
        $none = Geo::locate(\Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']));

        $this->assertSame(['country' => 'IT', 'region' => 'Lombardia', 'city' => 'Milano'], $it);
        $this->assertSame(['country' => 'DE', 'region' => null, 'city' => null], $de);
        $this->assertSame(['country' => null, 'region' => null, 'city' => null], $none);
    }

    public function test_se_l_intestazione_del_paese_contraddice_l_archivio_regione_e_citta_si_scartano(): void
    {
        config(['ksm.analytics.geoip_database' => $this->fixture()]);
        Geo::forget();

        $found = Geo::locate(\Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_IPCOUNTRY' => 'FR']));

        $this->assertSame(['country' => 'FR', 'region' => null, 'city' => null], $found);
    }

    public function test_senza_archivio_la_posizione_resta_vuota(): void
    {
        config(['ksm.analytics.geoip_database' => '/non/esiste.mmdb']);
        Geo::forget();

        $found = Geo::locate(\Illuminate\Http\Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.9']));

        $this->assertSame(['country' => null, 'region' => null, 'city' => null], $found);
    }

    public function test_una_visita_salva_regione_e_citta_dall_archivio(): void
    {
        config(['ksm.analytics.geoip_database' => $this->fixture()]);
        Geo::forget();

        $this->withHeaders(['User-Agent' => self::CHROME])->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->get('/contatti')->assertOk();

        $view = PageView::first();
        $this->assertSame('IT', $view->country);
        $this->assertSame('Lazio', $view->region);
        $this->assertSame('Roma', $view->city);
    }

    /* Parole cercate -------------------------------------------------------------- */

    public function test_la_ricerca_nel_sito_si_salva_pulita(): void
    {
        $this->withHeaders(['User-Agent' => self::CHROME])->get('/contatti?cerca='.rawurlencode('  Olio   Calabrese '))->assertOk();
        $this->withHeaders(['User-Agent' => self::CHROME])->get('/contatti?cerca=mario.rossi@example.com')->assertOk();
        $this->withHeaders(['User-Agent' => self::CHROME])->get('/contatti?cerca=1234567890123')->assertOk();

        $this->assertSame(['olio calabrese'], PageView::whereNotNull('search')->pluck('search')->all());
    }

    public function test_le_parole_dai_motori_si_colgono_solo_se_il_motore_le_passa(): void
    {
        $bing = TrafficSource::classify('https://www.bing.com/search?q=Olio+Bio', [], 'ksm.it');
        $google = TrafficSource::classify('https://www.google.com/', [], 'ksm.it');
        $campaign = TrafficSource::classify(null, ['utm_source' => 'news', 'utm_medium' => 'email', 'utm_term' => 'Offerta Estate'], 'ksm.it');

        $this->assertSame('search', $bing['channel']);
        $this->assertSame('olio bio', $bing['keyword']);
        $this->assertNull($google['keyword']);
        $this->assertSame('offerta estate', $campaign['keyword']);
    }

    /* Obiettivi ---------------------------------------------------------------------- */

    public function test_un_messaggio_inviato_conta_come_obiettivo_con_la_provenienza_della_visita(): void
    {
        Mail::fake();
        $headers = ['User-Agent' => self::CHROME, 'Referer' => 'https://www.google.com/'];

        $this->withHeaders($headers)->get('/contatti')->assertOk();
        $this->withHeaders($headers)->post('/contatti', ['name' => 'Anna', 'email' => 'anna@example.test', 'message' => 'Buongiorno'])->assertRedirect();

        $conversion = Conversion::first();
        $this->assertNotNull($conversion);
        $this->assertSame('contact', $conversion->goal);
        $this->assertSame('search', $conversion->channel);
        $this->assertSame('Google', $conversion->source);
        $this->assertSame(PageView::first()->session_id, $conversion->session_id);
    }

    public function test_senza_il_consenso_dnt_nessun_obiettivo_si_conta(): void
    {
        Mail::fake();

        $this->withHeaders(['User-Agent' => self::CHROME, 'DNT' => '1'])
            ->post('/contatti', ['name' => 'Anna', 'email' => 'anna@example.test', 'message' => 'Buongiorno'])->assertRedirect();

        $this->assertSame(0, Conversion::count());
    }

    public function test_gli_amministratori_e_i_programmi_automatici_non_fanno_obiettivi(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'POST', [], [], [], ['HTTP_USER_AGENT' => 'Googlebot/2.1']);
        Conversions::track($request, Conversions::ORDER, 10);

        $this->assertSame(0, Conversion::count());

        $request = \Illuminate\Http\Request::create('/', 'POST', [], [], [], ['HTTP_USER_AGENT' => self::CHROME]);
        $request->setUserResolver(fn () => $this->admin());
        Conversions::track($request, Conversions::ORDER, 10);

        $this->assertSame(0, Conversion::count());
    }

    public function test_un_obiettivo_senza_visita_trovata_si_conta_lo_stesso(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'POST', [], [], [], ['HTTP_USER_AGENT' => self::CHROME]);
        Conversions::track($request, Conversions::SIGNUP, null, 7);

        $conversion = Conversion::first();
        $this->assertSame('signup', $conversion->goal);
        $this->assertNull($conversion->session_id);
        $this->assertNull($conversion->channel);
        $this->assertSame('7', $conversion->ref);
    }

    public function test_un_obiettivo_sconosciuto_non_si_registra(): void
    {
        Conversions::track(\Illuminate\Http\Request::create('/', 'POST', [], [], [], ['HTTP_USER_AGENT' => self::CHROME]), 'inventato');

        $this->assertSame(0, Conversion::count());
    }

    /* Nuovi e di ritorno ------------------------------------------------------------- */

    public function test_senza_l_opzione_non_esiste_nessun_cookie(): void
    {
        config(['ksm.analytics.returning_visitors' => false]);

        $response = $this->withHeaders(['User-Agent' => self::CHROME])->get('/contatti')->assertOk();

        $response->assertCookieMissing('ksm_vid');
        $this->assertNull(PageView::first()->vid);
        $this->assertNull(PageView::first()->is_returning);
    }

    public function test_con_l_opzione_il_primo_passaggio_e_nuovo_e_il_ritorno_di_giorni_dopo_e_riconosciuto(): void
    {
        config(['ksm.analytics.returning_visitors' => true]);

        $first = $this->withHeaders(['User-Agent' => self::CHROME])->get('/contatti')->assertOk();
        $cookie = $first->getCookie('ksm_vid');
        $this->assertNotNull($cookie);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $cookie->getValue());

        $one = PageView::first();
        $this->assertFalse($one->is_returning);
        $this->assertSame(40, strlen($one->vid));
        $this->assertNotSame($cookie->getValue(), $one->vid, 'il valore del cookie non si salva in chiaro');

        // Giorni dopo: stesso cookie.
        $this->travel(3)->days();
        $this->withHeaders(['User-Agent' => self::CHROME])->withCookie('ksm_vid', $cookie->getValue())->get('/contatti')->assertOk();

        $second = PageView::orderByDesc('id')->first();
        $this->assertTrue($second->is_returning);
        $this->assertSame($one->vid, $second->vid);
    }

    public function test_lo_stesso_giorno_con_il_cookie_non_e_ancora_di_ritorno(): void
    {
        config(['ksm.analytics.returning_visitors' => true]);

        $cookie = $this->withHeaders(['User-Agent' => self::CHROME])->get('/contatti')->getCookie('ksm_vid');
        $this->travel(2)->hours();
        $this->withHeaders(['User-Agent' => self::CHROME])->withCookie('ksm_vid', $cookie->getValue())->get('/contatti');

        $this->assertFalse(PageView::orderByDesc('id')->first()->is_returning);
    }

    public function test_un_cookie_manomesso_viene_sostituito(): void
    {
        config(['ksm.analytics.returning_visitors' => true]);

        $response = $this->withHeaders(['User-Agent' => self::CHROME])->withCookie('ksm_vid', "' OR 1=1 --")->get('/contatti');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $response->getCookie('ksm_vid')->getValue());
    }

    /* Report ------------------------------------------------------------------------- */

    public function test_obiettivi_con_confronto_quota_e_incasso(): void
    {
        foreach (range(1, 4) as $i) {
            $this->row(['is_entry' => true, 'channel' => 'search', 'source' => 'Google']);
        }
        $this->conversion('order', ['value' => 50.5, 'session_id' => 'S1']);
        $this->conversion('order', ['value' => 20, 'session_id' => 'S2']);
        $this->conversion('contact', ['session_id' => 'S3']);
        // Periodo precedente (24-30 settembre): un solo ordine.
        $this->conversion('order', ['day' => '2026-09-26', 'value' => 9, 'session_id' => 'S9']);

        $goals = collect($this->insights()->goals())->keyBy('goal');

        $this->assertSame(2, $goals['order']['count']);
        $this->assertEquals(70.5, $goals['order']['value']);
        $this->assertEquals(1.0, $goals['order']['change']);
        $this->assertEquals(0.5, $goals['order']['rate']);
        $this->assertSame(1, $goals['contact']['count']);
        $this->assertNull($goals['contact']['change']);
        $this->assertSame(0, $goals['signup']['count']);
    }

    public function test_il_percorso_verso_l_ordine_conta_le_visite_a_ogni_passo(): void
    {
        $sessions = [];
        foreach (['A', 'B', 'C', 'D'] as $s) {
            $sessions[$s] = $this->row(['is_entry' => true, 'session_id' => $s, 'path' => '/'])->session_id;
        }
        $this->row(['session_id' => 'A', 'path' => '/prodotti/olio']);
        $this->row(['session_id' => 'B', 'path' => '/aziende/rossi']);
        $this->row(['session_id' => 'B', 'path' => '/pagamento']);
        $this->conversion('cart', ['session_id' => 'A']);
        $this->conversion('cart', ['session_id' => 'B']);
        $this->conversion('order', ['session_id' => 'B', 'value' => 10]);

        $funnel = $this->insights()->funnel();

        $this->assertSame([4, 2, 2, 1, 1], array_column($funnel, 'count'));
        $this->assertEquals(0.5, $funnel[1]['share']);
        $this->assertEquals(0.5, $funnel[3]['step']);
    }

    public function test_le_fonti_che_portano_ordini(): void
    {
        foreach (range(1, 5) as $i) {
            $this->row(['is_entry' => true, 'channel' => 'search', 'source' => 'Google']);
        }
        $this->row(['is_entry' => true, 'channel' => 'social', 'source' => 'Facebook']);
        $this->conversion('order', ['channel' => 'search', 'source' => 'Google', 'value' => 40]);
        $this->conversion('order', ['channel' => 'search', 'source' => 'Google', 'value' => 60]);
        $this->conversion('signup', ['channel' => 'social', 'source' => 'Facebook']);

        $rows = $this->insights()->conversionSources();

        $this->assertSame('Google', $rows[0]->source);
        $this->assertSame(2, $rows[0]->orders);
        $this->assertEquals(100.0, $rows[0]->revenue);
        $this->assertEquals(0.4, $rows[0]->rate);
        $this->assertSame(1, $rows[1]->signups);
    }

    public function test_nuovi_e_di_ritorno_con_il_comportamento_di_ciascuno(): void
    {
        $this->row(['is_entry' => true, 'is_returning' => false, 'session_id' => 'N1', 'duration' => 10]);
        $this->row(['session_id' => 'N1', 'duration' => 20]);
        $this->row(['is_entry' => true, 'is_returning' => true, 'session_id' => 'R1', 'duration' => 100]);
        $this->row(['is_entry' => true, 'session_id' => 'X1']);

        $audience = $this->insights()->audience();
        $this->assertSame(['new' => 1, 'returning' => 1, 'unknown' => 1, 'total' => 3], $audience);

        $behaviour = collect($this->insights()->audienceBehaviour())->keyBy('key');
        $this->assertEquals(2.0, $behaviour['new']['pages']);
        $this->assertEquals(30.0, $behaviour['new']['duration']);
        $this->assertEquals(1.0, $behaviour['returning']['pages']);
    }

    public function test_le_coorti_mostrano_quanti_tornano_nelle_settimane_dopo(): void
    {
        $monday = CarbonImmutable::now(config('ksm.analytics.timezone'))->startOfWeek()->subWeeks(2);

        // Tre persone alla prima visita; una torna dopo una settimana.
        foreach (['a', 'b', 'c'] as $v) {
            $this->row(['vid' => str_repeat($v, 40), 'day' => $monday->toDateString()]);
        }
        $this->row(['vid' => str_repeat('a', 40), 'day' => $monday->addWeek()->toDateString()]);

        $cohorts = $this->insights()->cohorts();

        $this->assertCount(1, $cohorts);
        $this->assertSame(3, $cohorts[0]['size']);
        $this->assertEquals(1.0, $cohorts[0]['weeks'][0]);
        $this->assertEqualsWithDelta(1 / 3, $cohorts[0]['weeks'][1], 0.001);
        $this->assertEquals(0.0, $cohorts[0]['weeks'][2]);
    }

    public function test_aziende_e_prodotti_si_mostrano_con_il_nome_e_non_con_l_indirizzo(): void
    {
        $owner = User::create(['name' => 'V', 'email' => 'v@example.test', 'password' => 'password', 'user_type' => 'vendor']);
        $company = Company::create(['user_id' => $owner->id, 'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true]);
        Product::create(['company_id' => $company->id, 'name' => 'Olio Extra', 'slug' => 'olio-extra', 'price' => 10, 'stock' => 5, 'status' => 'active']);

        foreach (range(1, 3) as $i) {
            $this->row(['path' => '/aziende/calabria-sapori']);
        }
        $this->row(['path' => '/aziende/calabria-sapori/recensioni']);
        $this->row(['path' => '/prodotti/olio-extra']);

        $companies = $this->insights()->companies();
        $products = $this->insights()->products();

        $this->assertCount(1, $companies);
        $this->assertSame('Calabria Sapori', $companies[0]->name);
        $this->assertSame(3, $companies[0]->views);
        $this->assertSame('Olio Extra', $products[0]->name);
        $this->assertSame('Calabria Sapori', $products[0]->company);
    }

    public function test_i_siti_delle_aziende_con_i_loro_obiettivi(): void
    {
        $owner = User::create(['name' => 'V', 'email' => 'v@example.test', 'password' => 'password', 'user_type' => 'vendor']);
        $company = Company::create(['user_id' => $owner->id, 'name' => 'Calabria Sapori', 'slug' => 'calabria-sapori', 'is_active' => true]);
        $this->row(['host' => 'calabriasapori.it', 'site_type' => 'company', 'site_id' => $company->id, 'is_entry' => true]);
        $this->conversion('order', ['host' => 'calabriasapori.it', 'value' => 5]);

        $rows = $this->insights()->storefronts();

        $this->assertSame('Calabria Sapori', $rows[0]->name);
        $this->assertSame(1, $rows[0]->goals);
    }

    public function test_regioni_e_citta_contano_solo_le_visite_italiane_con_posizione(): void
    {
        $this->row(['is_entry' => true, 'country' => 'IT', 'region' => 'Lombardia', 'city' => 'Milano']);
        $this->row(['is_entry' => true, 'country' => 'IT', 'region' => 'Lombardia', 'city' => 'Bergamo']);
        $this->row(['is_entry' => true, 'country' => 'IT', 'region' => 'Lazio', 'city' => 'Roma']);
        $this->row(['is_entry' => true, 'country' => 'DE', 'region' => 'Bayern']);
        $this->row(['is_entry' => true, 'country' => 'IT']);

        $regions = $this->insights()->regions();

        $this->assertSame(['Lombardia', 'Lazio'], $regions->pluck('label')->all());
        $this->assertEqualsWithDelta(2 / 3, $regions[0]->share, 0.001);
        $this->assertCount(3, $this->insights()->cities());
        $this->assertTrue($this->insights()->hasPlaces());
    }

    public function test_le_uscite_sono_l_ultima_pagina_di_ogni_visita(): void
    {
        $this->row(['session_id' => 'A', 'path' => '/', 'is_entry' => true]);
        $this->row(['session_id' => 'A', 'path' => '/contatti']);
        $this->row(['session_id' => 'B', 'path' => '/contatti', 'is_entry' => true]);
        $this->row(['session_id' => 'C', 'path' => '/contatti', 'is_entry' => true]);

        $exits = $this->insights()->exitPages();

        $this->assertSame('/contatti', $exits[0]->path);
        $this->assertSame(3, $exits[0]->exits);
        $this->assertSame(3, $exits[0]->views);
        $this->assertEquals(1.0, $exits[0]->rate);
    }

    public function test_il_dettaglio_di_una_pagina_dice_da_dove_si_arriva_e_dove_si_va(): void
    {
        $this->row(['session_id' => 'A', 'path' => '/', 'is_entry' => true, 'channel' => 'search', 'source' => 'Google']);
        $this->row(['session_id' => 'A', 'path' => '/aziende/x', 'duration' => 30]);
        $this->row(['session_id' => 'A', 'path' => '/pagamento']);
        $this->row(['session_id' => 'B', 'path' => '/aziende/x', 'is_entry' => true, 'channel' => 'direct']);

        $page = $this->insights()->page('/aziende/x');

        $this->assertSame(2, $page['views']);
        $this->assertSame(1, $page['entries']);
        $this->assertSame(1, $page['exits']);
        $this->assertSame(1, $page['bounces']);
        $this->assertEquals(30.0, $page['avg_time']);
        $this->assertSame(['/'], $page['previous']->pluck('path')->all());
        $this->assertSame(['/pagamento'], $page['next']->pluck('path')->all());
        $this->assertCount(7, $page['series']);
    }

    /* Pagine di amministrazione ----------------------------------------------------- */

    public function test_la_pagina_mostra_le_nuove_sezioni(): void
    {
        $this->row(['is_entry' => true, 'country' => 'IT', 'region' => 'Lombardia', 'city' => 'Milano', 'channel' => 'search', 'source' => 'Google', 'day' => now()->toDateString()]);
        $this->conversion('order', ['value' => 12, 'day' => now()->toDateString(), 'channel' => 'search', 'source' => 'Google']);

        $this->actingAs($this->admin())->get('/amministrazione/statistiche?periodo=7')
            ->assertOk()
            ->assertSee('Obiettivi raggiunti')
            ->assertSee('Dalla visita all')
            ->assertSee('Nuovi e di ritorno')
            ->assertSee('Regioni italiane')
            ->assertSee('Lombardia')
            ->assertSee('Pagine da cui si esce')
            ->assertSee('KSM_ANALYTICS_RETURNING');
    }

    public function test_la_pagina_di_dettaglio_si_apre_ed_e_riservata(): void
    {
        $this->row(['path' => '/contatti', 'is_entry' => true, 'day' => now()->toDateString()]);

        $this->get('/amministrazione/statistiche/pagina?path=/contatti')->assertRedirect();

        $this->actingAs($this->admin())->get('/amministrazione/statistiche/pagina?path=/contatti&periodo=7')
            ->assertOk()
            ->assertSee('Dettaglio pagina')
            ->assertSee('/contatti');

        $this->get('/amministrazione/statistiche/pagina?path=/mai-vista&periodo=7')
            ->assertOk()->assertSee('Nessuna visualizzazione');
    }
}
