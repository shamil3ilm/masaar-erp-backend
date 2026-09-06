<?php

use App\Http\Controllers\Api\V1\HR\LeaveAccrualController;
use App\Http\Controllers\Api\V1\HR\LeavePolicyController;
use App\Http\Controllers\Api\V1\HR\PublicHolidayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leave Management API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/leave-management
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Leave Policies
    |--------------------------------------------------------------------------
    */
    Route::prefix('policies')->group(function () {
        Route::get('/', [LeavePolicyController::class, 'index'])->name('leave-management.policies.index');
        Route::post('/', [LeavePolicyController::class, 'store'])->name('leave-management.policies.store')->middleware('check.permission:hr.leave.manage');
        Route::get('/{leavePolicy}', [LeavePolicyController::class, 'show'])->name('leave-management.policies.show');
        Route::put('/{leavePolicy}', [LeavePolicyController::class, 'update'])->name('leave-management.policies.update')->middleware('check.permission:hr.leave.manage');
        Route::delete('/{leavePolicy}', [LeavePolicyController::class, 'destroy'])->name('leave-management.policies.destroy')->middleware('check.permission:hr.leave.manage');
        Route::get('/{leavePolicy}/leave-types', [LeavePolicyController::class, 'leaveTypes'])->name('leave-management.policies.leave-types');
        Route::post('/{leavePolicy}/leave-types', [LeavePolicyController::class, 'storeLeaveType'])->name('leave-management.policies.leave-types.store')->middleware('check.permission:hr.leave.manage');
        Route::get('/{leavePolicy}/accrual-schedule', [LeavePolicyController::class, 'accrualSchedule'])->name('leave-management.policies.accrual-schedule');
    });

    /*
    |--------------------------------------------------------------------------
    | Leave Tiers
    |--------------------------------------------------------------------------
    */
    Route::prefix('tiers')->group(function () {
        Route::post('/leave-types/{leaveType}/assign', [LeavePolicyController::class, 'assignTier'])->name('leave-management.tiers.assign')->middleware('check.permission:hr.leave.manage');
        Route::put('/{leaveTier}', [LeavePolicyController::class, 'updateTier'])->name('leave-management.tiers.update')->middleware('check.permission:hr.leave.manage');
        Route::delete('/{leaveTier}', [LeavePolicyController::class, 'destroyTier'])->name('leave-management.tiers.destroy')->middleware('check.permission:hr.leave.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Public Holidays
    |--------------------------------------------------------------------------
    */
    Route::prefix('public-holidays')->group(function () {
        Route::get('/', [PublicHolidayController::class, 'index'])->name('leave-management.public-holidays.index');
        Route::post('/', [PublicHolidayController::class, 'store'])->name('leave-management.public-holidays.store')->middleware('check.permission:hr.leave.manage');
        Route::post('/bulk', [PublicHolidayController::class, 'bulkStore'])->name('leave-management.public-holidays.bulk-store')->middleware('check.permission:hr.leave.manage');
        Route::get('/{publicHoliday}', [PublicHolidayController::class, 'show'])->name('leave-management.public-holidays.show');
        Route::put('/{publicHoliday}', [PublicHolidayController::class, 'update'])->name('leave-management.public-holidays.update')->middleware('check.permission:hr.leave.manage');
        Route::delete('/{publicHoliday}', [PublicHolidayController::class, 'destroy'])->name('leave-management.public-holidays.destroy')->middleware('check.permission:hr.leave.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Leave Accruals & Adjustments
    |--------------------------------------------------------------------------
    */
    Route::prefix('accruals')->group(function () {
        Route::post('/process', [LeaveAccrualController::class, 'processAccruals'])->name('leave-management.accruals.process')->middleware('check.permission:hr.leave.manage');
        Route::get('/history', [LeaveAccrualController::class, 'accrualHistory'])->name('leave-management.accruals.history');
        Route::get('/balance', [LeaveAccrualController::class, 'getBalance'])->name('leave-management.accruals.balance');
    });

    Route::prefix('adjustments')->group(function () {
        Route::get('/', [LeaveAccrualController::class, 'adjustments'])->name('leave-management.adjustments.index');
        Route::post('/', [LeaveAccrualController::class, 'adjustBalance'])->name('leave-management.adjustments.store')->middleware('check.permission:hr.leave.manage');
    });

    Route::prefix('encashments')->group(function () {
        Route::get('/', [LeaveAccrualController::class, 'encashments'])->name('leave-management.encashments.index');
        Route::post('/', [LeaveAccrualController::class, 'encashLeave'])->name('leave-management.encashments.store')->middleware('check.permission:hr.leave.manage');
        Route::post('/{leaveEncashment}/approve', [LeaveAccrualController::class, 'approveEncashment'])->name('leave-management.encashments.approve')->middleware('check.permission:hr.leave.manage');
    });
});
