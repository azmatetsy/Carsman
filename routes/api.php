<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ShopDirectoryController;
use App\Http\Controllers\Api\V1\VehicleCatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Public Shop Directory & PostGIS Radius Search
    Route::get('/shops', [ShopDirectoryController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('shops.index');

    Route::get('/shops/{id}', [ShopDirectoryController::class, 'show'])
        ->whereUuid('id')
        ->middleware('throttle:60,1')
        ->name('shops.show');

    // Vehicle Catalog & NHTSA VIN Decoding
    Route::get('/vehicles/makes', [VehicleCatalogController::class, 'makes'])
        ->middleware('throttle:60,1')
        ->name('vehicles.makes');

    Route::post('/vehicles/decode-vin', [VehicleCatalogController::class, 'decodeVin'])
        ->middleware('throttle:20,1')
        ->name('vehicles.decode-vin');
});
