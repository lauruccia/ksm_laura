<?php

namespace Tests\Feature;

use App\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Il traffico non umano resta fuori dalle statistiche: chi non manda la
 * lingua del browser non si conta, e le sue visite vecchie si cancellano.
 */
class NonHumanTrafficTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';

    public function test_chi_si_finge_un_browser_senza_lingua_non_si_conta(): void
    {
        config(['ksm.analytics.require_browser_headers' => true]);

        $this->withHeaders(['User-Agent' => self::CHROME])->get('/contatti');
        $this->assertSame(0, PageView::count());

        $this->withHeaders(['User-Agent' => self::CHROME, 'Accept-Language' => 'it-IT,it;q=0.9'])->get('/contatti');
        $this->assertSame(1, PageView::count());
    }

    public function test_il_controllo_si_puo_spegnere(): void
    {
        config(['ksm.analytics.require_browser_headers' => false]);

        $this->withHeaders(['User-Agent' => self::CHROME])->get('/contatti');

        $this->assertSame(1, PageView::count());
    }

    private function visit(string $day, int $pages, int $seconds): string
    {
        $id = (string) Str::ulid();

        foreach (range(1, $pages) as $i) {
            PageView::create([
                'uid' => (string) Str::ulid(), 'host' => 'ksm.it', 'site_type' => 'platform',
                'path' => $i === 1 ? '/' : "/p$i", 'session_id' => $id, 'visitor' => sha1($id),
                'is_entry' => $i === 1, 'device' => 'desktop', 'browser' => 'Chrome', 'os' => 'Windows',
                'duration' => $i === 1 ? $seconds : 0, 'day' => $day, 'hour' => 10,
            ]);
        }

        return $id;
    }

    public function test_la_pulizia_toglie_le_visite_non_umane_vecchie_e_lascia_le_altre(): void
    {
        Cache::forget('analytics:bots-pruned-until');
        $old = now(config('ksm.analytics.timezone'))->subDays(20)->toDateString();
        $recent = now(config('ksm.analytics.timezone'))->subDay()->toDateString();

        $person = $this->visit($old, 2, 30);
        $this->visit($old, 1, 0);          // nessun tempo di lettura
        $this->visit($old, 41, 10);        // scansione del sito
        $this->visit($recent, 1, 0);       // non umana ma recente

        Artisan::call('analytics:prune', ['--bots-days' => 7]);

        $this->assertSame(2, PageView::where('day', $old)->count());
        $this->assertSame([$person], PageView::where('day', $old)->pluck('session_id')->unique()->values()->all());
        $this->assertSame(1, PageView::where('day', $recent)->count());
    }

    public function test_senza_giorni_indicati_la_pulizia_delle_visite_non_umane_e_spenta(): void
    {
        $old = now(config('ksm.analytics.timezone'))->subDays(20)->toDateString();
        $this->visit($old, 1, 0);

        Artisan::call('analytics:prune');

        $this->assertSame(1, PageView::count());
    }
}
