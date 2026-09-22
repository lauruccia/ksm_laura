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
}
