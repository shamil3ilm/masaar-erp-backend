<?php

use App\Http\Controllers\Api\V1\Accounting\MultiCurrencyController;
use Illuminate\Support\Facades\Route;

// Organization Currencies
Route::get('/currencies', [MultiCurrencyController::class, 'currencies']);
Route::post('/currencies', [MultiCurrencyController::class, 'addCurrency'])->middleware('check.permission:accounting.multi-currency.manage');
Route::delete('/currencies/{currencyCode}', [MultiCurrencyController::class, 'removeCurrency'])->middleware('check.permission:accounting.multi-currency.manage');

// Currency Revaluations
Route::prefix('revaluations')->group(function () {
    Route::get('/', [MultiCurrencyController::class, 'revaluations']);
    Route::post('/', [MultiCurrencyController::class, 'createRevaluation'])->middleware('check.permission:accounting.multi-currency.manage');
    Route::post('/auto-run', [MultiCurrencyController::class, 'autoRunRevaluation'])
        ->middleware('check.permission:accounting.multi-currency.manage'); // SAP F.05
    Route::get('/{currencyRevaluation}', [MultiCurrencyController::class, 'showRevaluation']);
    Route::post('/{currencyRevaluation}/post', [MultiCurrencyController::class, 'postRevaluation'])->middleware('check.permission:accounting.multi-currency.manage');
    Route::post('/{currencyRevaluation}/reverse', [MultiCurrencyController::class, 'reverseRevaluation'])->middleware('check.permission:accounting.multi-currency.manage');
});

// Forex Reports
Route::get('/forex-report', [MultiCurrencyController::class, 'forexReport']);
