<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
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
            // L'amministrazione esiste solo sul sito principale.
            Route::middleware(['web', 'platform'])->group(base_path('routes/admin.php'));
            Route::middleware('web')->group(base_path('routes/account.php'));
            Route::middleware('web')->group(base_path('routes/pages.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Dietro Caddy: https, dominio e IP veri solo dal proxy della macchina.
        // Senza, link, moduli e ritorni dai pagamenti uscirebbero in http.
        $middleware->replace(TrustProxies::class, App\Http\Middleware\TrustPlatformProxies::class);

        // Prima della sessione: www.dominio passa subito all'indirizzo senza www.
        $middleware->web(prepend: [
            App\Http\Middleware\RedirectWww::class,
        ]);

        $middleware->web(append: [
            App\Http\Middleware\ResolveTenant::class,
            App\Http\Middleware\SetLocale::class,
        ]);

        // Il sito si decide prima dell'accesso e dei parametri delle rotte: su un host
        // sconosciuto, o fuori da KSM su una sezione di KSM, si risponde 404 e non
        // "accedi" o "pagina non trovata" con il marchio della piattaforma.
        $middleware->prependToPriorityList(
            before: Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: App\Http\Middleware\ResolveTenant::class,
        );
        $middleware->prependToPriorityList(
            before: Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: App\Http\Middleware\EnsurePlatformSite::class,
        );

        $middleware->alias([
            'admin' => App\Http\Middleware\EnsureUserIsAdmin::class,
            'vendor' => App\Http\Middleware\EnsureUserIsVendor::class,
            'advertiser' => App\Http\Middleware\EnsureUserIsAdvertiser::class,
            'platform' => App\Http\Middleware\EnsurePlatformSite::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Un indirizzo senza rotta su un host che non e' di nessun sito: stesso 404
        // senza marchio di ResolveTenant, non la pagina di errore di KSM.
        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Illuminate\Http\Request $request) {
            if (! App\Support\Sites\HostDirectory::knows($request->getHost())) {
                return App\Http\Middleware\ResolveTenant::unknownSite();
            }
        });
    })->create();
