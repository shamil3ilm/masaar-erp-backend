<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\TM\TransportationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SAP TM — Transportation Management Routes
|--------------------------------------------------------------------------
| Carrier Management, Freight Rate Engine, Tendering, Transportation Orders,
| Load Building / Consolidation (SAP TM equivalent).
*/

$ctrl = TransportationController::class;

// --- Carrier Management ---
Route::prefix('carriers')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listCarriers'])->name('tm.carriers.index')->middleware('check.permission:tm.agreements.view');
    Route::post('/', [$ctrl, 'createCarrier'])->name('tm.carriers.store')->middleware('check.permission:tm.carriers.manage');
    Route::get('/{carrier}', [$ctrl, 'showCarrier'])->name('tm.carriers.show')->middleware('check.permission:tm.agreements.view');
    Route::put('/{carrier}', [$ctrl, 'updateCarrier'])->name('tm.carriers.update')->middleware('check.permission:tm.carriers.manage');

    // Carrier services (service levels)
    Route::post('/{carrier}/services', [$ctrl, 'createCarrierService'])->name('tm.carriers.services.store')->middleware('check.permission:tm.carriers.manage');

    // Carrier performance KPIs
    Route::post('/{carrier}/performance', [$ctrl, 'recordCarrierPerformance'])->name('tm.carriers.performance.store')->middleware('check.permission:tm.carriers.manage');
    Route::get('/{carrier}/performance', [TransportationController::class, 'listCarriers'])
        ->name('tm.carriers.performance.index')->middleware('check.permission:tm.agreements.view'); // placeholder — see showCarrier for performance data
});

// --- Freight Rate Tables ---
Route::prefix('rate-tables')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listRateTables'])->name('tm.rate-tables.index')->middleware('check.permission:tm.agreements.view');
    Route::post('/', [$ctrl, 'createRateTable'])->name('tm.rate-tables.store')->middleware('check.permission:tm.rate-tables.manage');
    Route::post('/{rateTable}/lines', [$ctrl, 'addRateLine'])->name('tm.rate-tables.lines.store')->middleware('check.permission:tm.rate-tables.manage');
});

// Freight cost calculation (rate engine)
Route::post('/calculate-cost', [$ctrl, 'calculateCost'])->name('tm.calculate-cost')->middleware('check.permission:tm.rate-tables.manage');

// --- Freight Agreements ---
Route::prefix('agreements')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listAgreements'])->name('tm.agreements.index')->middleware('check.permission:tm.agreements.view');
    Route::post('/', [$ctrl, 'createAgreement'])->name('tm.agreements.store')->middleware('check.permission:tm.agreements.manage');
    Route::post('/{agreement}/activate', [$ctrl, 'activateAgreement'])->name('tm.agreements.activate')->middleware('check.permission:tm.agreements.manage');
});

// --- Freight Tendering ---
Route::prefix('tenders')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listTenderRequests'])->name('tm.tenders.index')->middleware('check.permission:tm.agreements.view');
    Route::post('/', [$ctrl, 'createTenderRequest'])->name('tm.tenders.store')->middleware('check.permission:tm.tenders.manage');
    Route::post('/{tender}/open', [$ctrl, 'openTenderRequest'])->name('tm.tenders.open')->middleware('check.permission:tm.tenders.manage');
    Route::post('/{tender}/bids', [$ctrl, 'submitBid'])->name('tm.tenders.bids.store')->middleware('check.permission:tm.tenders.manage');
    Route::post('/{tender}/evaluate', [$ctrl, 'evaluateBids'])->name('tm.tenders.evaluate')->middleware('check.permission:tm.tenders.manage');
    Route::post('/{tender}/award', [$ctrl, 'awardTender'])->name('tm.tenders.award')->middleware('check.permission:tm.tenders.manage');
});

// --- Transportation Orders ---
Route::prefix('orders')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listTransportationOrders'])->name('tm.orders.index')->middleware('check.permission:tm.agreements.view');
    Route::post('/', [$ctrl, 'createTransportationOrder'])->name('tm.orders.store')->middleware('check.permission:tm.orders.manage');
    Route::get('/{order}', [$ctrl, 'showTransportationOrder'])->name('tm.orders.show')->middleware('check.permission:tm.agreements.view');
    Route::post('/{order}/status', [$ctrl, 'updateOrderStatus'])->name('tm.orders.status')->middleware('check.permission:tm.orders.manage');
});

// --- Load Plans (Load Building / Consolidation) ---
Route::prefix('load-plans')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listLoadPlans'])->name('tm.load-plans.index')->middleware('check.permission:tm.agreements.view');
    Route::post('/', [$ctrl, 'createLoadPlan'])->name('tm.load-plans.store')->middleware('check.permission:tm.load-plans.manage');
    Route::get('/{loadPlan}', [$ctrl, 'showLoadPlan'])->name('tm.load-plans.show')->middleware('check.permission:tm.agreements.view');
    Route::post('/{loadPlan}/add', [$ctrl, 'addToLoadPlan'])->name('tm.load-plans.add')->middleware('check.permission:tm.load-plans.manage');
    Route::delete('/{loadPlan}/remove', [$ctrl, 'removeFromLoadPlan'])->name('tm.load-plans.remove')->middleware('check.permission:tm.load-plans.manage');
    Route::post('/{loadPlan}/dispatch', [$ctrl, 'dispatchLoadPlan'])->name('tm.load-plans.dispatch')->middleware('check.permission:tm.load-plans.manage');
});

// --- Reports ---
Route::get('/reports/utilization', [$ctrl, 'utilizationReport'])->name('tm.reports.utilization')->middleware('check.permission:tm.agreements.view');
