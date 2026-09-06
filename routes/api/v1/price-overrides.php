<?php

use App\Http\Controllers\Api\V1\Sales\PriceOverrideController;
use App\Http\Controllers\Api\V1\Sales\PriceOverridePolicyController;
use Illuminate\Support\Facades\Route;

// Price Override Policies
Route::prefix('policies')->group(function () {
    Route::get('/', [PriceOverridePolicyController::class, 'index'])->middleware('check.permission:sales.price-overrides.view');
    Route::post('/', [PriceOverridePolicyController::class, 'store'])->middleware('check.permission:sales.price-overrides.manage');
    Route::get('/{policy}', [PriceOverridePolicyController::class, 'show'])->middleware('check.permission:sales.price-overrides.view');
    Route::put('/{policy}', [PriceOverridePolicyController::class, 'update'])->middleware('check.permission:sales.price-overrides.manage');
});

// Price Overrides
Route::get('/', [PriceOverrideController::class, 'index'])->middleware('check.permission:sales.price-overrides.view');
Route::post('/', [PriceOverrideController::class, 'store'])->middleware('check.permission:sales.price-overrides.manage');
Route::get('/report', [PriceOverrideController::class, 'report'])->middleware('check.permission:sales.price-overrides.view');
Route::get('/{override}', [PriceOverrideController::class, 'show'])->middleware('check.permission:sales.price-overrides.view');
Route::post('/{override}/approve', [PriceOverrideController::class, 'approve'])->middleware('check.permission:sales.price-overrides.manage');
Route::post('/{override}/reject', [PriceOverrideController::class, 'reject'])->middleware('check.permission:sales.price-overrides.manage');
