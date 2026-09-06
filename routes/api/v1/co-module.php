<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Accounting\ActivityTypeController;
use App\Http\Controllers\Api\V1\Accounting\CopaController;
use App\Http\Controllers\Api\V1\Accounting\CostElementController;
use App\Http\Controllers\Api\V1\Accounting\InternalOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CO Module Routes — Cost Elements, Activity Types, Internal Orders, CO-PA
|--------------------------------------------------------------------------
|
| Mounted inside the accounting module middleware group.
| All routes require the accounting module to be enabled.
|
*/

Route::apiResource('cost-elements', CostElementController::class)
    ->names('accounting.cost-elements')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:accounting.cost-elements.manage')->middlewareFor(['index', 'show'], 'check.permission:accounting.cost-elements.view');

Route::apiResource('activity-types', ActivityTypeController::class)
    ->names('accounting.activity-types')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:accounting.activity-types.manage')->middlewareFor(['index', 'show'], 'check.permission:accounting.activity-types.view');

Route::post('activity-types/{activityType}/rates', [ActivityTypeController::class, 'setRate'])
    ->name('accounting.activity-types.set-rate')->middleware('check.permission:accounting.activity-types.manage');

Route::apiResource('internal-orders', InternalOrderController::class)
    ->names('accounting.internal-orders')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:accounting.internal-orders.manage')->middlewareFor(['index', 'show'], 'check.permission:accounting.internal-orders.view');

Route::post('internal-orders/{internalOrder}/release', [InternalOrderController::class, 'release'])
    ->name('accounting.internal-orders.release')->middleware('check.permission:accounting.internal-orders.manage');

Route::post('internal-orders/{internalOrder}/settle', [InternalOrderController::class, 'settle'])
    ->name('accounting.internal-orders.settle')->middleware('check.permission:accounting.internal-orders.manage');

Route::post('internal-orders/{internalOrder}/technically-complete', [InternalOrderController::class, 'technicallyComplete'])
    ->name('accounting.internal-orders.technically-complete')->middleware('check.permission:accounting.internal-orders.manage');

Route::post('internal-orders/{internalOrder}/close', [InternalOrderController::class, 'close'])
    ->name('accounting.internal-orders.close')->middleware('check.permission:accounting.internal-orders.manage');

Route::get('internal-orders/{internalOrder}/budget-status', [InternalOrderController::class, 'budgetStatus'])
    ->name('accounting.internal-orders.budget-status')->middleware('check.permission:accounting.internal-orders.view');

Route::get('internal-orders/{internalOrder}/variance', [InternalOrderController::class, 'variance'])
    ->name('accounting.internal-orders.variance')->middleware('check.permission:accounting.internal-orders.view');

Route::prefix('copa')->group(function (): void {
    Route::get('profitability', [CopaController::class, 'profitability'])
        ->name('accounting.copa.profitability')->middleware('check.permission:accounting.copa.view');

    Route::get('dimension/{dimension}', [CopaController::class, 'dimensionBreakdown'])
        ->name('accounting.copa.dimension-breakdown')->middleware('check.permission:accounting.copa.view');

    // ----------------------------------------------------------------
    // CO-PA Plan Data & Variance — Gap 2
    // ----------------------------------------------------------------

    Route::get('plan-versions', [CopaController::class, 'planVersions'])
        ->name('accounting.copa.plan-versions.index')->middleware('check.permission:accounting.copa.view');

    Route::post('plan-versions', [CopaController::class, 'storePlanVersion'])
        ->name('accounting.copa.plan-versions.store')->middleware('check.permission:accounting.copa.manage');

    Route::post('plan-versions/{version}/items', [CopaController::class, 'storePlanItems'])
        ->name('accounting.copa.plan-versions.items.store')->middleware('check.permission:accounting.copa.manage');

    Route::get('variance', [CopaController::class, 'varianceReport'])
        ->name('accounting.copa.variance')->middleware('check.permission:accounting.copa.view');
});
