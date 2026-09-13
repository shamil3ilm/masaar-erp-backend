<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tax\VatComplianceController;
use App\Http\Controllers\Api\V1\Tax\VatReturnController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tax Compliance Routes — VAT (GCC)
|--------------------------------------------------------------------------
|
| All routes require JWT authentication and a verified organization context.
| Additional permission checks are applied per route group.
|
*/

// -------------------------------------------------------------------------
// GCC VAT Returns
// -------------------------------------------------------------------------
Route::prefix('vat-returns')->name('tax.vat.')->group(function () {

    Route::middleware('check.permission:tax.vat.view')->group(function () {
        Route::get('/', [VatReturnController::class, 'index'])->name('index')->middleware('check.permission:tax.vat.view');
        Route::get('/transactions', [VatReturnController::class, 'indexTransactions'])->name('transactions.index')->middleware('check.permission:tax.vat.view');
        Route::get('/{vatReturnPeriod}', [VatReturnController::class, 'show'])->name('show')->middleware('check.permission:tax.vat.view');
        Route::get('/{vatReturnPeriod}/export', [VatReturnController::class, 'exportReturn'])->name('export')->middleware('check.permission:tax.vat.view');
    });

    Route::middleware('check.permission:tax.vat.manage')->group(function () {
        Route::post('/', [VatReturnController::class, 'store'])->name('store');
        Route::post('/{vatReturnPeriod}/build-boxes', [VatReturnController::class, 'buildBoxes'])->name('build-boxes');
        Route::post('/{vatReturnPeriod}/submit', [VatReturnController::class, 'submit'])->name('submit');
        Route::post('/transactions', [VatReturnController::class, 'storeTransaction'])->name('transactions.store');
    });
});

// -------------------------------------------------------------------------
// VAT Compliance (GCC)
// -------------------------------------------------------------------------
Route::prefix('vat-compliance')->name('tax.vat-compliance.')->group(function () {
    Route::middleware('check.permission:tax.vat.view')->group(function () {
        Route::get('/', [VatComplianceController::class, 'index'])->name('index')->middleware('check.permission:tax.vat.view');
        Route::get('/{id}', [VatComplianceController::class, 'show'])->name('show')->middleware('check.permission:tax.vat.view');
    });
    Route::middleware('check.permission:tax.vat.manage')->group(function () {
        Route::post('/generate', [VatComplianceController::class, 'generate'])->name('generate');
        Route::post('/{id}/file', [VatComplianceController::class, 'file'])->name('file');
    });
});
