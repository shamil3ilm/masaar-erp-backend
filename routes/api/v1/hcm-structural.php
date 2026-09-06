<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\HR\CompensationController;
use App\Http\Controllers\Api\V1\HR\GosiController;
use App\Http\Controllers\Api\V1\HR\OvertimeController;
use App\Http\Controllers\Api\V1\HR\PositionController;
use App\Http\Controllers\Api\V1\HR\ShiftController;
use Illuminate\Support\Facades\Route;

Route::prefix('compensation')->group(function () {
    Route::get('/', [CompensationController::class, 'index'])->name('hr.compensation.index');
    Route::post('/', [CompensationController::class, 'store'])->name('hr.compensation.store')->middleware('check.permission:hr.compensation.manage');
    Route::post('/{compensationReview}/items', [CompensationController::class, 'addItem'])->name('hr.compensation.add-item')->middleware('check.permission:hr.compensation.manage');
    Route::post('/{compensationReview}/bulk-recommend', [CompensationController::class, 'bulkRecommend'])->name('hr.compensation.bulk-recommend')->middleware('check.permission:hr.compensation.manage');
    Route::post('/{compensationReview}/approve', [CompensationController::class, 'approve'])->name('hr.compensation.approve')->middleware('check.permission:hr.compensation.manage');
    Route::post('/{compensationReview}/apply', [CompensationController::class, 'apply'])->name('hr.compensation.apply')->middleware('check.permission:hr.compensation.manage');
});

Route::apiResource('positions', PositionController::class)->names('hr.positions')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:hr.org.manage');
Route::get('positions-hierarchy', [PositionController::class, 'hierarchy'])->name('hr.positions.hierarchy');
Route::post('positions/{position}/assign', [PositionController::class, 'assignEmployee'])->name('hr.positions.assign')->middleware('check.permission:hr.org.manage');
Route::post('positions/{position}/vacate', [PositionController::class, 'vacatePosition'])->name('hr.positions.vacate')->middleware('check.permission:hr.org.manage');

Route::apiResource('overtime', OvertimeController::class)->names('hr.overtime')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:hr.attendance.manage');
Route::post('overtime/{overtimeRequest}/approve', [OvertimeController::class, 'approve'])->name('hr.overtime.approve')->middleware('check.permission:hr.attendance.manage');
Route::post('overtime/{overtimeRequest}/reject', [OvertimeController::class, 'reject'])->name('hr.overtime.reject')->middleware('check.permission:hr.attendance.manage');
Route::get('overtime/summary/{employeeId}/{year}/{month}', [OvertimeController::class, 'monthlyOtSummary'])->name('hr.overtime.monthly-summary');

// Shifts
Route::prefix('shifts')->name('hr.shifts.')->group(function () {
    Route::get('/', [ShiftController::class, 'index'])->middleware('check.permission:hr.shifts.view')->name('index');
    Route::post('/', [ShiftController::class, 'store'])->middleware('check.permission:hr.shifts.manage')->name('store');
    Route::put('/{id}', [ShiftController::class, 'update'])->middleware('check.permission:hr.shifts.manage')->name('update');
    Route::delete('/{id}', [ShiftController::class, 'destroy'])->middleware('check.permission:hr.shifts.manage')->name('destroy');
    Route::post('/assign', [ShiftController::class, 'assign'])->middleware('check.permission:hr.shifts.manage')->name('assign');
});

// GOSI (Saudi Social Insurance)
Route::prefix('gosi')->name('hr.gosi.')->group(function () {
    Route::get('/', [GosiController::class, 'index'])->middleware('check.permission:hr.gosi.view')->name('index');
    Route::post('/calculate', [GosiController::class, 'calculate'])->middleware('check.permission:hr.gosi.manage')->name('calculate');
    Route::post('/submit-period', [GosiController::class, 'submitPeriod'])->middleware('check.permission:hr.gosi.manage')->name('submit-period');
});
