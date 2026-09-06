<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Accounting\TreasuryController;
use Illuminate\Support\Facades\Route;

Route::prefix('treasury')->group(function (): void {

    // -------------------------------------------------------------------------
    // Treasury Investments
    // -------------------------------------------------------------------------
    Route::get('investments', [TreasuryController::class, 'index'])
        ->name('accounting.treasury.investments.index')->middleware('check.permission:accounting.treasury.view');
    Route::post('investments', [TreasuryController::class, 'store'])
        ->name('accounting.treasury.investments.store')->middleware('check.permission:accounting.treasury.manage');
    Route::get('investments/{treasuryInvestment}', [TreasuryController::class, 'show'])
        ->name('accounting.treasury.investments.show')->middleware('check.permission:accounting.treasury.view');
    Route::post('investments/{treasuryInvestment}/accrue', [TreasuryController::class, 'accrueInterest'])
        ->name('accounting.treasury.accrue')->middleware('check.permission:accounting.treasury.manage');
    Route::post('investments/{treasuryInvestment}/mature', [TreasuryController::class, 'mature'])
        ->name('accounting.treasury.mature')->middleware('check.permission:accounting.treasury.manage');
    Route::post('investments/{treasuryInvestment}/pre-liquidate', [TreasuryController::class, 'preLiquidate'])
        ->name('accounting.treasury.pre-liquidate')->middleware('check.permission:accounting.treasury.manage');

    // -------------------------------------------------------------------------
    // Bank Positions
    // -------------------------------------------------------------------------
    Route::get('bank-positions', [TreasuryController::class, 'bankPositions'])
        ->name('accounting.treasury.bank-positions')->middleware('check.permission:accounting.treasury.view');

    // -------------------------------------------------------------------------
    // Liquidity Plans
    // -------------------------------------------------------------------------
    Route::get('liquidity-plans', [TreasuryController::class, 'liquidityPlans'])
        ->name('accounting.treasury.liquidity-plans.index')->middleware('check.permission:accounting.treasury.view');
    Route::post('liquidity-plans', [TreasuryController::class, 'createLiquidityPlan'])
        ->name('accounting.treasury.liquidity-plans.store')->middleware('check.permission:accounting.treasury.manage');
    Route::get('liquidity-plans/{liquidityPlan}', [TreasuryController::class, 'showLiquidityPlan'])
        ->name('accounting.treasury.liquidity-plans.show')->middleware('check.permission:accounting.treasury.view');
    Route::post('liquidity-plans/{liquidityPlan}/update-actuals', [TreasuryController::class, 'updateActuals'])
        ->name('accounting.treasury.update-actuals')->middleware('check.permission:accounting.treasury.manage');

    // -------------------------------------------------------------------------
    // Summaries
    // -------------------------------------------------------------------------
    Route::get('position-summary', [TreasuryController::class, 'positionSummary'])
        ->name('accounting.treasury.position-summary')->middleware('check.permission:accounting.treasury.view');
    Route::get('maturing-investments', [TreasuryController::class, 'maturingInvestments'])
        ->name('accounting.treasury.maturing')->middleware('check.permission:accounting.treasury.view');
});
