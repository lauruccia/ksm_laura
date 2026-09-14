<?php

use App\Http\Controllers\Account\AccountDashboardController;
use App\Http\Controllers\Account\AccountOrderController;
use App\Http\Controllers\Account\AccountProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Area cliente
|--------------------------------------------------------------------------
|
| Vale per chiunque abbia fatto accesso: anche chi ha un'azienda compra
| come cliente, e ritrova qui i propri ordini.
|
*/

Route::middleware('auth')
    ->prefix('account')
    ->name('account.')
    ->group(function () {
        Route::get('/', [AccountDashboardController::class, 'index'])->name('dashboard');

        Route::get('/ordini', [AccountOrderController::class, 'index'])->name('orders.index');
        Route::get('/ordini/{order}', [AccountOrderController::class, 'show'])->name('orders.show');

        Route::get('/profilo', [AccountProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profilo', [AccountProfileController::class, 'update'])->name('profile.update');
        Route::put('/profilo/password', [AccountProfileController::class, 'updatePassword'])
            ->name('profile.password');
    });
