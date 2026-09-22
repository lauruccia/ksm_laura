<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Support\Domains\CpanelHostingPanel;
use App\Support\Domains\DnsResolver;
use App\Support\Domains\DomainConnectionChecker;
use App\Support\Domains\HostingPanel;
use App\Support\Domains\NoHostingPanel;
use App\Support\Domains\TlsProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * I domini dell'app aggiunti da soli all'account cPanel.
 *
 * cPanel e' finto: si prova cosa gli chiediamo e come leggiamo le risposte.
 */
class CpanelHostingPanelTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://cpanel.test:2083';

    private function panel(): CpanelHostingPanel
    {
        return new CpanelHostingPanel(self::URL, 'hnksmsho', 'TOKEN', 'ksm-next/public');
    }

    private function fakeCpanel(array $addons = [], array $addResult = ['result' => 1, 'reason' => 'ok']): void
    {
        Http::fake([
            self::URL.'/execute/DomainInfo/list_domains' => Http::response(['data' => [
                'main_domain' => 'ksmshop.it',
                'addon_domains' => $addons,
                'parked_domains' => [],
                'sub_domains' => ['ksm.ksmshop.it'],
            ]]),
            self::URL.'/json-api/cpanel*' => Http::response(['cpanelresult' => ['data' => [$addResult]]]),
            self::URL.'/execute/SSL/start_autossl_check' => Http::response(['status' => 1]),
        ]);
    }

    public function test_a_missing_domain_is_added_with_the_app_folder_and_a_certificate_is_requested(): void
    {
        $this->fakeCpanel();

        $this->assertNull($this->panel()->ensure('CittaDiOstia.it'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'addaddondomain')
            && $request['newdomain'] === 'cittadiostia.it'
            && $request['subdomain'] === 'cittadiostia-it'
            && $request['dir'] === 'ksm-next/public'
            && $request->hasHeader('Authorization', 'cpanel hnksmsho:TOKEN'));
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'start_autossl_check'));
    }

    public function test_a_domain_already_on_the_account_is_left_alone(): void
    {
        $this->fakeCpanel(['cittadiostia.it']);

        $panel = $this->panel();
        $this->assertNull($panel->ensure('cittadiostia.it'));
        $this->assertNull($panel->ensure('ksm.ksmshop.it'));

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'addaddondomain'));
        Http::assertSentCount(1);
    }

    public function test_a_refusal_comes_back_as_the_reason(): void
    {
        $this->fakeCpanel(addResult: ['result' => 0, 'reason' => 'The domain already exists in the DNS cluster.']);

        $this->assertSame(
            'cPanel non ha aggiunto il dominio: The domain already exists in the DNS cluster.'
                .' Come alias: The domain already exists in the DNS cluster.',
            $this->panel()->ensure('cittadiostia.it')
        );
    }

    public function test_without_addon_domains_left_the_domain_becomes_an_alias(): void
    {
        Http::fake([
            self::URL.'/execute/DomainInfo/list_domains' => Http::response(['data' => ['main_domain' => 'ksmshop.it']]),
            self::URL.'/json-api/cpanel*' => Http::sequence()
                ->push(['cpanelresult' => ['data' => [['result' => 0, 'reason' => 'Your addon domain limit of 0 addon domains has been reached.']]]])
                ->push(['cpanelresult' => ['data' => [['result' => 1, 'reason' => 'ok']]]]),
            self::URL.'/execute/SSL/start_autossl_check' => Http::response(['status' => 1]),
        ]);

        $this->assertNull($this->panel()->ensure('cittadiostia.it'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'cpanel_jsonapi_func=park')
            && $request['domain'] === 'cittadiostia.it');
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'start_autossl_check'));
    }

    public function test_the_check_records_the_panel_refusal_on_the_domain(): void
    {
        $this->fakeCpanel(addResult: ['result' => 0, 'reason' => 'Rifiutato.']);
        config(['ksm.server.ips' => ['203.0.113.10']]);

        $dns = new class implements DnsResolver
        {
            public function addresses(string $host): array
            {
                return ['198.51.100.1'];
            }

            public function cname(string $host): ?string
            {
                return null;
            }
        };
        $tls = new class implements TlsProbe
        {
            public function check(string $host): ?string
            {
                return null;
            }
        };

        $domain = Domain::create(['name' => 'Ostia', 'domain' => 'cittadiostia.it', 'type' => 'home', 'is_active' => true]);

        (new DomainConnectionChecker($dns, $tls, $this->panel()))->refresh($domain, 'cittadiostia.it');

        $this->assertSame('cPanel non ha aggiunto il dominio: Rifiutato. Come alias: Rifiutato.', $domain->fresh()->domain_error);
    }

    public function test_the_hourly_check_does_not_add_domains_to_the_panel(): void
    {
        $panel = new class implements HostingPanel
        {
            public array $ensured = [];

            public function ensure(string $host): ?string
            {
                $this->ensured[] = $host;

                return null;
            }

            public function requestCertificate(): void
            {
            }
        };
        $this->app->instance(HostingPanel::class, $panel);
        config(['ksm.server.ips' => ['203.0.113.10']]);

        Domain::withoutEvents(fn () => Domain::create(['name' => 'Ostia', 'domain' => 'cittadiostia.invalid', 'type' => 'home', 'is_active' => true]));

        $this->artisan('domains:check')->assertSuccessful();

        $this->assertSame([], $panel->ensured);
    }

    public function test_without_a_token_there_is_no_panel(): void
    {
        config(['ksm.cpanel.token' => null]);
        $this->app->forgetInstance(HostingPanel::class);

        $this->assertInstanceOf(NoHostingPanel::class, app(HostingPanel::class));
    }
}
