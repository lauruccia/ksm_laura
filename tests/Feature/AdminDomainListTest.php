<?php

namespace Tests\Feature;

use App\Jobs\RegisterDomainOnHostingPanel;
use App\Models\Domain;
use App\Models\HostingSetting;
use App\Models\Role;
use App\Models\User;
use App\Support\Domains\HostingPanel;
use App\Support\Domains\NoHostingPanel;
use App\Support\Domains\WhmHostingPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * L'elenco dei domini con centinaia di righe: filtri, ordinamento, azioni in
 * blocco (ricollega, verifica) e la pagina Server e hosting per traslocare.
 */
class AdminDomainListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['ksm.whm' => ['url' => null, 'reseller' => null, 'token' => null, 'account' => null], 'ksm.cpanel' => ['url' => null, 'user' => null, 'token' => null]]);
        $this->app->forgetInstance(HostingPanel::class);
    }

    private function admin(): User
    {
        $role = Role::firstOrCreate(['slug' => Role::SUPER_ADMIN], ['name' => 'Super amministratore', 'is_system' => true]);

        return User::create([
            'name' => 'Amministratore', 'email' => 'admin'.uniqid().'@example.test', 'password' => 'password',
            'user_type' => 'admin', 'role_id' => $role->id, 'is_active' => true,
        ]);
    }

    private function domain(string $host, array $state = []): Domain
    {
        $domain = Domain::create(['name' => $host, 'domain' => $host, 'type' => 'home', 'is_active' => true]);
        $domain->forceFill($state)->saveQuietly();

        return $domain;
    }

    private function network(): array
    {
        return [
            'collegato' => $this->domain('collegato.it', ['domain_checked_at' => now(), 'dns_verified_at' => now(), 'ssl_verified_at' => now()]),
            'certificato' => $this->domain('certificato.it', ['domain_checked_at' => now(), 'dns_verified_at' => now()]),
            'dns' => $this->domain('dns.it', ['domain_checked_at' => now(), 'domain_error' => 'Nessun record DNS trovato per il dominio.']),
            'nuovo' => $this->domain('nuovo.it'),
        ];
    }

    public function test_i_filtri_per_stato_e_i_contatori(): void
    {
        $this->network();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.domains.index', ['stato' => 'certificato']))
            ->assertOk()
            ->assertSee('certificato.it')
            ->assertDontSee('collegato.it')
            ->assertViewHas('counts', ['errore' => 1, 'da_verificare' => 1, 'dns' => 1, 'certificato' => 1, 'collegato' => 1]);

        $this->actingAs($admin)->get(route('admin.domains.index', ['stato' => 'dns']))
            ->assertSee('dns.it')->assertSee('Nessun record DNS')->assertDontSee('nuovo.it');

        $this->actingAs($admin)->get(route('admin.domains.index', ['cerca' => 'nuov']))
            ->assertSee('nuovo.it')->assertDontSee('dns.it');
    }

    public function test_l_ordinamento_per_stato_mette_prima_quelli_da_sistemare(): void
    {
        $this->network();

        $records = $this->actingAs($this->admin())->get(route('admin.domains.index', ['ordina' => 'stato']))
            ->assertOk()->viewData('records');

        $this->assertSame(['nuovo.it', 'dns.it', 'certificato.it', 'collegato.it'], $records->pluck('domain')->all());
    }

    public function test_ricollega_tutti_i_risultati_mette_in_coda_ogni_dominio(): void
    {
        $this->network();

        $this->actingAs($this->admin())
            ->patch(route('admin.domains.bulk'), ['action' => 'reconnect', 'scope' => 'all'])
            ->assertRedirect()->assertSessionHas('success');

        Queue::assertPushed(RegisterDomainOnHostingPanel::class, 4);
        Queue::assertPushed(RegisterDomainOnHostingPanel::class, fn ($job) => $job->register === true);
    }

    public function test_verifica_in_blocco_segue_i_filtri_e_non_tocca_il_pannello(): void
    {
        $this->network();

        $this->actingAs($this->admin())
            ->patch(route('admin.domains.bulk'), ['action' => 'verify', 'scope' => 'all', 'stato' => 'certificato'])
            ->assertRedirect();

        Queue::assertPushed(RegisterDomainOnHostingPanel::class, 1);
        Queue::assertPushed(RegisterDomainOnHostingPanel::class, fn ($job) => $job->host === 'certificato.it' && $job->register === false);
    }

    public function test_spegnere_in_blocco(): void
    {
        $domains = $this->network();

        $this->actingAs($this->admin())
            ->patch(route('admin.domains.bulk'), ['action' => 'deactivate', 'ids' => [$domains['dns']->id, $domains['nuovo']->id]])
            ->assertRedirect();

        $this->assertFalse($domains['dns']->fresh()->is_active);
        $this->assertTrue($domains['collegato']->fresh()->is_active);
    }

    public function test_server_e_hosting_prende_il_posto_del_env(): void
    {
        $this->actingAs($this->admin())->get(route('admin.domains.hosting'))->assertOk()->assertSee('Traslocare su un altro server');

        $this->actingAs($this->admin())->put(route('admin.domains.hosting.update'), [
            'panel' => 'whm',
            'server_ips' => '203.0.113.20',
            'whm_url' => 'https://nuovo.test:2087',
            'whm_reseller' => 'rivenditore',
            'whm_token' => 'SEGRETO',
            'whm_account' => 'ksm',
            'whm_proxy_plan' => 'rivenditore_2Mb',
        ])->assertRedirect(route('admin.domains.hosting'));

        $this->assertNotSame('SEGRETO', \DB::table('hosting_settings')->value('whm_token'));

        $this->app->forgetInstance(HostingPanel::class);
        $this->assertInstanceOf(WhmHostingPanel::class, app(HostingPanel::class));
        $this->assertSame(['203.0.113.20'], config('ksm.server.ips'));
        $this->assertSame('rivenditore_2Mb', config('ksm.whm.proxy_plan'));

        // Un token vuoto tiene quello salvato.
        $this->actingAs($this->admin())->put(route('admin.domains.hosting.update'), [
            'panel' => 'whm', 'server_ips' => '203.0.113.20', 'whm_url' => 'https://nuovo.test:2087',
            'whm_reseller' => 'rivenditore', 'whm_token' => '', 'whm_account' => 'ksm',
        ])->assertSessionHasNoErrors();
        $this->assertSame('SEGRETO', HostingSetting::current()->whm_token);
    }

    public function test_nessun_pannello_e_ip_sbagliati(): void
    {
        $this->actingAs($this->admin())->put(route('admin.domains.hosting.update'), ['panel' => 'none', 'server_ips' => 'non-un-ip'])
            ->assertSessionHasErrors('server_ips');

        $this->actingAs($this->admin())->put(route('admin.domains.hosting.update'), ['panel' => 'none', 'server_ips' => '203.0.113.30'])
            ->assertSessionHasNoErrors();

        $this->app->forgetInstance(HostingPanel::class);
        $this->assertInstanceOf(NoHostingPanel::class, app(HostingPanel::class));

        $this->actingAs($this->admin())->patch(route('admin.domains.hosting.test'))->assertSessionHas('success');
    }
}
