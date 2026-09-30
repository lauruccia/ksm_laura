<?php

namespace Tests\Feature;

use App\Support\Domains\HostingPanel;
use App\Support\Domains\WhmHostingPanel;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * I domini parcheggiati dalla WHM del rivenditore sull'account dell'app.
 *
 * La WHM e' finta: si prova cosa le chiediamo e come leggiamo le risposte.
 */
class WhmHostingPanelTest extends TestCase
{
    private const URL = 'https://whm.test:2087';

    private function panel(): WhmHostingPanel
    {
        return new WhmHostingPanel(self::URL, 'rivenditore', 'TOKEN', 'ilnetwork');
    }

    private function fakeWhm(array $parked = [], array $parkMeta = ['result' => 1, 'reason' => 'OK']): void
    {
        Http::fake([
            self::URL.'/json-api/cpanel?*list_domains*' => Http::response(['result' => ['data' => [
                'main_domain' => 'ilnetworkmarketing.it',
                'addon_domains' => [],
                'parked_domains' => $parked,
                'sub_domains' => [],
            ]]]),
            self::URL.'/json-api/cpanel?*start_autossl_check*' => Http::response(['result' => ['status' => 1]]),
            self::URL.'/json-api/create_parked_domain_for_user*' => Http::response(['metadata' => $parkMeta]),
        ]);
    }

    public function test_a_missing_domain_is_parked_on_the_app_account(): void
    {
        $this->fakeWhm();

        $this->assertNull($this->panel()->ensure('CittaDiOstia.it'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'create_parked_domain_for_user')
            && $request['domain'] === 'cittadiostia.it'
            && $request['username'] === 'ilnetwork'
            && $request['web_vhost_domain'] === 'ilnetworkmarketing.it'
            && $request->hasHeader('Authorization', 'whm rivenditore:TOKEN'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'start_autossl_check')
            && $request['cpanel_jsonapi_user'] === 'ilnetwork');
    }

    public function test_a_domain_already_parked_is_left_alone(): void
    {
        $this->fakeWhm(['cittadiostia.it']);

        $this->assertNull($this->panel()->ensure('cittadiostia.it'));

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'create_parked_domain_for_user'));
    }

    public function test_a_refusal_comes_back_as_the_reason(): void
    {
        $this->fakeWhm(parkMeta: ['result' => 0, 'reason' => 'The domain already exists.']);

        $this->assertSame('WHM non ha parcheggiato il dominio: The domain already exists.', $this->panel()->ensure('cittadiostia.it'));
    }

    public function test_the_whm_wins_over_cpanel_when_both_are_configured(): void
    {
        config([
            'ksm.whm' => ['url' => self::URL, 'reseller' => 'rivenditore', 'token' => 'T', 'account' => 'ilnetwork'],
            'ksm.cpanel' => ['url' => 'https://cpanel.test:2083', 'user' => 'u', 'token' => 'T', 'docroot' => 'x'],
        ]);
        $this->app->forgetInstance(HostingPanel::class);

        $this->assertInstanceOf(WhmHostingPanel::class, app(HostingPanel::class));
    }

    private function proxyPanel(): WhmHostingPanel
    {
        return new WhmHostingPanel(self::URL, 'rivenditore', 'TOKEN', 'ilnetwork', 'rivenditore_5Mb', 'https://ksm.it/', 'info@ksm.it');
    }

    private function fakeFullWhm(array $accounts = [], array $taken = []): void
    {
        Http::fake(function (Request $request) use ($accounts, $taken) {
            $url = $request->url();

            return match (true) {
                str_contains($url, 'list_domains') => Http::response(['result' => ['data' => [
                    'main_domain' => 'ilnetworkmarketing.it', 'addon_domains' => [], 'parked_domains' => [], 'sub_domains' => [],
                ]]]),
                str_contains($url, 'create_parked_domain_for_user') => Http::response(['metadata' => [
                    'result' => 0, 'reason' => 'You have reached the maximum number of domains.',
                ]]),
                str_contains($url, 'listaccts') => Http::response(['data' => ['acct' => $accounts]]),
                str_contains($url, 'verify_new_username') => Http::response(['metadata' => [
                    'result' => in_array($request['user'], $taken, true) ? 0 : 1,
                ]]),
                str_contains($url, 'createacct') => Http::response(['metadata' => ['result' => 1, 'reason' => 'OK']]),
                default => Http::response(['result' => ['status' => 1, 'errors' => null]]),
            };
        });
    }

    public function test_without_a_proxy_plan_the_limit_is_just_reported(): void
    {
        $this->fakeFullWhm();

        $this->assertSame(
            'WHM non ha parcheggiato il dominio: You have reached the maximum number of domains.',
            $this->panel()->ensure('cittadiostia.it'),
        );
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'createacct'));
    }

    public function test_a_refused_park_becomes_a_proxy_account(): void
    {
        config(['ksm.server.ips' => ['86.107.33.88']]);
        $this->fakeFullWhm(taken: ['cittadiostia']);

        $this->assertNull($this->proxyPanel()->ensure('CittaDiOstia.it'));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'createacct')
            && $request['domain'] === 'cittadiostia.it'
            && $request['username'] === 'cittadiostia1'
            && $request['plan'] === 'rivenditore_5Mb'
            && $request['contactemail'] === 'info@ksm.it'
            && strlen($request['password']) === 32);
        Http::assertSent(fn (Request $request) => ($request['cpanel_jsonapi_func'] ?? null) === 'save_file_content'
            && $request['cpanel_jsonapi_user'] === 'cittadiostia1'
            && $request['file'] === 'index.php'
            && str_contains($request['content'], "const KSM_TARGET = 'https://ksm.it';")
            && str_contains($request['content'], "const KSM_SERVER_IP = '86.107.33.88';"));
        Http::assertSent(fn (Request $request) => ($request['cpanel_jsonapi_func'] ?? null) === 'save_file_content'
            && $request['file'] === '.htaccess'
            && str_contains($request['content'], 'RewriteRule ^ index.php'));
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'start_autossl_check')
            && $request['cpanel_jsonapi_user'] === 'cittadiostia1');
    }

    public function test_an_existing_proxy_account_only_gets_its_files_again(): void
    {
        $this->fakeFullWhm(accounts: [['user' => 'cittadiostia', 'domain' => 'cittadiostia.it']]);

        $this->assertNull($this->proxyPanel()->ensure('cittadiostia.it'));

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'createacct')
            || str_contains($request->url(), 'create_parked_domain_for_user'));
        Http::assertSent(fn (Request $request) => ($request['cpanel_jsonapi_func'] ?? null) === 'save_file_content'
            && $request['cpanel_jsonapi_user'] === 'cittadiostia');
    }

    public function test_a_file_that_cannot_be_written_is_reported(): void
    {
        Http::fake(function (Request $request) {
            return match (true) {
                str_contains($request->url(), 'list_domains') => Http::response(['result' => ['data' => ['main_domain' => 'ilnetworkmarketing.it']]]),
                str_contains($request->url(), 'listaccts') => Http::response(['data' => ['acct' => [['user' => 'cittadiostia', 'domain' => 'cittadiostia.it']]]]),
                default => Http::response(['result' => ['status' => 0, 'errors' => ['Disk quota exceeded']]]),
            };
        });

        $this->assertSame(
            "Account proxy cittadiostia creato, ma .htaccess non e' stato scritto: Disk quota exceeded",
            $this->proxyPanel()->ensure('cittadiostia.it'),
        );
    }
}
