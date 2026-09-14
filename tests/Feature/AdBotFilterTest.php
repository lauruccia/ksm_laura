<?php

namespace Tests\Feature;

use App\Models\Advertisement;
use App\Models\AdvertisementStat;
use App\Support\Ads\BotDetector;
use App\Support\Ads\Placements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chi compra a visualizzazioni o a clic paga solo quelli di persone.
 *
 * Qui si prova la parte del server: programmi che si dichiarano, browser
 * senza nome, la stessa persona che ricarica. Il secondo sullo schermo e i
 * browser pilotati li ferma ads.js, prima che la richiesta parta.
 */
class AdBotFilterTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';

    private function campaign(): Advertisement
    {
        return Advertisement::create([
            'name' => 'Kosmoprof',
            'link' => 'https://profumeriafiano.it/',
            'img' => 'advertisements/kosmoprof.png',
            'locations' => [Placements::HOME_BELOW_HEADER],
            'billing' => 'impressions',
            'max_impressions' => 1000,
            'status' => 1,
        ]);
    }

    private function stat(Advertisement $campaign): ?AdvertisementStat
    {
        return AdvertisementStat::where('advertisement_id', $campaign->id)->first();
    }

    public function test_riconosce_programmi_e_browser(): void
    {
        foreach ([
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
            'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)',
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
            'WhatsApp/2.23.20.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/140.0 Safari/537.36',
            'curl/8.4.0',
            'python-requests/2.31.0',
            '',
        ] as $userAgent) {
            $this->assertTrue(BotDetector::isBot($userAgent), "Doveva essere un programma: $userAgent");
        }

        foreach ([
            self::CHROME,
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0',
        ] as $userAgent) {
            $this->assertFalse(BotDetector::isBot($userAgent), "Doveva essere un browser: $userAgent");
        }
    }

    public function test_le_visualizzazioni_dei_programmi_si_scartano_ma_restano_scritte(): void
    {
        $campaign = $this->campaign();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])
            ->post($campaign->viewUrl())
            ->assertNoContent();

        $this->assertSame(0, $campaign->fresh()->impressions);
        $this->assertSame(1, $this->stat($campaign)->filtered_impressions);
        $this->assertSame(0, $this->stat($campaign)->impressions);
    }

    public function test_un_browser_senza_nome_non_conta(): void
    {
        $campaign = $this->campaign();

        $this->withHeaders(['User-Agent' => ''])->post($campaign->viewUrl())->assertNoContent();

        $this->assertSame(0, $campaign->fresh()->impressions);
    }

    public function test_la_stessa_persona_conta_una_volta_ogni_mezz_ora(): void
    {
        $campaign = $this->campaign();
        $browser = ['User-Agent' => self::CHROME];

        $this->withHeaders($browser)->post($campaign->viewUrl());
        $this->withHeaders($browser)->post($campaign->viewUrl());
        $this->assertSame(1, $campaign->fresh()->impressions);

        $this->travel(31)->minutes();

        $this->withHeaders($browser)->post($campaign->viewUrl());
        $this->assertSame(2, $campaign->fresh()->impressions);

        // Un'altra persona conta subito.
        $this->withHeaders($browser)
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post($campaign->viewUrl());
        $this->assertSame(3, $campaign->fresh()->impressions);
    }

    public function test_i_clic_dei_programmi_portano_al_sito_ma_non_si_pagano(): void
    {
        $campaign = $this->campaign();

        $this->withHeaders(['User-Agent' => 'curl/8.4.0'])
            ->get(route('ads.click', $campaign))
            ->assertRedirect('https://profumeriafiano.it/');

        $this->assertSame(0, $campaign->fresh()->clicks);
        $this->assertSame(1, $this->stat($campaign)->filtered_clicks);
    }

    public function test_gli_scartati_si_vedono_nelle_statistiche(): void
    {
        $campaign = $this->campaign();

        $this->withHeaders(['User-Agent' => 'curl/8.4.0'])->post($campaign->viewUrl());

        $role = \App\Models\Role::firstOrCreate(
            ['slug' => \App\Models\Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );
        $admin = \App\Models\User::create([
            'name' => 'Amministratore', 'email' => 'admin@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.advertisements.show', $campaign))
            ->assertOk()
            ->assertSee('Scartati come automatici')
            ->assertSee('1 visualizzazioni');
    }
}
