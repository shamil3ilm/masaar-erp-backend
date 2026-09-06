<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Sales\PricingConditionController;
use Illuminate\Support\Facades\Route;

// Pricing Procedures (SAP condition technique — pricing schema)
Route::get('procedures', [PricingConditionController::class, 'indexProcedures'])->name('sales.pricing.procedures.index');
Route::post('procedures', [PricingConditionController::class, 'storeProcedure'])->name('sales.pricing.procedures.store')->middleware('check.permission:sales.pricing.manage');
Route::get('procedures/{pricingProcedure}', [PricingConditionController::class, 'showProcedure'])->name('sales.pricing.procedures.show');
Route::put('procedures/{pricingProcedure}', [PricingConditionController::class, 'updateProcedure'])->name('sales.pricing.procedures.update')->middleware('check.permission:sales.pricing.manage');
Route::delete('procedures/{pricingProcedure}', [PricingConditionController::class, 'destroyProcedure'])->name('sales.pricing.procedures.destroy')->middleware('check.permission:sales.pricing.manage');

// Condition Types (PR00, K007, MWST, etc.)
Route::get('condition-types', [PricingConditionController::class, 'indexConditionTypes'])->name('sales.pricing.condition-types.index');
Route::post('condition-types', [PricingConditionController::class, 'storeConditionType'])->name('sales.pricing.condition-types.store')->middleware('check.permission:sales.pricing.manage');
Route::get('condition-types/{pricingConditionType}', [PricingConditionController::class, 'showConditionType'])->name('sales.pricing.condition-types.show');
Route::put('condition-types/{pricingConditionType}', [PricingConditionController::class, 'updateConditionType'])->name('sales.pricing.condition-types.update')->middleware('check.permission:sales.pricing.manage');
Route::delete('condition-types/{pricingConditionType}', [PricingConditionController::class, 'destroyConditionType'])->name('sales.pricing.condition-types.destroy')->middleware('check.permission:sales.pricing.manage');

// Condition Records (validity-dated price/discount records per key combination)
Route::get('condition-records', [PricingConditionController::class, 'indexConditionRecords'])->name('sales.pricing.condition-records.index');
Route::post('condition-records', [PricingConditionController::class, 'storeConditionRecord'])->name('sales.pricing.condition-records.store')->middleware('check.permission:sales.pricing.manage');
Route::get('condition-records/{pricingConditionRecord}', [PricingConditionController::class, 'showConditionRecord'])->name('sales.pricing.condition-records.show');
Route::put('condition-records/{pricingConditionRecord}', [PricingConditionController::class, 'updateConditionRecord'])->name('sales.pricing.condition-records.update')->middleware('check.permission:sales.pricing.manage');
Route::delete('condition-records/{pricingConditionRecord}', [PricingConditionController::class, 'destroyConditionRecord'])->name('sales.pricing.condition-records.destroy')->middleware('check.permission:sales.pricing.manage');

// Resolve effective price for a line item
Route::post('resolve', [PricingConditionController::class, 'resolve'])->name('sales.pricing.resolve')->middleware('check.permission:sales.pricing.manage');
