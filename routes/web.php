<?php

use App\Http\Controllers\Advertiser\AdvertiserAreaController;
use App\Http\Controllers\AdvertisementClickController;
use App\Http\Controllers\Auth\AdvertiserRegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Sito pubblico
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/lingua/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/aziende', [CompanyController::class, 'index'])->name('companies.index');
Route::get('/aziende/{company:slug}', [CompanyController::class, 'show'])->name('companies.show');
Route::post('/aziende/{company:slug}/recensioni', [ReviewController::class, 'storeForCompany'])
    ->name('companies.reviews.store');
Route::post('/aziende/{company:slug}/contatto', [ContactController::class, 'sendToCompany'])
    ->name('companies.contact');

Route::get('/prodotti', [ProductController::class, 'index'])->name('products.index');
Route::get('/prodotti/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::post('/prodotti/{product:slug}/recensioni', [ReviewController::class, 'storeForProduct'])
    ->middleware('auth')->name('products.reviews.store');

Route::get('/piani', [PlanController::class, 'index'])->name('plans.index');

Route::get('/contatti', [ContactController::class, 'show'])->name('contact');
Route::post('/contatti', [ContactController::class, 'send'])->name('contact.send');

Route::get('/traccia-ordine', [OrderTrackingController::class, 'form'])->name('orders.track');
Route::post('/traccia-ordine', [OrderTrackingController::class, 'lookup'])->name('orders.track.lookup');

Route::get('/banner/{advertisement}/click', AdvertisementClickController::class)->name('ads.click');

// Motori di ricerca: mappa del sito e istruzioni, generate per il dominio corrente.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-{section}-{page}.xml', [SitemapController::class, 'section'])
    ->where(['section' => 'pagine|aziende|prodotti', 'page' => '[1-9][0-9]*'])
    ->name('sitemap.section');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Carrello e pagamento
|--------------------------------------------------------------------------
*/

Route::prefix('carrello')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/aggiungi/{product:slug}', [CartController::class, 'add'])->name('add');
    Route::patch('/aggiorna/{product:slug}', [CartController::class, 'update'])->name('update');
    Route::delete('/rimuovi/{product:slug}', [CartController::class, 'remove'])->name('remove');
    Route::delete('/svuota', [CartController::class, 'clear'])->name('clear');
});

Route::middleware('auth')->prefix('pagamento')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'show'])->name('show');
    Route::post('/', [CheckoutController::class, 'process'])->name('process');
    // Il gateway rimanda qui: l'esito viene richiesto al gateway stesso.
    Route::get('/rientro/{order}', [CheckoutController::class, 'returnFromGateway'])->name('return');
    // Nuovo tentativo sulla parte che manca, per esempio l'euro dopo i KY gia' pagati.
    Route::post('/riprova/{order}', [CheckoutController::class, 'retry'])->name('retry');
    Route::get('/esito/{order}', [CheckoutController::class, 'success'])->name('success');
    Route::get('/annullato/{order}', [CheckoutController::class, 'cancelled'])->name('cancelled');
});

/*
|--------------------------------------------------------------------------
| Accesso
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/accedi', [LoginController::class, 'show'])->name('login');
    Route::post('/accedi', [LoginController::class, 'store'])->name('login.store');

    Route::get('/registrati', [RegisterController::class, 'show'])->name('register');
    Route::post('/registrati', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/inserzionisti/registrati', [AdvertiserRegisterController::class, 'show'])->name('advertiser.register');
    Route::post('/inserzionisti/registrati', [AdvertiserRegisterController::class, 'store'])->name('advertiser.register.store');

    Route::get('/password/recupero', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/password/recupero', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/password/reimposta/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/password/reimposta', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/verifica', [VerificationController::class, 'show'])->name('verification.show');
    Route::post('/verifica', [VerificationController::class, 'verify'])->name('verification.verify');
    Route::post('/verifica/reinvia', [VerificationController::class, 'resend'])->name('verification.resend');
    Route::post('/esci', [LoginController::class, 'destroy'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Area inserzionista
|--------------------------------------------------------------------------
|
| Per gli inserzionisti esterni del circuito banner: le campagne le crea
| l'amministrazione, qui si leggono statistiche e scadenze.
|
*/

Route::middleware(['auth', 'advertiser'])
    ->prefix('area-inserzionista')
    ->name('advertiser.')
    ->group(function () {
        Route::get('/', [AdvertiserAreaController::class, 'index'])->name('dashboard');
        Route::get('/campagne/{advertisement}', [AdvertiserAreaController::class, 'show'])->name('campaigns.show');
    });

/*
|--------------------------------------------------------------------------
| Area venditore
|--------------------------------------------------------------------------
*/

require __DIR__.'/vendor-area.php';

/*
|--------------------------------------------------------------------------
| La rotta jolly delle pagine CMS sta in routes/pages.php e viene
| registrata per ultima, dopo amministrazione e area venditore.
|--------------------------------------------------------------------------
*/
