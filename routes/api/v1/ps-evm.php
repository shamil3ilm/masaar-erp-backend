<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Projects\EarnedValueController;
use App\Http\Controllers\Api\V1\Projects\ProjectSettlementController;
use App\Http\Controllers\Api\V1\Projects\WbsController;
use Illuminate\Support\Facades\Route;

Route::get('projects/{projectId}/wbs', [WbsController::class, 'hierarchy'])
    ->name('ps.wbs.hierarchy');

Route::post('projects/{projectId}/wbs', [WbsController::class, 'createElement'])
    ->name('ps.wbs.create')->middleware('check.permission:projects.wbs.edit');

Route::put('projects/{projectId}/wbs/{wbsElement}', [WbsController::class, 'updateElement'])
    ->name('ps.wbs.update')->middleware('check.permission:projects.wbs.edit');

Route::post('projects/{projectId}/wbs/rollup', [WbsController::class, 'rollupCosts'])
    ->name('ps.wbs.rollup')->middleware('check.permission:projects.wbs.edit');

Route::post('projects/{projectId}/evm/snapshot', [EarnedValueController::class, 'calculateSnapshot'])
    ->name('ps.evm.snapshot')->middleware('check.permission:projects.evm.manage');

Route::get('projects/{projectId}/evm/latest', [EarnedValueController::class, 'latestSnapshot'])
    ->name('ps.evm.latest');

Route::get('projects/{projectId}/evm/history', [EarnedValueController::class, 'history'])
    ->name('ps.evm.history');

Route::post('projects/{projectId}/settlement-rules', [ProjectSettlementController::class, 'defineRule'])
    ->name('ps.settlement.rule')->middleware('check.permission:projects.settlement.manage');

Route::post('projects/{projectId}/settle', [ProjectSettlementController::class, 'settle'])
    ->name('ps.settlement.settle')->middleware('check.permission:projects.settlement.manage');
