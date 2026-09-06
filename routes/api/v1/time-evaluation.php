<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HR\TimeEvaluationController;
use Illuminate\Support\Facades\Route;

Route::apiResource('time-sheets', TimeEvaluationController::class)
    ->names('hr.time-sheets')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:hr.attendance.manage')->middlewareFor(['index', 'show'], 'check.permission:hr.attendance.view');

Route::post('time-sheets/{timeSheet}/entries', [TimeEvaluationController::class, 'addEntry'])
    ->name('hr.time-sheets.add-entry')->middleware('check.permission:hr.attendance.manage');

Route::post('time-sheets/{timeSheet}/submit', [TimeEvaluationController::class, 'submit'])
    ->name('hr.time-sheets.submit')->middleware('check.permission:hr.attendance.manage');

Route::post('time-sheets/{timeSheet}/approve', [TimeEvaluationController::class, 'approve'])
    ->name('hr.time-sheets.approve')->middleware('check.permission:hr.attendance.manage');

Route::post('time-sheets/{timeSheet}/reject', [TimeEvaluationController::class, 'reject'])
    ->name('hr.time-sheets.reject')->middleware('check.permission:hr.attendance.manage');

Route::post('time-sheets/{timeSheet}/evaluate', [TimeEvaluationController::class, 'evaluate'])
    ->name('hr.time-sheets.evaluate')->middleware('check.permission:hr.attendance.manage');

Route::post('time-sheets/{timeSheet}/transfer-payroll', [TimeEvaluationController::class, 'transferToPayroll'])
    ->name('hr.time-sheets.transfer-payroll')->middleware('check.permission:hr.attendance.manage');

Route::get('time-sheets/{timeSheet}/cost-allocation', [TimeEvaluationController::class, 'costAllocation'])
    ->name('hr.time-sheets.cost-allocation')->middleware('check.permission:hr.attendance.view');

Route::prefix('wage-types')->group(function (): void {
    Route::get('/', [TimeEvaluationController::class, 'wageTypes'])
        ->name('hr.wage-types.index')->middleware('check.permission:hr.attendance.view');

    Route::post('/', [TimeEvaluationController::class, 'storeWageType'])
        ->name('hr.wage-types.store')->middleware('check.permission:hr.payroll.process');

    Route::put('/{timeWageType}', [TimeEvaluationController::class, 'updateWageType'])
        ->name('hr.wage-types.update')->middleware('check.permission:hr.payroll.process');

    Route::delete('/{timeWageType}', [TimeEvaluationController::class, 'destroyWageType'])
        ->name('hr.wage-types.destroy')->middleware('check.permission:hr.payroll.process');
});
