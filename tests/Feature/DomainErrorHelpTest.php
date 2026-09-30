<?php

namespace Tests\Feature;

use App\Support\Domains\DomainErrorHelp;
use Tests\TestCase;

/**
 * Il "?" accanto agli errori dei domini: a ogni errore il consiglio giusto.
 */
class DomainErrorHelpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ksm.server.ips' => ['203.0.113.10'], 'ksm.server.cname' => null, 'ksm.whm.proxy_plan' => 'rivenditore_1MB']);
    }

    public function test_nessun_errore_nessun_consiglio(): void
    {
        $this->assertNull(DomainErrorHelp::for(null));
        $this->assertNull(DomainErrorHelp::for('  '));
    }

    /** @dataProvider errors */
    public function test_ogni_errore_ha_il_suo_consiglio(string $error, string $title, string $inSteps): void
    {
        $help = DomainErrorHelp::for($error);

        $this->assertSame($title, $help['title']);
        $this->assertNotEmpty($help['steps']);
        $this->assertStringContainsString($inSteps, implode(' ', $help['steps']));
    }

    public function test_il_punto_di_domanda_si_vede_accanto_all_errore(): void
    {
        $html = view('admin.domains._error-help', ['error' => 'Il dominio punta a 1.2.3.4, non al server.'])->render();

        $this->assertStringContainsString('data-errhelp-btn', $html);
        $this->assertStringContainsString('Il DNS punta a un altro server', $html);
        $this->assertStringContainsString('203.0.113.10', $html);
        $this->assertSame('', trim(view('admin.domains._error-help', ['error' => null])->render()));
    }

    public static function errors(): array
    {
        return [
            'dns altrove' => ['Il dominio punta a 1.2.3.4, non al server.', 'Il DNS punta a un altro server', '203.0.113.10'],
            'dns assente' => ['Nessun record DNS trovato per il dominio.', 'Il dominio non ha record DNS', '203.0.113.10'],
            'certificato' => ['Certificato non ancora valido: handshake failed', 'Il DNS è giusto, manca ancora il certificato', 'AutoSSL'],
            'pacchetto' => ['Account proxy non creato: Sorry, unable to use package rivenditore_1MB (pacchetto "rivenditore_1MB": controllalo)', 'La WHM rifiuta il pacchetto', 'rivenditore_1MB'],
            'gia\' presente' => ['Account proxy non creato: Sorry, the domain esempio.it already exists.', 'Il dominio c\'è già sul server', 'Elenca account'],
            'whm giu\'' => ['WHM non risponde: cURL error 28', 'Il pannello dell\'hosting non risponde', 'Prova la connessione'],
            'file mancanti' => ["Account proxy esempio creato, ma index.php non e' stato scritto: HTTP 500", 'Account creato, ma i file del collegamento mancano', 'Ricollega'],
            'pacchetto inesistente' => ['Il pacchetto x non esiste tra quelli del rivenditore.', 'Il pacchetto impostato non esiste', 'Server e hosting'],
            'parcheggio' => ['WHM non ha parcheggiato il dominio: denied', 'Il pannello non ha aggiunto il dominio', 'Elenca account'],
            'nome' => ['Nome di dominio non valido.', 'Il nome del dominio è scritto male', 'Modifica'],
            'server' => ['Indirizzo del server non configurato: manca KSM_SERVER_IPS.', 'Manca l\'indirizzo del server', 'KSM_SERVER_IPS'],
            'sconosciuto' => ['Qualcosa di strano', 'Come provare a risolvere', 'Verifica'],
        ];
    }
}
