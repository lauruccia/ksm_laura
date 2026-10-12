<?php

namespace Tests\Feature;

use App\Models\Conversion;
use App\Models\PageView;
use App\Models\Role;
use App\Models\User;
use App\Support\Analytics\Humans;
use App\Support\Analytics\Insights;
use App\Support\Analytics\Period;
use App\Support\Analytics\Report;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Solo traffico probabilmente umano": cosa si nasconde e cosa resta.
 */
class HumanTrafficTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Humans::forget();
    }

    private function period(): Period
    {
        return new Period(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-07'), 'prova');
    }

    /** Una visita: la prima riga e' d'ingresso, le altre seguono. */
    private function visit(int $pages, int $seconds, ?string $country = 'IT', array $extra = []): string
    {
        $id = (string) Str::ulid();

        foreach (range(1, $pages) as $i) {
            PageView::create($extra + [
                'uid' => (string) Str::ulid(), 'host' => 'ksm.it', 'site_type' => 'platform',
                'path' => $i === 1 ? '/' : "/p$i", 'session_id' => $id, 'visitor' => sha1($id),
                'is_entry' => $i === 1, 'channel' => $i === 1 ? 'direct' : null, 'country' => $i === 1 ? $country : null,
                'device' => 'desktop', 'browser' => 'Chrome', 'os' => 'Windows',
                'duration' => $i === 1 ? $seconds : 0, 'day' => '2026-10-05', 'hour' => 10,
            ]);
        }

        return $id;
    }

    public function test_si_nascondono_le_visite_senza_tempo_e_le_scansioni(): void
    {
        $this->visit(2, 30);               // persona
        $this->visit(1, 4);                // persona, una sola pagina ma legge
        $this->visit(1, 0);                // nessun tempo
        $this->visit(Humans::MAX_PAGES + 1, 10); // scansione del sito

        $all = new Report($this->period());
        $human = new Report($this->period(), null, true);

        $this->assertEquals(4.0, $all->summary()[1]['raw']);
        $this->assertEquals(2.0, $human->summary()[1]['raw']);
        $this->assertEquals(3.0, $human->summary()[2]['raw']);
    }

    public function test_un_paese_dove_nessuno_legge_si_nasconde_ma_non_l_italia(): void
    {
        foreach (range(1, Humans::MIN_SESSIONS) as $i) {
            $this->visit(1, 0, 'US');
            $this->visit(1, 0, 'IT');
        }
        $this->visit(1, 20, 'US'); // una lettura "americana" fra tante
        $this->visit(1, 20, 'IT');
        $this->visit(2, 20, 'DE');

        $countries = Humans::suspectCountries($this->period(), null);
        $this->assertSame(['US'], $countries);

        $human = new Report($this->period(), null, true);
        $this->assertEquals(2.0, $human->summary()[1]['raw'], 'restano la persona italiana e quella tedesca');
    }

    public function test_un_paese_con_abbastanza_letture_non_e_sospetto(): void
    {
        foreach (range(1, 100) as $i) {
            $this->visit(1, $i <= 10 ? 15 : 0, 'BR'); // il 10% legge
        }

        $this->assertSame([], Humans::suspectCountries($this->period(), null));
    }

    public function test_gli_obiettivi_senza_visita_umana_si_nascondono(): void
    {
        $human = $this->visit(2, 30);
        $bot = $this->visit(1, 0);
        $base = ['host' => 'ksm.it', 'day' => '2026-10-05', 'hour' => 11];

        Conversion::create($base + ['goal' => 'order', 'session_id' => $human, 'value' => 10]);
        Conversion::create($base + ['goal' => 'contact', 'session_id' => $bot]);
        Conversion::create($base + ['goal' => 'contact', 'session_id' => null]);

        $all = collect((new Insights($this->period()))->goals())->keyBy('goal');
        $only = collect((new Insights($this->period(), null, true))->goals())->keyBy('goal');

        $this->assertSame(2, $all['contact']['count']);
        $this->assertSame(0, $only['contact']['count']);
        $this->assertSame(1, $only['order']['count']);
    }

    public function test_il_riepilogo_dice_quante_visite_si_nascondono(): void
    {
        $this->visit(1, 30);
        $this->visit(1, 0);
        $this->visit(1, 0);
        $this->visit(1, 0);

        $summary = Humans::summary($this->period(), null);

        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['hidden']);
        $this->assertEquals(0.75, $summary['share']);
    }

    public function test_la_pagina_con_l_interruttore(): void
    {
        $this->visit(1, 0, 'US', ['day' => now(config('ksm.analytics.timezone'))->toDateString()]);
        $this->visit(2, 30, 'IT', ['day' => now(config('ksm.analytics.timezone'))->toDateString()]);

        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);
        $admin = User::create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'password', 'user_type' => 'admin', 'is_active' => true, 'role_id' => $role->id]);

        $this->actingAs($admin)->get('/amministrazione/statistiche?periodo=7')
            ->assertOk()->assertSee('Solo traffico probabilmente umano')->assertSee('delle visite sembra automatico');

        $this->actingAs($admin)->get('/amministrazione/statistiche?periodo=7&umani=1')
            ->assertOk()->assertSee('Nascoste 1 visite su 2')->assertSee('umani=1', false);

        $this->actingAs($admin)->get('/amministrazione/statistiche/pagina?path=/&periodo=7&umani=1')->assertOk();
        $this->actingAs($admin)->get('/amministrazione/statistiche/esporta/pagine?periodo=7&umani=1')->assertOk();
    }
}
