<?php

namespace App\Providers;

use App\Support\TenantContext;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        view()->share('tenant', $this->app->make(TenantContext::class));

        // Quella di Laravel presuppone Tailwind, che il sito non carica.
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');
    }
}
