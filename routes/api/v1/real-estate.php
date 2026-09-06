<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\RealEstate\RealEstateController;
use App\Http\Controllers\Api\V1\RealEstate\VacancyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SAP RE-FX — Real Estate Flexible Framework Routes
|--------------------------------------------------------------------------
| Portfolio hierarchy, lease contracts, periodic posting, service charge
| settlement, security deposits, vacancy management.
*/

$ctrl = RealEstateController::class;

// --- Portfolio Management ---
Route::prefix('portfolios')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listPortfolios'])->name('re.portfolios.index')->middleware('check.permission:real_estate.buildings.view');
    Route::post('/', [$ctrl, 'createPortfolio'])->name('re.portfolios.store')->middleware('check.permission:real_estate.portfolios.manage');
    Route::get('/overview', [$ctrl, 'portfolioOverview'])->name('re.portfolios.overview')->middleware('check.permission:real_estate.buildings.view');
});

// --- Properties ---
Route::prefix('properties')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listProperties'])->name('re.properties.index')->middleware('check.permission:real_estate.buildings.view');
    Route::post('/', [$ctrl, 'createProperty'])->name('re.properties.store')->middleware('check.permission:real_estate.properties.manage');
    Route::get('/{property}', [$ctrl, 'showProperty'])->name('re.properties.show')->middleware('check.permission:real_estate.buildings.view');

    // Buildings within a property
    Route::post('/{property}/buildings', [$ctrl, 'createBuilding'])->name('re.properties.buildings.store')->middleware('check.permission:real_estate.properties.manage');
});

// --- Buildings ---
Route::prefix('buildings')->group(function () use ($ctrl) {
    // Floors within a building
    Route::post('/{building}/floors', [$ctrl, 'createFloor'])->name('re.buildings.floors.store')->middleware('check.permission:real_estate.buildings.manage');

    // Rental units within a building
    Route::post('/{building}/units', [$ctrl, 'createRentalUnit'])->name('re.buildings.units.store')->middleware('check.permission:real_estate.buildings.manage');
});

// --- Rental Units ---
Route::prefix('units')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listRentalUnits'])->name('re.units.index')->middleware('check.permission:real_estate.buildings.view');
    Route::get('/{unit}', [$ctrl, 'showRentalUnit'])->name('re.units.show')->middleware('check.permission:real_estate.buildings.view');
    // Vacancy management
    Route::post('/{id}/vacate', [VacancyController::class, 'vacate'])->name('re.units.vacate')->middleware('check.permission:real_estate.units.manage');
    Route::post('/{id}/occupy', [VacancyController::class, 'occupy'])->name('re.units.occupy')->middleware('check.permission:real_estate.units.manage');
    Route::get('/{id}/vacancy-history', [VacancyController::class, 'vacancyHistory'])->name('re.units.vacancy-history')->middleware('check.permission:real_estate.buildings.view');
});

Route::prefix('buildings')->group(function () {
    Route::get('/{buildingId}/vacant-units', [VacancyController::class, 'vacantUnits'])->name('re.buildings.vacant-units')->middleware('check.permission:real_estate.buildings.view');
    Route::get('/{buildingId}/occupancy-trend', [VacancyController::class, 'occupancyTrend'])->name('re.buildings.occupancy-trend')->middleware('check.permission:real_estate.buildings.view');
    Route::post('/{buildingId}/snapshot', [VacancyController::class, 'snapshot'])->name('re.buildings.snapshot')->middleware('check.permission:real_estate.buildings.manage');
});

// --- Lease Contracts ---
Route::prefix('contracts')->group(function () use ($ctrl) {
    Route::get('/', [$ctrl, 'listContracts'])->name('re.contracts.index')->middleware('check.permission:real_estate.buildings.view');
    Route::post('/', [$ctrl, 'createContract'])->name('re.contracts.store')->middleware('check.permission:real_estate.contracts.manage');
    Route::get('/{contract}', [$ctrl, 'showContract'])->name('re.contracts.show')->middleware('check.permission:real_estate.buildings.view');
    Route::post('/{contract}/activate', [$ctrl, 'activateContract'])->name('re.contracts.activate')->middleware('check.permission:real_estate.contracts.manage');
    Route::post('/{contract}/terminate', [$ctrl, 'terminateContract'])->name('re.contracts.terminate')->middleware('check.permission:real_estate.contracts.manage');

    // Security deposits
    Route::post('/{contract}/deposits', [$ctrl, 'createDeposit'])->name('re.contracts.deposits.store')->middleware('check.permission:real_estate.contracts.manage');

    // IFRS 16 — Right-of-Use asset & lease liability amortisation
    Route::post('/{contract}/ifrs16/generate', [$ctrl, 'generateIfrs16'])->name('re.contracts.ifrs16.generate')->middleware('check.permission:real_estate.contracts.manage');
    Route::get('/{contract}/ifrs16/schedule', [$ctrl, 'ifrs16Schedule'])->name('re.contracts.ifrs16.schedule')->middleware('check.permission:real_estate.buildings.view');
});

// --- Contract Options ---
Route::prefix('options')->group(function () use ($ctrl) {
    Route::post('/{option}/exercise', [$ctrl, 'exerciseOption'])->name('re.options.exercise')->middleware('check.permission:real_estate.options.manage');
});

// --- Rent Conditions / Escalation ---
Route::prefix('conditions')->group(function () use ($ctrl) {
    Route::post('/{condition}/escalate', [$ctrl, 'applyEscalation'])->name('re.conditions.escalate')->middleware('check.permission:real_estate.conditions.manage');
});

// --- Security Deposits ---
Route::prefix('deposits')->group(function () use ($ctrl) {
    Route::post('/{deposit}/collect', [$ctrl, 'recordDepositCollection'])->name('re.deposits.collect')->middleware('check.permission:real_estate.deposits.manage');
    Route::post('/{deposit}/accrue-interest', [$ctrl, 'accrueDepositInterest'])->name('re.deposits.accrue')->middleware('check.permission:real_estate.deposits.manage');
    Route::post('/{deposit}/refund', [$ctrl, 'refundDeposit'])->name('re.deposits.refund')->middleware('check.permission:real_estate.deposits.manage');
});

// --- Periodic Posting ---
Route::prefix('posting-runs')->group(function () use ($ctrl) {
    Route::post('/simulate', [$ctrl, 'simulatePostingRun'])->name('re.posting-runs.simulate')->middleware('check.permission:real_estate.posting-runs.manage');
    Route::post('/execute', [$ctrl, 'executePostingRun'])->name('re.posting-runs.execute')->middleware('check.permission:real_estate.posting-runs.manage');
});

// --- Service Charge Settlement ---
Route::prefix('settlements')->group(function () use ($ctrl) {
    Route::post('/', [$ctrl, 'createServiceChargeSettlement'])->name('re.settlements.store')->middleware('check.permission:real_estate.settlements.manage');
    Route::post('/{settlement}/calculate', [$ctrl, 'calculateSettlement'])->name('re.settlements.calculate')->middleware('check.permission:real_estate.settlements.manage');
});

// --- Reports ---
Route::prefix('reports')->group(function () use ($ctrl) {
    Route::get('/vacancy', [$ctrl, 'vacancyReport'])->name('re.reports.vacancy')->middleware('check.permission:real_estate.buildings.view');
    Route::get('/occupancy-trend', [VacancyController::class, 'occupancyTrend'])->name('re.reports.occupancy-trend')->middleware('check.permission:real_estate.buildings.view');
    Route::get('/expiring-contracts', [$ctrl, 'expiringContracts'])->name('re.reports.expiring')->middleware('check.permission:real_estate.buildings.view');
    Route::get('/due-escalations', [$ctrl, 'dueEscalations'])->name('re.reports.escalations-due')->middleware('check.permission:real_estate.buildings.view');
    Route::get('/upcoming-escalations', [$ctrl, 'upcomingEscalations'])->name('re.reports.escalations-upcoming')->middleware('check.permission:real_estate.buildings.view');
});
