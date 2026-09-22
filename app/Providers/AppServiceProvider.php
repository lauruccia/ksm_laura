<?php

namespace App\Providers;

use App\Support\Domains\CpanelHostingPanel;
use App\Support\Domains\HostingPanel;
use App\Support\Domains\NoHostingPanel;
use App\Support\Domains\WhmHostingPanel;
use App\Support\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

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
    }
}
