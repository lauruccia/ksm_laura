<?php

use App\Integrations\ManagementApi\Http\Controllers\FulfillmentController;
use App\Integrations\ManagementApi\Http\Controllers\IdentityController;
use App\Integrations\ManagementApi\Http\Controllers\InventoryController;
use App\Integrations\ManagementApi\Http\Controllers\OrderController;
use App\Integrations\ManagementApi\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('management/v1')->middleware('throttle:120,1')->group(function (): void {
    Route::get('identity', IdentityController::class)->middleware('management.token:identity:read');

    Route::middleware('management.token:catalog:read')->group(function (): void {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{id}', [ProductController::class, 'show']);
    });

    Route::middleware('management.token:orders:read')->group(function (): void {
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
    });

    Route::put('inventory/{id}', InventoryController::class)->middleware('management.token:inventory:write');
    Route::post('orders/{id}/fulfillments', FulfillmentController::class)->middleware('management.token:fulfillments:write');
});
