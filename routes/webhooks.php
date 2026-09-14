<?php

use App\Http\Controllers\AdvertisementViewController;
use App\Http\Controllers\TlsAuthorizationController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Notifiche dei gestori di pagamento.
 *
 * Niente sessione e niente token CSRF: chi chiama e' un server, non un
 * browser. L'autenticita' e' data dalla firma della notifica, che il
 * driver verifica prima di toccare qualsiasi cosa.
 *
 * L'indirizzo contiene l'azienda perche' ogni azienda incassa con le
 * proprie credenziali e ha un proprio segreto di verifica.
 */

Route::post('/webhook/stripe/{company}', [WebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('/webhook/paypal/{company}', [WebhookController::class, 'paypal'])->name('webhooks.paypal');
Route::post('/webhook/kmoney/{company}', [WebhookController::class, 'kmoney'])->name('webhooks.kmoney');

/*
 * Il server web chiede qui se puo' emettere un certificato per un dominio.
 * Anche lui e' un server: stesso gruppo senza sessione.
 */
Route::get('/tls/autorizza', TlsAuthorizationController::class)->name('tls.authorize');

/*
 * Visualizzazione di un banner, segnalata dalla pagina con sendBeacon.
 * Niente sessione: vale la firma dell'indirizzo, scritta dal sito.
 */
Route::post('/banner/{advertisement}/vista', AdvertisementViewController::class)
    ->middleware(['signed:relative', 'throttle:120,1'])
    ->name('ads.view');
