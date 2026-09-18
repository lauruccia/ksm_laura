<?php

namespace App\Providers;

use App\Models\AdminSetting;
use App\Support\Cart;
use App\Support\Navigation;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Dati sempre presenti in intestazione e piede.
 * Un solo punto di lettura, cosi' le viste non interrogano il database.
 */
class ViewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer(['layouts.*', 'partials.*'], function ($view) {
            $view->with([
                'settings' => AdminSetting::current(),
                'headerPages' => Navigation::pages('header'),
                'footerPages' => Navigation::pages('footer'),
                'cartCount' => app(Cart::class)->totalCount(),
                // Di quanti venditori sono i carrelli aperti: oltre uno lo si dice.
                'cartVendors' => app(Cart::class)->parked()->count() + (app(Cart::class)->isEmpty() ? 0 : 1),
            ]);
        });
    }
}
