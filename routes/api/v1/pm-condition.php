<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Maintenance\ConditionMaintenanceController;
use Illuminate\Support\Facades\Route;

Route::post('measurements', [ConditionMaintenanceController::class, 'recordMeasurement'])->name('maintenance.measurements.record')->middleware('check.permission:maintenance.measurements.manage');
Route::apiResource('condition-rules', ConditionMaintenanceController::class)->names('maintenance.condition-rules')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:maintenance.condition-rules.manage')->middlewareFor(['index', 'show'], 'check.permission:maintenance.condition-rules.view');
Route::prefix('equipment/{equipmentId}')->group(function () {
    Route::get('spare-parts', [ConditionMaintenanceController::class, 'spareParts'])->name('maintenance.spare-parts.index')->middleware('check.permission:maintenance.condition-rules.view');
    Route::post('spare-parts', [ConditionMaintenanceController::class, 'addSparePart'])->name('maintenance.spare-parts.add')->middleware('check.permission:maintenance.spare-parts.manage');
    Route::get('spare-parts/availability', [ConditionMaintenanceController::class, 'sparePartsAvailability'])->name('maintenance.spare-parts.availability')->middleware('check.permission:maintenance.condition-rules.view');
});
