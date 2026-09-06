<?php

use App\Http\Controllers\Api\V1\Sales\ProductBundleController;
use App\Http\Controllers\Api\V1\Sales\SeasonalCampaignController;
use Illuminate\Support\Facades\Route;

// Product Bundles
Route::prefix('bundles')->group(function () {
    Route::get('/', [ProductBundleController::class, 'index'])->middleware('check.permission:sales.offers.view');
    Route::post('/', [ProductBundleController::class, 'store'])->middleware('check.permission:sales.offers.manage');
    Route::get('/{bundle}', [ProductBundleController::class, 'show'])->middleware('check.permission:sales.offers.view');
    Route::put('/{bundle}', [ProductBundleController::class, 'update'])->middleware('check.permission:sales.offers.manage');
    Route::delete('/{bundle}', [ProductBundleController::class, 'destroy'])->middleware('check.permission:sales.offers.manage');
    Route::post('/{bundle}/calculate', [ProductBundleController::class, 'calculatePrice'])->middleware('check.permission:sales.offers.manage');
});

// Seasonal Campaigns
Route::prefix('campaigns')->group(function () {
    Route::get('/', [SeasonalCampaignController::class, 'index'])->middleware('check.permission:sales.offers.view');
    Route::get('/active', [SeasonalCampaignController::class, 'active'])->middleware('check.permission:sales.offers.view');
    Route::post('/', [SeasonalCampaignController::class, 'store'])->middleware('check.permission:sales.offers.manage');
    Route::get('/{campaign}', [SeasonalCampaignController::class, 'show'])->middleware('check.permission:sales.offers.view');
    Route::put('/{campaign}', [SeasonalCampaignController::class, 'update'])->middleware('check.permission:sales.offers.manage');
    Route::delete('/{campaign}', [SeasonalCampaignController::class, 'destroy'])->middleware('check.permission:sales.offers.manage');
    Route::post('/{campaign}/tier-offers', [SeasonalCampaignController::class, 'addTierOffer'])->middleware('check.permission:sales.offers.manage');
});
