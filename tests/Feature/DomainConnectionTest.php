<?php

namespace Tests\Feature;

use App\Jobs\RegisterDomainOnHostingPanel;
use App\Jobs\RemoveDomainFromHostingPanel;
use App\Models\Company;
use App\Models\Domain;
use App\Models\Role;
use App\Models\User;
use App\Support\Domains\DnsResolver;
use App\Support\Domains\DomainConnectionChecker;
use App\Support\Domains\HostingPanel;
use App\Support\Domains\TlsProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Collegamento dei domini: DNS verso il server, certificato valido.
 *
 * DNS e certificato sono finti: si prova il nostro giudizio sulle
 * risposte, non la rete.
 */
class DomainConnectionTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_IP = '203.0.113.10';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ksm.server.ips' => [self::SERVER_IP], 'ksm.server.cname' => null]);
    }

    private function fakeNetwork(array $addresses, ?string $tlsError = null, ?string $cname = null): void
    {
        $this->app->instance(DnsResolver::class, new class($addresses, $cname) implements DnsResolver
        {
            public function __construct(private array $records, private ?string $target) {}

            public function addresses(string $host): array
            {
                return $this->records;
            }

            public function cname(string $host): ?string
            {
                return $this->target;
            }
        });

        $this->app->instance(TlsProbe::class, new class($tlsError) implements TlsProbe
        {
            public function __construct(private ?string $error) {}

            public function check(string $host): ?string
            {
                return $this->error;
            }
        });
    }

    private function domain(string $host = 'mangiareebere.it'): Domain
    {
        return Domain::create(['name' => $host, 'domain' => $host, 'type' => 'home', 'is_active' => true]);
    }

    private function company(array $attributes = []): Company
    {
        $owner = User::create([
            'name' => 'Titolare',
            'email' => 'titolare'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'vendor',
        ]);

        return Company::create($attributes + [
            'user_id' => $owner->id,
            'name' => 'Decina Bus',
            'slug' => 'decina-bus-'.uniqid(),
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(
            ['slug' => Role::SUPER_ADMIN],
            ['name' => 'Super amministratore', 'is_system' => true]
        );

        return User::create([
            'name' => 'Amministratore',
            'email' => 'admin'.uniqid().'@example.test',
            'password' => 'password',
            'user_type' => 'admin',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    public function test_dns_verso_il_server_e_certificato_valido_vuol_dire_collegato(): void
    {
        $this->fakeNetwork([self::SERVER_IP]);
        $domain = $this->domain();

        $result = app(DomainConnectionChecker::class)->refresh($domain, $domain->domain);

        $this->assertTrue($result->connected());
        $this->assertSame('Collegato', $domain->fresh()->connectionLabel());
        $this->assertNull($domain->fresh()->domain_error);
    }

    public function test_un_dns_che_punta_altrove_viene_spiegato(): void
    {
        $this->fakeNetwork(['198.51.100.7']);
        $domain = $this->domain();

        app(DomainConnectionChecker::class)->refresh($domain, $domain->domain);

        $this->assertSame('DNS da configurare', $domain->fresh()->connectionLabel());
        $this->assertStringContainsString('198.51.100.7', $domain->fresh()->domain_error);
    }

    public function test_un_cname_verso_la_piattaforma_vale_come_dns_giusto(): void
    {
        config(['ksm.server.cname' => 'ksm.it']);
        $this->fakeNetwork([], null, 'ksm.it.');

        $this->assertTrue(app(DomainConnectionChecker::class)->check('mangiareebere.it')->dns);
    }

    public function test_dns_giusto_senza_certificato_resta_in_attesa(): void
    {
        $this->fakeNetwork([self::SERVER_IP], 'certificate verify failed');
        $domain = $this->domain();

        app(DomainConnectionChecker::class)->refresh($domain, $domain->domain);

        $this->assertSame('Certificato in attesa', $domain->fresh()->connectionLabel());
        $this->assertNotNull($domain->fresh()->dns_verified_at);
    }

    public function test_senza_indirizzo_del_server_non_si_da_per_buono_niente(): void
    {
        config(['ksm.server.ips' => [], 'ksm.server.cname' => null]);
        $this->fakeNetwork([self::SERVER_IP]);

        $result = app(DomainConnectionChecker::class)->check('mangiareebere.it');

        $this->assertFalse($result->connected());
        $this->assertStringContainsString('KSM_SERVER_IPS', $result->error);
    }

    public function test_il_giro_verifica_domini_della_rete_e_domini_delle_aziende(): void
    {
        $this->fakeNetwork([self::SERVER_IP]);
        $domain = $this->domain();
        $company = $this->company(['custom_domain' => 'decinabus.it']);
        $withoutDomain = $this->company();

        $this->artisan('domains:check')->assertSuccessful();

        $this->assertTrue($domain->fresh()->isConnected());
        $this->assertTrue($company->fresh()->isConnected());
        $this->assertNull($withoutDomain->fresh()->domain_checked_at);
    }

    public function test_il_server_web_ha_il_permesso_solo_per_i_domini_della_piattaforma(): void
    {
        $this->domain('mangiareebere.it');
        $this->company(['custom_domain' => 'decinabus.it']);

        $this->get('/tls/autorizza?domain=www.mangiareebere.it')->assertOk();
        $this->get('/tls/autorizza?domain=decinabus.it')->assertOk();
        $this->get('/tls/autorizza?domain=dominio-di-altri.it')->assertNotFound();
    }

    public function test_da_fuori_non_si_chiede_niente(): void
    {
        $this->domain('mangiareebere.it');

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.99'])
            ->get('/tls/autorizza?domain=mangiareebere.it')
            ->assertForbidden();
    }

    public function test_verifica_ora_dall_amministrazione(): void
    {
        $this->fakeNetwork([self::SERVER_IP]);
        $domain = $this->domain();

        $this->actingAs($this->admin())
            ->patch(route('admin.domains.check', $domain))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($domain->fresh()->isConnected());
    }

    public function test_la_scheda_azienda_salva_il_dominio_ripulito_e_azzera_la_verifica(): void
    {
        $company = $this->company([
            'custom_domain' => 'vecchio-dominio.it',
            'dns_verified_at' => now(),
            'ssl_verified_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.companies.update', $company), [
                'name' => $company->name,
                'login_email' => $company->user->email,
                'custom_domain' => 'https://www.DecinaBus.it/contatti',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $company->refresh();

        $this->assertSame('decinabus.it', $company->custom_domain);
        $this->assertNull($company->dns_verified_at);
        $this->assertSame('Da verificare', $company->connectionLabel());
    }

    /** Un pannello finto che conta le chiamate: con la WHM vera ogni ensure fa ripartire Apache. */
    private function fakePanel(): object
    {
        $panel = new class implements HostingPanel
        {
            public array $ensured = [];

            public function ensure(string $host): ?string
            {
                $this->ensured[] = $host;

                return null;
            }

            public array $removed = [];

            public function remove(string $host): ?string
            {
                $this->removed[] = $host;

                return null;
            }

            public function requestCertificate(): void {}
        };
        $this->app->instance(HostingPanel::class, $panel);

        return $panel;
    }

    public function test_un_dominio_salvato_va_sul_pannello_dalla_coda_non_dalla_richiesta(): void
    {
        Queue::fake();
        $panel = $this->fakePanel();

        $domain = $this->domain('ristoranticalabria.com');

        $this->assertSame([], $panel->ensured);
        Queue::assertPushed(RegisterDomainOnHostingPanel::class,
            fn ($job) => $job->modelClass === Domain::class && $job->modelId === $domain->id && $job->host === 'ristoranticalabria.com');
    }

    public function test_il_lavoro_in_coda_registra_il_dominio_e_lo_verifica(): void
    {
        $this->fakeNetwork([self::SERVER_IP]);
        Queue::fake();
        $panel = $this->fakePanel();
        $domain = $this->domain('ristoranticalabria.com');

        app()->call([new RegisterDomainOnHostingPanel(Domain::class, $domain->id, 'ristoranticalabria.com'), 'handle']);

        $this->assertSame(['ristoranticalabria.com'], $panel->ensured);
        $this->assertTrue($domain->fresh()->isConnected());
    }

    public function test_il_lavoro_in_coda_ignora_un_dominio_cambiato_nel_frattempo(): void
    {
        Queue::fake();
        $panel = $this->fakePanel();
        $domain = $this->domain('ristoranticalabria.com');

        app()->call([new RegisterDomainOnHostingPanel(Domain::class, $domain->id, 'vecchio.it'), 'handle']);

        $this->assertSame([], $panel->ensured);
    }

    public function test_verifica_ora_con_il_pannello_controlla_subito_e_registra_dalla_coda(): void
    {
        $this->fakeNetwork([self::SERVER_IP], 'certificato scaduto');
        Queue::fake();
        $panel = $this->fakePanel();
        $domain = $this->domain();

        $this->actingAs($this->admin())
            ->patch(route('admin.domains.check', $domain))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'entro un paio di minuti'));

        $this->assertSame([], $panel->ensured);
        Queue::assertPushed(RegisterDomainOnHostingPanel::class, 2);
    }

    public function test_eliminare_un_dominio_lo_toglie_dal_pannello_dalla_coda(): void
    {
        Queue::fake();
        $this->fakePanel();
        $domain = $this->domain('ristoranticalabria.com');

        $domain->delete();

        Queue::assertPushed(RemoveDomainFromHostingPanel::class, fn ($job) => $job->host === 'ristoranticalabria.com');
    }

    public function test_cambiare_indirizzo_toglie_il_vecchio_dal_pannello(): void
    {
        Queue::fake();
        $this->fakePanel();
        $domain = $this->domain('vecchio.it');

        $domain->update(['domain' => 'nuovo.it']);

        Queue::assertPushed(RemoveDomainFromHostingPanel::class, fn ($job) => $job->host === 'vecchio.it');
        Queue::assertNotPushed(RemoveDomainFromHostingPanel::class, fn ($job) => $job->host === 'nuovo.it');
    }

    public function test_il_lavoro_di_rimozione_non_tocca_un_indirizzo_ancora_usato(): void
    {
        Queue::fake();
        $panel = $this->fakePanel();
        $this->domain('ristoranticalabria.com');

        app()->call([new RemoveDomainFromHostingPanel('ristoranticalabria.com'), 'handle']);
        $this->assertSame([], $panel->removed);

        app()->call([new RemoveDomainFromHostingPanel('eliminato.it'), 'handle']);
        $this->assertSame(['eliminato.it'], $panel->removed);
    }
}
