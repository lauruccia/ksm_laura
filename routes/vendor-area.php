<?php

use App\Http\Controllers\Vendor\CompanyOnboardingController;
use App\Http\Middleware\EnsurePlanAllows;
use App\Support\PlanCapabilities;
use App\Http\Controllers\Vendor\SubscriptionController;
use App\Http\Controllers\Vendor\VendorAdvertisingController;
use App\Http\Controllers\Vendor\VendorDashboardController;
use App\Http\Controllers\Vendor\VendorKMoneyController;
use App\Http\Controllers\Vendor\VendorOrderController;
use App\Http\Controllers\Vendor\VendorPaymentSettingController;
use App\Http\Controllers\Vendor\VendorProductController;
use App\Http\Controllers\Vendor\VendorProfileController;
use Illuminate\Support\Facades\Route;

/*
 * Attivazione e abbonamento stanno fuori dal filtro `vendor`: servono
 * proprio a chi un'azienda attiva non ce l'ha ancora, o non piu'.
 */
Route::middleware('auth')->group(function () {
    Route::get('/attivazione', [CompanyOnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/attivazione', [CompanyOnboardingController::class, 'store'])->name('onboarding.store');

    Route::prefix('abbonamento')->name('subscription.')->group(function () {
        Route::get('/', [SubscriptionController::class, 'index'])->name('index');
        Route::post('/', [SubscriptionController::class, 'store'])->name('store');
        Route::get('/{subscription}/pagamento', [SubscriptionController::class, 'payment'])->name('payment');
        Route::post('/{subscription}/pagamento', [SubscriptionController::class, 'pay'])->name('pay');
        // Il gateway rimanda qui: l'esito viene richiesto al gateway stesso.
        Route::get('/{subscription}/rientro', [SubscriptionController::class, 'returnFromGateway'])->name('return');
        Route::get('/{subscription}/bonifico', [SubscriptionController::class, 'bankTransfer'])->name('bank');
        Route::get('/{subscription}/esito', [SubscriptionController::class, 'success'])->name('success');
        Route::get('/{subscription}/annullato', [SubscriptionController::class, 'cancelled'])->name('cancelled');
    });
});

Route::middleware(['auth', 'vendor'])
    ->prefix('area-azienda')
    ->name('vendor.')
    ->group(function () {
        Route::get('/', [VendorDashboardController::class, 'index'])->name('dashboard');

        Route::get('/profilo', [VendorProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profilo', [VendorProfileController::class, 'update'])->name('profile.update');

        // Campagne banner dell'azienda: statistiche e scadenze, con qualsiasi piano.
        Route::get('/pubblicita', [VendorAdvertisingController::class, 'index'])->name('advertising.index');
        Route::get('/pubblicita/{advertisement}', [VendorAdvertisingController::class, 'show'])->name('advertising.show');

        // Prodotti, ordini e incassi hanno senso solo se il piano
        // comprende la vendita: senza, non c'e' niente da gestire.
        Route::middleware(EnsurePlanAllows::class.':'.PlanCapabilities::SHOP)->group(function () {
            Route::resource('prodotti', VendorProductController::class)
                ->parameters(['prodotti' => 'product'])
                ->names('products');
            Route::patch('/prodotti/{product}/stato', [VendorProductController::class, 'toggleStatus'])
                ->name('products.status');

            // Quote KMoney: su piu' prodotti selezionati e per categoria.
            Route::patch('/prodotti-kmoney', [VendorKMoneyController::class, 'bulk'])->name('products.kmoney');
            Route::get('/kmoney', [VendorKMoneyController::class, 'edit'])->name('kmoney.edit');
            Route::put('/kmoney', [VendorKMoneyController::class, 'update'])->name('kmoney.update');

            Route::get('/ordini', [VendorOrderController::class, 'index'])->name('orders.index');
            Route::get('/ordini/{order}', [VendorOrderController::class, 'show'])->name('orders.show');
            Route::patch('/ordini/{order}/stato', [VendorOrderController::class, 'updateStatus'])
                ->name('orders.status');

            Route::get('/incassi', [VendorPaymentSettingController::class, 'edit'])->name('payments.edit');
            Route::put('/incassi', [VendorPaymentSettingController::class, 'update'])->name('payments.update');
        });
    });
