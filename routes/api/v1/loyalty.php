<?php

use App\Http\Controllers\Api\V1\Loyalty\LoyaltyAccountController;
use App\Http\Controllers\Api\V1\Loyalty\LoyaltyProgramController;
use App\Http\Controllers\Api\V1\Loyalty\RewardsCatalogController;
use Illuminate\Support\Facades\Route;

// Loyalty Programs
Route::prefix('programs')->group(function () {
    Route::get('/', [LoyaltyProgramController::class, 'index'])->middleware('check.permission:loyalty.programs.view');
    Route::post('/', [LoyaltyProgramController::class, 'store'])->middleware('check.permission:loyalty.programs.create');
    Route::get('/{program}', [LoyaltyProgramController::class, 'show'])->middleware('check.permission:loyalty.programs.view');
    Route::put('/{program}', [LoyaltyProgramController::class, 'update'])->middleware('check.permission:loyalty.programs.manage');
    Route::delete('/{program}', [LoyaltyProgramController::class, 'destroy'])->middleware('check.permission:loyalty.programs.delete');
    Route::get('/{program}/tiers', [LoyaltyProgramController::class, 'tiers'])->middleware('check.permission:loyalty.programs.view');
    Route::post('/{program}/tiers', [LoyaltyProgramController::class, 'storeTier'])->middleware('check.permission:loyalty.programs.manage');
    Route::get('/{program}/earning-rules', [LoyaltyProgramController::class, 'earningRules'])->middleware('check.permission:loyalty.programs.view');
    Route::post('/{program}/earning-rules', [LoyaltyProgramController::class, 'storeEarningRule'])->middleware('check.permission:loyalty.programs.manage');
});

// Loyalty Accounts
Route::prefix('accounts')->group(function () {
    Route::get('/', [LoyaltyAccountController::class, 'index'])->middleware('check.permission:loyalty.accounts.view');
    Route::post('/enroll', [LoyaltyAccountController::class, 'enroll'])->middleware('check.permission:loyalty.accounts.create');
    Route::get('/{account}', [LoyaltyAccountController::class, 'show'])->middleware('check.permission:loyalty.accounts.view');
    Route::get('/{account}/transactions', [LoyaltyAccountController::class, 'transactions'])->middleware('check.permission:loyalty.accounts.view');
    Route::post('/{account}/earn', [LoyaltyAccountController::class, 'earnPoints'])->middleware('check.permission:loyalty.accounts.manage');
    Route::post('/{account}/redeem', [LoyaltyAccountController::class, 'redeemReward'])->middleware('check.permission:loyalty.accounts.manage');
    Route::get('/{account}/available-rewards', [LoyaltyAccountController::class, 'availableRewards'])->middleware('check.permission:loyalty.accounts.view');
});

// Rewards Catalog
Route::prefix('rewards')->group(function () {
    Route::get('/', [RewardsCatalogController::class, 'index'])->middleware('check.permission:loyalty.rewards.view');
    Route::post('/', [RewardsCatalogController::class, 'store'])->middleware('check.permission:loyalty.rewards.create');
    Route::get('/{reward}', [RewardsCatalogController::class, 'show'])->middleware('check.permission:loyalty.rewards.view');
    Route::put('/{reward}', [RewardsCatalogController::class, 'update'])->middleware('check.permission:loyalty.rewards.manage');
    Route::delete('/{reward}', [RewardsCatalogController::class, 'destroy'])->middleware('check.permission:loyalty.rewards.manage');
});
