<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Le notifiche dei gestori non sono richieste di browser: niente
            // sessione, solo il minimo per risolvere l'azienda dall'indirizzo.
            Route::middleware([SubstituteBindings::class])->group(base_path('routes/webhooks.php'));

            // L'ordine conta: prima le sezioni con un prefisso proprio,
            // per ultima la rotta jolly delle pagine.
            Route::middleware('web')->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/account.php'));
            Route::middleware('web')->group(base_path('routes/pages.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            App\Http\Middleware\ResolveTenant::class,
            App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'admin' => App\Http\Middleware\EnsureUserIsAdmin::class,
            'vendor' => App\Http\Middleware\EnsureUserIsVendor::class,
            'advertiser' => App\Http\Middleware\EnsureUserIsAdvertiser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
