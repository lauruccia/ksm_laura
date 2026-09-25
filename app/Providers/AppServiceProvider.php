<?php

namespace App\Providers;

use App\Models\SmtpSetting;
use App\Support\Ads\AdServer;
use App\Support\Domains\CpanelHostingPanel;
use App\Support\Domains\HostingPanel;
use App\Support\Domains\NoHostingPanel;
use App\Support\Domains\WhmHostingPanel;
use App\Support\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->scoped(AdServer::class);

        // La posta in uscita scritta in Amministrazione vale piu' del .env.
        $this->app->afterResolving('mail.manager', fn () => SmtpSetting::applyToMailer());

        // Solo con un token cPanel i domini si aggiungono da soli all'account.
        $this->app->singleton(HostingPanel::class, function () {
            // Con la configurazione in cache di prima le chiavi mancano del tutto.
            $whm = (array) config('ksm.whm', []) + ['url' => null, 'reseller' => null, 'token' => null, 'account' => null];
            $cpanel = (array) config('ksm.cpanel', []) + ['url' => null, 'user' => null, 'token' => null, 'docroot' => 'ksm-next/public'];

            return match (true) {
                filled($whm['url']) && filled($whm['reseller']) && filled($whm['token']) && filled($whm['account'])
                    => new WhmHostingPanel($whm['url'], $whm['reseller'], $whm['token'], $whm['account']),
                filled($cpanel['url']) && filled($cpanel['user']) && filled($cpanel['token'])
                    => new CpanelHostingPanel($cpanel['url'], $cpanel['user'], $cpanel['token'], $cpanel['docroot']),
                default => new NoHostingPanel,
            };
        });
    }

    public function boot(): void
    {
        view()->share('tenant', $this->app->make(TenantContext::class));

        // Quella di Laravel presuppone Tailwind, che il sito non carica.
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        // Stripe di suo aspetta fino a 80 secondi: chi paga non deve restare
        // appeso cosi' a lungo se Stripe non risponde.
        if (class_exists(CurlClient::class)) {
            $stripeHttp = new CurlClient;
            $stripeHttp->setConnectTimeout(5);
            $stripeHttp->setTimeout(25);
            ApiRequestor::setHttpClient($stripeHttp);
        }
    }
}
