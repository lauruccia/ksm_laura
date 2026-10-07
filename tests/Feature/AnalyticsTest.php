<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\Role;
use App\Models\User;
use App\Support\Analytics\Format;
use App\Support\Analytics\PageViewRecorder;
use App\Support\Analytics\Period;
use App\Support\Analytics\Report;
use App\Support\Analytics\TrafficSource;
use App\Support\Analytics\UserAgent;
use App\Support\Permissions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Statistiche delle visite: cosa si conta, cosa no, e come i numeri
 * arrivano alla pagina di amministrazione.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

    private function visit(string $path = '/contatti', array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders(['User-Agent' => self::CHROME] + $headers)->get($path);
    }

    private function admin(?array $permissions = null): User
    {
        $role = $permissions === null
            ? Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true])
            : Role::create(['name' => 'Ruolo '.Str::random(4), 'slug' => 'ruolo-'.Str::random(6), 'permissions' => $permissions]);

        return User::create([
            'name' => 'Admin',
            'email' => 'admin'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'admin',
            'is_active' => true,
            'role_id' => $role->id,
        ]);
    }

    /** Una riga di statistiche scritta a mano, per provare i report. */
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
            'channel' => null,
            'device' => 'desktop',
            'browser' => 'Chrome',
            'os' => 'Windows',
            'duration' => 0,
            'day' => '2026-10-05',
            'hour' => 10,
        ]);
    }

    private function period(string $from = '2026-10-01', string $to = '2026-10-07'): Period
    {
        return new Period(CarbonImmutable::parse($from), CarbonImmutable::parse($to), 'prova');
    }

    /* Raccolta -------------------------------------------------------------- */

    public function test_una_pagina_pubblica_viene_contata_e_porta_la_sigla_per_la_durata(): void
    {
        $response = $this->visit()->assertOk()->assertSee('name="ksm-pv"', false);

        $view = PageView::sole();

        $this->assertSame('/contatti', $view->path);
        $this->assertSame('localhost', $view->host);
        $this->assertSame('platform', $view->site_type);
        $this->assertTrue($view->is_entry);
        $this->assertSame('direct', $view->channel);
        $this->assertSame(['desktop', 'Chrome', 'Windows'], [$view->device, $view->browser, $view->os]);
        $this->assertSame(Period::today()->toDateString(), $view->day);
        $response->assertSee('content="'.$view->uid.'"', false);
    }

    public function test_le_pagine_della_stessa_visita_condividono_la_sessione_e_solo_la_prima_porta_la_provenienza(): void
    {
        $this->visit('/contatti', ['Referer' => 'https://www.google.com/search?q=ksm']);
        $this->visit('/aziende', ['Referer' => 'http://localhost/contatti']);

        [$first, $second] = PageView::orderBy('id')->get()->all();

        $this->assertSame($first->session_id, $second->session_id);
        $this->assertTrue($first->is_entry);
        $this->assertFalse($second->is_entry);
        $this->assertSame(['search', 'Google'], [$first->channel, $first->source]);
        $this->assertNull($second->channel);
    }

    public function test_un_altro_visitatore_o_dopo_una_pausa_e_una_visita_nuova(): void
    {
        $this->visit();
        $this->withHeaders(['User-Agent' => self::IPHONE])->get('/contatti');

        $this->assertSame(2, PageView::distinct()->count('session_id'));

        $this->travel(PageViewRecorder::SESSION_MINUTES + 1)->minutes();
        $this->visit();

        $this->assertSame(3, PageView::distinct()->count('session_id'));
        $this->assertSame(3, PageView::where('is_entry', true)->count());
    }

    public function test_cio_che_non_va_contato_non_viene_scritto(): void
    {
        // Programma automatico.
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)'])->get('/contatti');
        // Do Not Track e Global Privacy Control.
        $this->visit('/contatti', ['DNT' => '1']);
        $this->visit('/contatti', ['Sec-GPC' => '1']);
        // Pagina preparata in anticipo dal browser.
        $this->visit('/contatti', ['Sec-Purpose' => 'prefetch']);
        // Aree riservate e indirizzi con codici.
        $this->visit('/password/reimposta/segreto');
        $this->visit('/account');
        // Non e' una pagina vista.
        $this->post('/contatti');
        $this->getJson('/contatti');
        // Chi amministra il sito.
        $this->actingAs($this->admin())->withHeaders(['User-Agent' => self::CHROME])->get('/contatti');

        $this->assertSame(0, PageView::count());
    }

    public function test_si_possono_spegnere_le_statistiche(): void
    {
        config(['ksm.analytics.enabled' => false]);

        $this->visit()->assertOk()->assertDontSee('ksm-pv', false);

        $this->assertSame(0, PageView::count());
    }

    public function test_una_pagina_che_non_risponde_200_non_e_contata(): void
    {
        $this->visit('/aziende/non-esiste')->assertNotFound();

        $this->assertSame(0, PageView::count());
    }

    public function test_si_registra_solo_il_percorso_senza_i_parametri(): void
    {
        $this->visit('/contatti?email=mario@example.test&utm_source=newsletter&utm_medium=email&utm_campaign=autunno');

        $view = PageView::sole();

        $this->assertSame('/contatti', $view->path);
        $this->assertSame(['email', 'newsletter', 'autunno'], [$view->channel, $view->source, $view->campaign]);
    }

    /* Durata ---------------------------------------------------------------- */

    public function test_la_durata_si_aggiorna_e_puo_solo_crescere(): void
    {
        $this->visit();
        $uid = PageView::sole()->uid;

        $this->post(route('analytics.duration'), ['pv' => $uid, 't' => 12])->assertNoContent();
        $this->assertSame(12, PageView::sole()->duration);

        $this->post(route('analytics.duration'), ['pv' => $uid, 't' => 5])->assertNoContent();
        $this->assertSame(12, PageView::sole()->duration, 'tornare indietro non azzera');

        $this->post(route('analytics.duration'), ['pv' => $uid, 't' => 99999])->assertNoContent();
        $this->assertSame(3600, PageView::sole()->duration, 'oltre un\'ora e\' una scheda dimenticata');
    }

    public function test_la_durata_con_dati_sbagliati_o_una_sigla_inventata_non_tocca_niente(): void
    {
        $this->visit();

        $this->post(route('analytics.duration'), ['pv' => 'corta', 't' => 10])->assertStatus(422);
        $this->post(route('analytics.duration'), ['pv' => (string) Str::ulid(), 't' => 10])->assertNoContent();
        $this->post(route('analytics.duration'), ['pv' => PageView::sole()->uid, 't' => 'molti'])->assertStatus(422);

        $this->assertSame(0, PageView::sole()->duration);
    }

    /* Classificazione ------------------------------------------------------- */

    /** @dataProvider agents */
    public function test_dispositivo_browser_e_sistema(string $ua, array $expected): void
    {
        $this->assertSame($expected, array_values(UserAgent::parse($ua)));
    }

    public static function agents(): array
    {
        return [
            'Chrome su Windows' => [self::CHROME, ['desktop', 'Chrome', 'Windows']],
            'Safari su iPhone' => [self::IPHONE, ['mobile', 'Safari', 'iOS']],
            'Edge' => ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/126.0 Safari/537.36 Edg/126.0', ['desktop', 'Edge', 'Windows']],
            'Firefox su Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0', ['desktop', 'Firefox', 'Linux']],
            'Chrome su Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/126.0 Mobile Safari/537.36', ['mobile', 'Chrome', 'Android']],
            'Tablet Android' => ['Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 Chrome/126.0 Safari/537.36', ['tablet', 'Chrome', 'Android']],
            'iPad' => ['Mozilla/5.0 (iPad; CPU OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.5 Mobile/15E148 Safari/604.1', ['tablet', 'Safari', 'iOS']],
            'Vuoto' => ['', ['desktop', 'Altro', 'Altro']],
        ];
    }

    /** @dataProvider referrers */
    public function test_provenienza(?string $referrer, array $query, string $channel, ?string $source): void
    {
        $result = TrafficSource::classify($referrer, $query, 'ksm.it');

        $this->assertSame([$channel, $source], [$result['channel'], $result['source']]);
    }

    public static function referrers(): array
    {
        return [
            'nessuno' => [null, [], 'direct', null],
            'lo stesso sito' => ['https://ksm.it/aziende', [], 'direct', null],
            'Google italiano' => ['https://www.google.it/', [], 'search', 'Google'],
            'Bing' => ['https://www.bing.com/search?q=x', [], 'search', 'Bing'],
            'Gmail non e\' una ricerca' => ['https://mail.google.com/mail/u/0/', [], 'email', 'Gmail'],
            'Facebook mobile' => ['https://m.facebook.com/', [], 'social', 'Facebook'],
            'link corto di X' => ['https://t.co/abc', [], 'social', 'X (Twitter)'],
            'sito esterno' => ['https://www.blog-di-prova.it/articolo', [], 'referral', 'blog-di-prova.it'],
            'campagna' => [null, ['utm_source' => 'volantino', 'utm_medium' => 'cpc', 'utm_campaign' => 'autunno'], 'campaign', 'volantino'],
            'newsletter' => ['https://mail.google.com/', ['utm_source' => 'nl', 'utm_medium' => 'email'], 'email', 'nl'],
        ];
    }

    /* Report ---------------------------------------------------------------- */

    private function seedSessions(): void
    {
        // A: tre pagine, 30 secondi in tutto.
        $a = ['visitor' => 'a', 'session_id' => 'sa'];
        $this->row($a + ['path' => '/', 'is_entry' => true, 'channel' => 'search', 'source' => 'Google', 'country' => 'IT', 'duration' => 10]);
        $this->row($a + ['path' => '/aziende', 'duration' => 20]);
        $this->row($a + ['path' => '/aziende/rossi']);

        // B: una pagina sola, da telefono: rimbalzo.
        $this->row(['visitor' => 'b', 'session_id' => 'sb', 'path' => '/', 'is_entry' => true, 'channel' => 'direct', 'device' => 'mobile', 'browser' => 'Safari', 'os' => 'iOS']);

        // C: due pagine su un altro sito, da una campagna.
        $c = ['visitor' => 'c', 'session_id' => 'sc', 'host' => 'rete.example', 'day' => '2026-10-06', 'hour' => 21];
        $this->row($c + ['path' => '/', 'is_entry' => true, 'channel' => 'campaign', 'source' => 'volantino', 'campaign' => 'autunno', 'country' => 'DE', 'duration' => 30]);
        $this->row($c + ['path' => '/prodotti']);
    }

    public function test_le_schede_calcolano_visitatori_visite_pagine_durata_e_rimbalzo(): void
    {
        $this->seedSessions();

        $cards = collect((new Report($this->period()))->summary())->keyBy('key');

        $this->assertSame(3.0, $cards['visitors']['raw']);
        $this->assertSame(3.0, $cards['sessions']['raw']);
        $this->assertSame(6.0, $cards['views']['raw']);
        $this->assertSame(2.0, $cards['pages_per_session']['raw']);
        $this->assertSame(20.0, $cards['avg_duration']['raw']);
        $this->assertEqualsWithDelta(1 / 3, $cards['bounce_rate']['raw'], 0.0001);
        $this->assertNull($cards['views']['change'], 'senza periodo precedente non c\'e\' confronto');
        $this->assertSame('20s', $cards['avg_duration']['value']);
        $this->assertSame('33%', $cards['bounce_rate']['value']);
    }

    public function test_il_confronto_col_periodo_precedente(): void
    {
        $this->seedSessions();
        // Settimana prima: due pagine viste, una sola persona.
        $this->row(['visitor' => 'z', 'session_id' => 'sz', 'is_entry' => true, 'day' => '2026-09-28']);
        $this->row(['visitor' => 'z', 'session_id' => 'sz', 'day' => '2026-09-28']);

        $cards = collect((new Report($this->period()))->summary())->keyBy('key');

        $this->assertEqualsWithDelta(2.0, $cards['views']['change'], 0.0001, 'da 2 a 6 pagine: +200%');
        $this->assertEqualsWithDelta(2.0, $cards['visitors']['change'], 0.0001);
    }

    public function test_si_puo_guardare_un_solo_sito(): void
    {
        $this->seedSessions();

        $cards = collect((new Report($this->period(), 'rete.example'))->summary())->keyBy('key');

        $this->assertSame(2.0, $cards['views']['raw']);
        $this->assertSame(1.0, $cards['sessions']['raw']);
        $this->assertSame(0.0, $cards['bounce_rate']['raw']);
    }

    public function test_pagine_ingressi_provenienza_e_pubblico(): void
    {
        $this->seedSessions();
        $report = new Report($this->period());

        $home = $report->pages()->firstWhere('path', '/');
        $this->assertSame([3, 3, 3], [$home->views, $home->visitors, $home->entries]);
        $this->assertSame('/', $report->pages()->first()->path, 'la piu\' vista viene prima');

        $entry = $report->entryPages()->firstWhere('path', '/');
        $this->assertSame([3, 1], [$entry->entries, $entry->bounces]);

        $this->assertEquals(
            ['Diretto' => 1, 'Motori di ricerca' => 1, 'Campagne' => 1],
            $report->channels()->pluck('count', 'label')->all()
        );
        $this->assertSame('volantino', $report->campaigns()->first()->source);
        $this->assertSame('autunno', $report->campaigns()->first()->campaign);

        $countries = $report->countries()->pluck('count', 'label');
        $this->assertSame(1, $countries['Non rilevato']);
        $this->assertCount(3, $countries);

        $this->assertEquals(['Computer' => 2, 'Telefono' => 1], $report->devices()->pluck('count', 'label')->all());
        $this->assertSame(['ksm.it', 'rete.example'], $report->sites()->pluck('host')->all());
    }

    public function test_l_andamento_riempie_i_giorni_vuoti_e_somma_i_mesi(): void
    {
        $this->seedSessions();

        $days = (new Report($this->period()))->series();

        $this->assertCount(7, $days);
        $this->assertSame([0, 4, 2, 0], [$days[0]['views'], $days[4]['views'], $days[5]['views'], $days[6]['views']]);

        $months = (new Report(new Period(CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-10-07'), 'x')))->series('month');
        $this->assertCount(4, $months);
        $this->assertSame(6, array_sum(array_column($months, 'views')));
    }

    public function test_la_mappa_degli_orari_usa_giorno_della_settimana_e_ora_italiana(): void
    {
        $this->seedSessions();

        $heat = (new Report($this->period()))->heatmap();

        // 5 ottobre 2026 e' un lunedi', 6 un martedi'.
        $this->assertSame(4, $heat['cells'][0][10]);
        $this->assertSame(2, $heat['cells'][1][21]);
        $this->assertSame(4, $heat['max']);
    }

    public function test_chi_e_online_ora(): void
    {
        $this->seedSessions();
        $this->row(['visitor' => 'ora-1']);
        $this->row(['visitor' => 'ora-1']);
        $this->row(['visitor' => 'ora-2', 'host' => 'rete.example']);

        // Le righe di prova sono tutte "adesso" per created_at.
        $this->assertSame(5, (new Report($this->period()))->live());
        $this->assertSame(2, (new Report($this->period(), 'rete.example'))->live());
    }

    /* Pagina di amministrazione --------------------------------------------- */

    public function test_la_pagina_si_apre_con_e_senza_dati(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('Statistiche visite')
            ->assertSee('Nessuna visita in questo periodo');

        $this->seedSessions();

        $this->actingAs($admin)->get(route('admin.analytics.index', ['dal' => '2026-10-01', 'al' => '2026-10-07']))
            ->assertOk()
            ->assertSee('Pagine più viste')
            ->assertSee('/aziende/rossi')
            ->assertSee('Siti più visitati')
            ->assertSee('rete.example')
            ->assertSee('Quando arrivano')
            ->assertViewHas('summary', fn (array $cards) => $cards[2]['value'] === '6');
    }

    public function test_la_pagina_accetta_periodi_e_siti_strani_senza_rompersi(): void
    {
        $admin = $this->admin();
        $this->seedSessions();

        foreach ([
            ['periodo' => 'oggi'], ['periodo' => 'ieri'], ['periodo' => '7'], ['periodo' => '365'], ['periodo' => 'inventato'],
            ['dal' => '2026-10-07', 'al' => '2026-10-01'], ['dal' => 'ieri', 'al' => '31-02-2026'], ['dal' => '2026-02-31'],
            ['dal' => '1999-01-01', 'al' => '2999-01-01'], ['sito' => 'rete.example'], ['sito' => "x'; drop table page_views;--"],
        ] as $query) {
            $this->actingAs($admin)->get(route('admin.analytics.index', $query))->assertOk();
        }

        $this->assertSame(6, PageView::count());
    }

    public function test_serve_il_permesso_per_vedere_le_statistiche(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect(route('login'));

        $senza = $this->admin([Permissions::DASHBOARD_VIEW]);
        $this->actingAs($senza)->get(route('admin.analytics.index'))->assertForbidden();
        $this->actingAs($senza)->get(route('admin.analytics.export', 'pagine'))->assertForbidden();

        $con = $this->admin([Permissions::ANALYTICS_VIEW]);
        $this->actingAs($con)->get(route('admin.analytics.index'))->assertOk();
    }

    public function test_la_voce_di_menu_compare_solo_a_chi_ha_il_permesso(): void
    {
        $this->actingAs($this->admin([Permissions::DASHBOARD_VIEW]))->get(route('admin.dashboard'))
            ->assertOk()->assertDontSee('Statistiche visite');

        $this->actingAs($this->admin())->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Statistiche visite');
    }

    public function test_l_esportazione_in_csv(): void
    {
        $this->seedSessions();
        $admin = $this->admin();
        $query = ['dal' => '2026-10-01', 'al' => '2026-10-07'];

        $pagine = $this->actingAs($admin)->get(route('admin.analytics.export', ['type' => 'pagine'] + $query))
            ->assertOk()->assertDownload('statistiche-pagine-20261001-20261007.csv');
        $csv = $pagine->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBFPagina;", $csv);
        $this->assertStringContainsString('/aziende/rossi;1;1;0;0', $csv);

        $giorni = $this->actingAs($admin)->get(route('admin.analytics.export', ['type' => 'giorni'] + $query))->streamedContent();
        $this->assertSame(8, count(array_filter(explode("\n", $giorni))), 'intestazione e sette giorni');

        $sorgenti = $this->actingAs($admin)->get(route('admin.analytics.export', ['type' => 'sorgenti'] + $query))->assertOk();
        $this->assertStringContainsString('Campagne;volantino;1', $sorgenti->streamedContent());

        $this->actingAs($admin)->get('/amministrazione/statistiche/esporta/altro')->assertNotFound();
    }

    /* Pulizia --------------------------------------------------------------- */

    public function test_la_pulizia_cancella_solo_le_visite_oltre_la_conservazione(): void
    {
        $this->row(['day' => Period::today()->subDays(10)->toDateString()]);
        $this->row(['day' => Period::today()->subDays(500)->toDateString()]);

        $this->artisan('analytics:prune')->assertSuccessful();

        $this->assertSame(1, PageView::count());
        $this->assertSame(Period::today()->subDays(10)->toDateString(), PageView::sole()->day);
    }

    public function test_formati(): void
    {
        $this->assertSame('1m 15s', Format::duration(75));
        $this->assertSame('1h 1m', Format::duration(3700));
        $this->assertSame('0s', Format::duration(0));
        $this->assertSame('1.234', Format::number(1234));
        $this->assertSame('2,5', Format::decimal(2.5));
        $this->assertSame('4,5%', Format::percent(0.045));
        $this->assertSame('Non rilevato', Format::country(null));
    }
}
