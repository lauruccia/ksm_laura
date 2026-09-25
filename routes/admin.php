<?php

use App\Http\Controllers\Admin\AdminAdvertisementController;
use App\Http\Controllers\Admin\AdminAdvertiserController;
use App\Http\Controllers\Admin\AdminCmsPageController;
use App\Http\Controllers\Admin\AdminCompanyCategoryController;
use App\Http\Controllers\Admin\AdminCompanyController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDomainController;
use App\Http\Controllers\Admin\AdminMenuController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminProductBrandController;
use App\Http\Controllers\Admin\AdminProductCategoryController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Support\Permissions as P;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Amministrazione
|--------------------------------------------------------------------------
|
| `admin` dice chi puo' entrare, `can:` dice cosa puo' fare una volta
| dentro. Ogni sezione dichiara qui il permesso che richiede: e' l'unico
| punto da guardare per sapere chi vede cosa.
|
| Regola costante: l'elenco chiede il permesso "vedere", tutto il resto
| chiede "gestire".
|
*/

Route::middleware(['auth', 'admin'])
    ->prefix('amministrazione')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])
            ->middleware('can:'.P::DASHBOARD_VIEW)->name('dashboard');

        /* Aziende ------------------------------------------------------- */

        Route::resource('aziende', AdminCompanyController::class)
            ->parameters(['aziende' => 'company'])->names('companies')
            ->only(['index'])->middleware('can:'.P::COMPANIES_VIEW);

        Route::middleware('can:'.P::COMPANIES_MANAGE)->group(function () {
            Route::resource('aziende', AdminCompanyController::class)
                ->parameters(['aziende' => 'company'])->names('companies')
                ->except(['index']);
            Route::patch('/aziende/{company}/stato', [AdminCompanyController::class, 'toggleStatus'])
                ->name('companies.status');
            Route::patch('/aziende/{company}/dominio', [AdminCompanyController::class, 'checkDomain'])
                ->name('companies.domain');
            Route::middleware('throttle:6,1')->group(function () {
                Route::post('/aziende/{company}/kmoney', [AdminCompanyController::class, 'pairKMoney'])
                    ->name('companies.kmoney.pair');
                Route::patch('/aziende/{company}/kmoney', [AdminCompanyController::class, 'checkKMoney'])
                    ->name('companies.kmoney.check');
            });

            Route::resource('categorie-aziende', AdminCompanyCategoryController::class)
                ->parameters(['categorie-aziende' => 'category'])->names('company_categories');
        });

        /* Abbonamenti --------------------------------------------------- */

        Route::get('/abbonamenti', [AdminSubscriptionController::class, 'index'])
            ->middleware('can:'.P::SUBSCRIPTIONS_VIEW)->name('subscriptions.index');

        Route::middleware('can:'.P::SUBSCRIPTIONS_MANAGE)->group(function () {
            Route::post('/abbonamenti', [AdminSubscriptionController::class, 'store'])
                ->name('subscriptions.store');
            Route::get('/abbonamenti/aziende', [AdminSubscriptionController::class, 'companies'])
                ->name('subscriptions.companies');
            Route::post('/abbonamenti/rinnovo-in-blocco', [AdminSubscriptionController::class, 'extend'])
                ->name('subscriptions.extend');
            Route::patch('/abbonamenti/{subscription}/promemoria', [AdminSubscriptionController::class, 'reminders'])
                ->name('subscriptions.reminders');
            Route::patch('/abbonamenti/{subscription}/conferma', [AdminSubscriptionController::class, 'confirm'])
                ->name('subscriptions.confirm');
            Route::patch('/abbonamenti/{subscription}/chiudi', [AdminSubscriptionController::class, 'cancel'])
                ->name('subscriptions.cancel');
        });

        /* Catalogo ------------------------------------------------------ */

        Route::middleware('can:'.P::CATALOG_VIEW)->group(function () {
            Route::resource('prodotti', AdminProductController::class)
                ->parameters(['prodotti' => 'product'])->names('products')
                ->only(['index', 'show']);
            Route::resource('categorie-prodotti', AdminProductCategoryController::class)
                ->parameters(['categorie-prodotti' => 'category'])->names('product_categories')
                ->only(['index']);
            Route::resource('marche', AdminProductBrandController::class)
                ->parameters(['marche' => 'brand'])->names('brands')
                ->only(['index']);
        });

        Route::middleware('can:'.P::CATALOG_MANAGE)->group(function () {
            Route::resource('prodotti', AdminProductController::class)
                ->parameters(['prodotti' => 'product'])->names('products')
                ->only(['edit', 'update', 'destroy']);
            Route::patch('/prodotti/{product}/stato', [AdminProductController::class, 'toggleStatus'])
                ->name('products.status');
            Route::patch('/prodotti-kmoney', [AdminProductController::class, 'kmoney'])
                ->name('products.kmoney');

            Route::resource('categorie-prodotti', AdminProductCategoryController::class)
                ->parameters(['categorie-prodotti' => 'category'])->names('product_categories')
                ->except(['index']);
            Route::resource('marche', AdminProductBrandController::class)
                ->parameters(['marche' => 'brand'])->names('brands')
                ->except(['index']);
        });

        /* Vendite ------------------------------------------------------- */

        Route::resource('ordini', AdminOrderController::class)
            ->parameters(['ordini' => 'order'])->names('orders')
            ->only(['index', 'show'])->middleware('can:'.P::ORDERS_VIEW);

        Route::middleware('can:'.P::ORDERS_MANAGE)->group(function () {
            Route::delete('/ordini/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
            Route::patch('/ordini/{order}/stato', [AdminOrderController::class, 'updateStatus'])
                ->name('orders.status');
        });

        Route::get('/pagamenti', [AdminPaymentController::class, 'index'])
            ->middleware('can:'.P::PAYMENTS_VIEW)->name('payments.index');
        Route::delete('/pagamenti/{payment}', [AdminPaymentController::class, 'destroy'])
            ->middleware('can:'.P::PAYMENTS_MANAGE)->name('payments.destroy');

        Route::resource('piani', AdminPlanController::class)
            ->parameters(['piani' => 'plan'])->names('plans')
            ->middleware('can:'.P::PLANS_MANAGE);

        /* Contenuti ----------------------------------------------------- */

        Route::middleware('can:'.P::CONTENT_MANAGE)->group(function () {
            Route::resource('domini', AdminDomainController::class)
                ->parameters(['domini' => 'domain'])->names('domains');
            Route::patch('/domini/{domain}/verifica', [AdminDomainController::class, 'check'])
                ->name('domains.check');
            Route::resource('pagine', AdminCmsPageController::class)
                ->parameters(['pagine' => 'page'])->names('cms');
            Route::get('/menu', [AdminMenuController::class, 'edit'])->name('menus.edit');
            Route::put('/menu/{location}', [AdminMenuController::class, 'update'])->name('menus.update');
            Route::resource('banner', AdminAdvertisementController::class)
                ->parameters(['banner' => 'advertisement'])->names('advertisements');

            // La ricerca delle aziende e' la stessa di Abbonamenti.
            Route::get('/inserzionisti/aziende', [AdminSubscriptionController::class, 'companies'])
                ->name('advertisers.companies');
            Route::resource('inserzionisti', AdminAdvertiserController::class)
                ->parameters(['inserzionisti' => 'advertiser'])->names('advertisers');
        });

        /* Sistema ------------------------------------------------------- */

        Route::resource('utenti', AdminUserController::class)
            ->parameters(['utenti' => 'user'])->names('users')
            ->only(['index'])->middleware('can:'.P::USERS_VIEW);

        Route::middleware('can:'.P::USERS_MANAGE)->group(function () {
            Route::resource('utenti', AdminUserController::class)
                ->parameters(['utenti' => 'user'])->names('users')
                ->except(['index']);
            Route::patch('/utenti/{user}/stato', [AdminUserController::class, 'toggleStatus'])
                ->name('users.status');
        });

        Route::resource('ruoli', AdminRoleController::class)
            ->parameters(['ruoli' => 'role'])->names('roles')
            ->middleware('can:'.P::ROLES_MANAGE);

        Route::middleware('can:'.P::SETTINGS_MANAGE)->group(function () {
            Route::get('/impostazioni', [AdminSettingController::class, 'edit'])->name('settings.edit');
            Route::put('/impostazioni', [AdminSettingController::class, 'update'])->name('settings.update');
            Route::put('/impostazioni/pagamenti', [AdminSettingController::class, 'updatePayments'])
                ->name('settings.payments');
            Route::put('/impostazioni/posta', [AdminSettingController::class, 'updateMail'])
                ->name('settings.mail');
        });

        /* Il proprio profilo: nessun permesso, e' di chi ha fatto accesso. */

        Route::get('/profilo', [AdminProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profilo', [AdminProfileController::class, 'update'])->name('profile.update');
    });
