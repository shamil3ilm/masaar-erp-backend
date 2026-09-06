<?php

use App\Http\Controllers\Api\V1\Inventory\BarcodeController;
use App\Http\Controllers\Api\V1\Inventory\PriceCheckController;
use App\Http\Controllers\Api\V1\Inventory\ShelfLabelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Barcode & Price Check API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/inventory
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Top-level convenience routes
    |--------------------------------------------------------------------------
    */
    Route::post('/lookup', [BarcodeController::class, 'lookupByValue'])->name('inventory.barcode.lookup')->middleware('check.permission:inventory.barcodes.manage');
    Route::post('/price-check', [PriceCheckController::class, 'quickCheck'])->name('inventory.barcode.price-check')->middleware('check.permission:inventory.barcodes.manage');

    /*
    |--------------------------------------------------------------------------
    | Barcodes
    |--------------------------------------------------------------------------
    */
    Route::prefix('barcodes')->group(function () {
        Route::get('/', [BarcodeController::class, 'index'])->name('inventory.barcodes.index');
        Route::post('/', [BarcodeController::class, 'store'])->name('inventory.barcodes.store')->middleware('check.permission:inventory.barcodes.manage');
        Route::post('/generate', [BarcodeController::class, 'generate'])->name('inventory.barcodes.generate')->middleware('check.permission:inventory.barcodes.manage');
        Route::post('/lookup', [BarcodeController::class, 'lookup'])->name('inventory.barcodes.lookup')->middleware('check.permission:inventory.barcodes.manage');
        Route::post('/bulk-generate', [BarcodeController::class, 'bulkGenerate'])->name('inventory.barcodes.bulk-generate')->middleware('check.permission:inventory.barcodes.manage');
        Route::post('/print-labels', [BarcodeController::class, 'printLabels'])->name('inventory.barcodes.print-labels')->middleware('check.permission:inventory.barcodes.manage');
        Route::get('/{barcode}', [BarcodeController::class, 'show'])->name('inventory.barcodes.show');
        Route::put('/{barcode}', [BarcodeController::class, 'update'])->name('inventory.barcodes.update')->middleware('check.permission:inventory.barcodes.manage');
        Route::delete('/{barcode}', [BarcodeController::class, 'destroy'])->name('inventory.barcodes.destroy')->middleware('check.permission:inventory.barcodes.manage');
    });

    // Product-scoped barcode routes
    Route::get('products/{product}/barcodes', [BarcodeController::class, 'listForProduct']);
    Route::post('products/{product}/barcodes', [BarcodeController::class, 'storeForProduct']);

    /*
    |--------------------------------------------------------------------------
    | Price Check
    |--------------------------------------------------------------------------
    */
    Route::prefix('price-check')->group(function () {
        Route::post('/check', [PriceCheckController::class, 'check'])->name('inventory.price-check.check')->middleware('check.permission:inventory.price-check.manage');
        Route::get('/analytics', [PriceCheckController::class, 'analytics'])->name('inventory.price-check.analytics');
        Route::get('/logs', [PriceCheckController::class, 'logs'])->name('inventory.price-check.logs');

        // Stations
        Route::prefix('stations')->group(function () {
            Route::get('/', [PriceCheckController::class, 'stationIndex'])->name('inventory.price-check.stations.index');
            Route::post('/', [PriceCheckController::class, 'stationStore'])->name('inventory.price-check.stations.store')->middleware('check.permission:inventory.price-check.manage');
            Route::get('/{station}', [PriceCheckController::class, 'stationShow'])->name('inventory.price-check.stations.show');
            Route::put('/{station}', [PriceCheckController::class, 'stationUpdate'])->name('inventory.price-check.stations.update')->middleware('check.permission:inventory.price-check.manage');
            Route::delete('/{station}', [PriceCheckController::class, 'stationDestroy'])->name('inventory.price-check.stations.destroy')->middleware('check.permission:inventory.price-check.manage');
            Route::get('/{station}/stats', [PriceCheckController::class, 'stationStats'])->name('inventory.price-check.stations.stats');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Shelf Labels
    |--------------------------------------------------------------------------
    */
    Route::prefix('shelf-labels')->group(function () {
        Route::get('/', [ShelfLabelController::class, 'index'])->name('inventory.shelf-labels.index');
        Route::post('/', [ShelfLabelController::class, 'store'])->name('inventory.shelf-labels.store')->middleware('check.permission:inventory.shelf-labels.manage');
        Route::post('/generate', [ShelfLabelController::class, 'generate'])->name('inventory.shelf-labels.generate')->middleware('check.permission:inventory.shelf-labels.manage');
        Route::post('/bulk-create', [ShelfLabelController::class, 'bulkCreate'])->name('inventory.shelf-labels.bulk-create')->middleware('check.permission:inventory.shelf-labels.manage');
        Route::post('/reprint', [ShelfLabelController::class, 'reprint'])->name('inventory.shelf-labels.reprint')->middleware('check.permission:inventory.shelf-labels.manage');
        Route::get('/{shelfLabel}', [ShelfLabelController::class, 'show'])->name('inventory.shelf-labels.show');
        Route::put('/{shelfLabel}', [ShelfLabelController::class, 'update'])->name('inventory.shelf-labels.update')->middleware('check.permission:inventory.shelf-labels.manage');
        Route::delete('/{shelfLabel}', [ShelfLabelController::class, 'destroy'])->name('inventory.shelf-labels.destroy')->middleware('check.permission:inventory.shelf-labels.manage');
    });
});
