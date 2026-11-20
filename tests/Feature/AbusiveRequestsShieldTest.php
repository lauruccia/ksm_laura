<?php

namespace Tests\Feature;

use Tests\TestCase;

class AbusiveRequestsShieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ksm.shield.enabled' => true,
            'ksm.shield.store' => 'array',
            'ksm.shield.rate_per_minute' => 5,
        ]);
    }

    public function test_un_indirizzo_con_codice_dentro_viene_respinto(): void
    {
        foreach ([
            '/?cerca='.rawurlencode('\'><asdf alt="">'),
            '/?cerca=%2527%253e%253casdf%2520alt%253d%2522%2522%253e',
            '/?cerca='.rawurlencode('1 union select password from users'),
            '/?cerca='.rawurlencode('../../etc/passwd'),
            '/?cerca='.rawurlencode('\x27\x3e\x3casdf'),
            '/?cerca='.rawurlencode('&#x27;&gt;&lt;asdf'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_le_ricerche_vere_passano(): void
    {
        foreach (['dell\'olio', 'amministratori di condominio', 'caffè & bar', 'pizza 2x1'] as $term) {
            $this->get('/?cerca='.rawurlencode($term))->assertOk();
        }
    }

    public function test_gli_strumenti_di_scansione_sono_respinti_ma_i_motori_veri_no(): void
    {
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; AhrefsBot/7.0)'])->assertForbidden();
        $this->get('/', ['User-Agent' => 'sqlmap/1.7'])->assertForbidden();
        $this->get('/', ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_robots_txt_resta_raggiungibile_anche_dai_bot_respinti(): void
    {
        $this->get('/robots.txt', ['User-Agent' => 'AhrefsBot/7.0'])->assertOk()->assertSee('User-agent: AhrefsBot');
    }

    public function test_troppe_pagine_dallo_stesso_ip_danno_429(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->get('/')->assertOk();
        }

        $this->get('/')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_lo_scudo_spento_lascia_passare_tutto(): void
    {
        config(['ksm.shield.enabled' => false]);

        $this->get('/?cerca='.rawurlencode('<script>'))->assertOk();
    }
}
