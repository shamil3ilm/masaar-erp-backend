<?php

use App\Http\Controllers\Api\V1\Maintenance\EquipmentHierarchyController;
use App\Http\Controllers\Api\V1\Maintenance\FaultAnalysisController;
use App\Http\Controllers\Api\V1\Maintenance\FleetController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceNotificationController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenancePermitController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceReportController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceSettlementController;
use App\Http\Controllers\Api\V1\Maintenance\CounterBasedMaintenanceController;
use App\Http\Controllers\Api\V1\Maintenance\MaintenanceTaskListController;
use App\Http\Controllers\Api\V1\Maintenance\ServiceOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Plant Maintenance API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/maintenance (applied in api.php)
|
*/

Route::middleware(['auth:api'])->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Functional Locations
    |--------------------------------------------------------------------------
    */
    Route::prefix('functional-locations')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'functionalLocationIndex'])
            ->middleware('check.permission:maintenance.functional-locations.view')
            ->name('maintenance.functional-locations.index');

        Route::post('/', [MaintenanceController::class, 'functionalLocationStore'])
            ->middleware('check.permission:maintenance.functional-locations.create')
            ->name('maintenance.functional-locations.store');

        Route::get('/{functionalLocation}', [MaintenanceController::class, 'functionalLocationShow'])
            ->middleware('check.permission:maintenance.functional-locations.view')
            ->name('maintenance.functional-locations.show');

        Route::put('/{functionalLocation}', [MaintenanceController::class, 'functionalLocationUpdate'])
            ->middleware('check.permission:maintenance.functional-locations.edit')
            ->name('maintenance.functional-locations.update');

        Route::delete('/{functionalLocation}', [MaintenanceController::class, 'functionalLocationDestroy'])
            ->middleware('check.permission:maintenance.functional-locations.delete')
            ->name('maintenance.functional-locations.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Equipment Categories
    |--------------------------------------------------------------------------
    */
    Route::prefix('equipment-categories')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'categoryIndex'])
            ->middleware('check.permission:maintenance.equipment-categories.view')
            ->name('maintenance.equipment-categories.index');

        Route::post('/', [MaintenanceController::class, 'categoryStore'])
            ->middleware('check.permission:maintenance.equipment-categories.create')
            ->name('maintenance.equipment-categories.store');

        Route::get('/{equipmentCategory}', [MaintenanceController::class, 'categoryShow'])
            ->middleware('check.permission:maintenance.equipment-categories.view')
            ->name('maintenance.equipment-categories.show');

        Route::put('/{equipmentCategory}', [MaintenanceController::class, 'categoryUpdate'])
            ->middleware('check.permission:maintenance.equipment-categories.edit')
            ->name('maintenance.equipment-categories.update');

        Route::delete('/{equipmentCategory}', [MaintenanceController::class, 'categoryDestroy'])
            ->middleware('check.permission:maintenance.equipment-categories.delete')
            ->name('maintenance.equipment-categories.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Equipment
    |--------------------------------------------------------------------------
    */
    Route::prefix('equipment')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'equipmentIndex'])
            ->middleware('check.permission:maintenance.equipment.view')
            ->name('maintenance.equipment.index');

        Route::post('/', [MaintenanceController::class, 'equipmentStore'])
            ->middleware('check.permission:maintenance.equipment.create')
            ->name('maintenance.equipment.store');

        Route::get('/due-soon', [MaintenanceController::class, 'equipmentDueSoon'])
            ->middleware('check.permission:maintenance.equipment.view')
            ->name('maintenance.equipment.due-soon');

        Route::get('/{equipment}', [MaintenanceController::class, 'equipmentShow'])
            ->middleware('check.permission:maintenance.equipment.view')
            ->name('maintenance.equipment.show');

        Route::put('/{equipment}', [MaintenanceController::class, 'equipmentUpdate'])
            ->middleware('check.permission:maintenance.equipment.edit')
            ->name('maintenance.equipment.update');

        Route::delete('/{equipment}', [MaintenanceController::class, 'equipmentDestroy'])
            ->middleware('check.permission:maintenance.equipment.delete')
            ->name('maintenance.equipment.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Maintenance Plans
    |--------------------------------------------------------------------------
    */
    Route::prefix('plans')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'planIndex'])
            ->middleware('check.permission:maintenance.plans.view')
            ->name('maintenance.plans.index');

        Route::post('/', [MaintenanceController::class, 'planStore'])
            ->middleware('check.permission:maintenance.plans.create')
            ->name('maintenance.plans.store');

        Route::put('/{maintenancePlan}', [MaintenanceController::class, 'planUpdate'])
            ->middleware('check.permission:maintenance.plans.edit')
            ->name('maintenance.plans.update');

        Route::post('/{maintenancePlan}/toggle-active', [MaintenanceController::class, 'planToggleActive'])
            ->middleware('check.permission:maintenance.plans.edit')
            ->name('maintenance.plans.toggle-active');

        Route::post('/{maintenancePlan}/generate-order', [MaintenanceController::class, 'planGenerateOrder'])
            ->middleware('check.permission:maintenance.orders.create')
            ->name('maintenance.plans.generate-order');
    });

    /*
    |--------------------------------------------------------------------------
    | Maintenance Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('orders')->group(function (): void {
        Route::get('/', [MaintenanceController::class, 'orderIndex'])
            ->middleware('check.permission:maintenance.orders.view')
            ->name('maintenance.orders.index');

        Route::post('/', [MaintenanceController::class, 'orderStore'])
            ->middleware('check.permission:maintenance.orders.create')
            ->name('maintenance.orders.store');

        Route::get('/{maintenanceOrder}', [MaintenanceController::class, 'orderShow'])
            ->middleware('check.permission:maintenance.orders.view')
            ->name('maintenance.orders.show');

        Route::put('/{maintenanceOrder}', [MaintenanceController::class, 'orderUpdate'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.update');

        Route::delete('/{maintenanceOrder}', [MaintenanceController::class, 'orderDestroy'])
            ->middleware('check.permission:maintenance.orders.delete')
            ->name('maintenance.orders.destroy');

        Route::post('/{maintenanceOrder}/start', [MaintenanceController::class, 'orderStart'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.start');

        Route::post('/{maintenanceOrder}/tasks/{taskId}/complete', [MaintenanceController::class, 'orderCompleteTask'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.complete-task');

        Route::post('/{maintenanceOrder}/complete', [MaintenanceController::class, 'orderComplete'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.complete');

        Route::post('/{maintenanceOrder}/cancel', [MaintenanceController::class, 'orderCancel'])
            ->middleware('check.permission:maintenance.orders.edit')
            ->name('maintenance.orders.cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------------
    */
    Route::get('/stats', [MaintenanceController::class, 'stats'])
        ->middleware('check.permission:maintenance.stats.view')
        ->name('maintenance.stats');

    /*
    |--------------------------------------------------------------------------
    | Fleet Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('fleet')->name('maintenance.fleet.')->group(function (): void {
        Route::get('/', [FleetController::class, 'index'])->name('index')->middleware('check.permission:maintenance.fleet.view');
        Route::post('/', [FleetController::class, 'store'])->name('store')->middleware('check.permission:maintenance.fleet.manage');
        Route::get('/requiring-service', [FleetController::class, 'requiringService'])->name('requiring-service')->middleware('check.permission:maintenance.fleet.view');
        Route::get('/cost-summary', [FleetController::class, 'costSummary'])->name('cost-summary')->middleware('check.permission:maintenance.fleet.view');
        Route::get('/{id}', [FleetController::class, 'show'])->name('show')->middleware('check.permission:maintenance.fleet.view');
        Route::put('/{id}', [FleetController::class, 'update'])->name('update')->middleware('check.permission:maintenance.fleet.manage');
        Route::delete('/{id}', [FleetController::class, 'destroy'])->name('destroy')->middleware('check.permission:maintenance.fleet.manage');
        Route::post('/{vehicleId}/assign', [FleetController::class, 'assign'])->name('assign')->middleware('check.permission:maintenance.fleet.manage');
        Route::post('/{vehicleId}/unassign', [FleetController::class, 'unassign'])->name('unassign')->middleware('check.permission:maintenance.fleet.manage');
        Route::get('/{vehicleId}/mileage-logs', [FleetController::class, 'mileageLogs'])->name('mileage-logs')->middleware('check.permission:maintenance.fleet.view');
        Route::post('/{vehicleId}/mileage-logs', [FleetController::class, 'logMileage'])->name('mileage-logs.store')->middleware('check.permission:maintenance.fleet.manage');
        Route::get('/{vehicleId}/fuel-logs', [FleetController::class, 'fuelLogs'])->name('fuel-logs')->middleware('check.permission:maintenance.fleet.view');
        Route::post('/{vehicleId}/fuel-logs', [FleetController::class, 'logFuel'])->name('fuel-logs.store')->middleware('check.permission:maintenance.fleet.manage');
        Route::get('/{vehicleId}/maintenance', [FleetController::class, 'maintenanceRecords'])->name('maintenance')->middleware('check.permission:maintenance.fleet.view');
        Route::post('/{vehicleId}/maintenance', [FleetController::class, 'recordMaintenance'])->name('maintenance.store')->middleware('check.permission:maintenance.fleet.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Maintenance Order Cost Settlement (PM-WOC-CO)
    |--------------------------------------------------------------------------
    */
    Route::get('/maintenance-orders/unsettled', [MaintenanceSettlementController::class, 'unsettledOrders'])
        ->name('maintenance.unsettled')->middleware('check.permission:maintenance.costs.view');

    Route::prefix('maintenance-orders/{orderId}/costs')->name('maintenance.costs.')->group(function (): void {
        Route::get('/', [MaintenanceSettlementController::class, 'costLines'])->name('index')->middleware('check.permission:maintenance.costs.view');
        Route::post('/', [MaintenanceSettlementController::class, 'addCostLine'])->name('store')->middleware('check.permission:maintenance.costs.manage');
        Route::get('/total', [MaintenanceSettlementController::class, 'totalCost'])->name('total')->middleware('check.permission:maintenance.costs.view');
        Route::post('/settle', [MaintenanceSettlementController::class, 'settle'])->name('settle')->middleware('check.permission:maintenance.costs.manage');
        Route::get('/settlement-history', [MaintenanceSettlementController::class, 'settlementHistory'])->name('history')->middleware('check.permission:maintenance.costs.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Counter-based maintenance scheduling
    |--------------------------------------------------------------------------
    */
    Route::prefix('counters')->name('maintenance.counters.')->group(function (): void {
        Route::get('/', [CounterBasedMaintenanceController::class, 'counters'])->name('index')->middleware('check.permission:maintenance.counter-orders.view');
        Route::post('/', [CounterBasedMaintenanceController::class, 'storeCounter'])->name('store')->middleware('check.permission:maintenance.counters.manage');
        Route::post('/{counterId}/readings', [CounterBasedMaintenanceController::class, 'recordReading'])->name('readings.store')->middleware('check.permission:maintenance.counters.manage');
    });

    Route::prefix('counter-plans')->name('maintenance.counter-plans.')->group(function (): void {
        Route::get('/', [CounterBasedMaintenanceController::class, 'plans'])->name('index')->middleware('check.permission:maintenance.counter-orders.view');
        Route::post('/', [CounterBasedMaintenanceController::class, 'storePlan'])->name('store')->middleware('check.permission:maintenance.counter-plans.manage');
        Route::get('/due', [CounterBasedMaintenanceController::class, 'dueOrders'])->name('due')->middleware('check.permission:maintenance.counter-orders.view');
        Route::post('/{planId}/generate-order', [CounterBasedMaintenanceController::class, 'generateOrder'])->name('generate-order')->middleware('check.permission:maintenance.counter-plans.manage');
    });

    Route::prefix('counter-orders')->name('maintenance.counter-orders.')->group(function (): void {
        Route::get('/', [CounterBasedMaintenanceController::class, 'orders'])->name('index')->middleware('check.permission:maintenance.counter-orders.view');
        Route::post('/{orderId}/complete', [CounterBasedMaintenanceController::class, 'completeOrder'])->name('complete')->middleware('check.permission:maintenance.counter-orders.manage');
    });

    // Work Permits & Safety Checks (PM-WOC-PTW)
    Route::prefix('permits')->name('maintenance.permits.')->group(function (): void {
        Route::get('/', [MaintenancePermitController::class, 'index'])->name('index')->middleware('check.permission:maintenance.permits.view');
        Route::post('/', [MaintenancePermitController::class, 'store'])->name('store')->middleware('check.permission:maintenance.permits.manage');
        Route::get('/{id}', [MaintenancePermitController::class, 'show'])->name('show')->middleware('check.permission:maintenance.permits.view');
        Route::put('/{id}', [MaintenancePermitController::class, 'update'])->name('update')->middleware('check.permission:maintenance.permits.manage');
        Route::post('/{id}/approve', [MaintenancePermitController::class, 'approve'])->name('approve')->middleware('check.permission:maintenance.permits.manage');
        Route::post('/{id}/activate', [MaintenancePermitController::class, 'activate'])->name('activate')->middleware('check.permission:maintenance.permits.manage');
        Route::post('/{id}/suspend', [MaintenancePermitController::class, 'suspend'])->name('suspend')->middleware('check.permission:maintenance.permits.manage');
        Route::post('/{id}/close', [MaintenancePermitController::class, 'close'])->name('close')->middleware('check.permission:maintenance.permits.manage');
        Route::post('/{id}/safety-checks', [MaintenancePermitController::class, 'addSafetyCheck'])->name('safety-checks.store')->middleware('check.permission:maintenance.permits.manage');
        Route::post('/{id}/safety-checks/{checkId}/complete', [MaintenancePermitController::class, 'completeSafetyCheck'])->name('safety-checks.complete')->middleware('check.permission:maintenance.permits.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Maintenance task lists
    |--------------------------------------------------------------------------
    */
    Route::prefix('task-lists')->name('maintenance.task-lists.')->group(function (): void {
        Route::get('/', [MaintenanceTaskListController::class, 'index'])->name('index')->middleware('check.permission:maintenance.task-lists.view');
        Route::post('/', [MaintenanceTaskListController::class, 'store'])->name('store')->middleware('check.permission:maintenance.task-lists.manage');
        Route::get('/{taskList}', [MaintenanceTaskListController::class, 'show'])->name('show')->middleware('check.permission:maintenance.task-lists.view');
        Route::put('/{taskList}', [MaintenanceTaskListController::class, 'update'])->name('update')->middleware('check.permission:maintenance.task-lists.manage');
        Route::delete('/{taskList}', [MaintenanceTaskListController::class, 'destroy'])->name('destroy')->middleware('check.permission:maintenance.task-lists.manage');
        Route::post('/{taskList}/operations', [MaintenanceTaskListController::class, 'storeOperation'])->name('operations.store')->middleware('check.permission:maintenance.task-lists.manage');
        Route::delete('/{taskList}/operations/{operation}', [MaintenanceTaskListController::class, 'destroyOperation'])->name('operations.destroy')->middleware('check.permission:maintenance.task-lists.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | PMIS Reporting — MTBF / MTTR / OEE / Downtime (PM-IS)
    |--------------------------------------------------------------------------
    */
    Route::prefix('maintenance/reports')->name('maintenance.reports.')->group(function (): void {
        Route::get('kpis', [MaintenanceReportController::class, 'kpiDashboard'])->name('kpis')->middleware('check.permission:maintenance.reports.view');
        Route::post('kpis/compute', [MaintenanceReportController::class, 'computeKpis'])->name('kpis.compute')->middleware('check.permission:maintenance.reports.manage');
        Route::get('cost-analysis', [MaintenanceReportController::class, 'costAnalysis'])->name('cost-analysis')->middleware('check.permission:maintenance.reports.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Service Orders — external vendor maintenance (PM-WOC-EXT)
    |--------------------------------------------------------------------------
    */
    Route::apiResource('service-orders', ServiceOrderController::class)
        ->names('maintenance.service-orders')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:maintenance.service-orders.manage')->middlewareFor(['index', 'show'], 'check.permission:maintenance.service-orders.view');

    /*
    |--------------------------------------------------------------------------
    | Fault Codes & Root Cause Analysis (PM-QM)
    |--------------------------------------------------------------------------
    */
    Route::get('fault-codes', [FaultAnalysisController::class, 'indexFaultCodes'])->name('maintenance.fault-codes.index')->middleware('check.permission:maintenance.fault-codes.view');
    Route::post('fault-codes', [FaultAnalysisController::class, 'storeFaultCode'])->name('maintenance.fault-codes.store')->middleware('check.permission:maintenance.fault-codes.manage');

    Route::get('rca', [FaultAnalysisController::class, 'indexRca'])->name('maintenance.rca.index')->middleware('check.permission:maintenance.fault-codes.view');
    Route::post('rca', [FaultAnalysisController::class, 'storeRca'])->name('maintenance.rca.store')->middleware('check.permission:maintenance.rca.manage');
    Route::put('rca/{rca}', [FaultAnalysisController::class, 'updateRca'])->name('maintenance.rca.update')->middleware('check.permission:maintenance.rca.manage');

    /*
    |--------------------------------------------------------------------------
    | Maintenance Notifications (SAP IW21-IW28)
    |--------------------------------------------------------------------------
    */
    Route::prefix('notifications')->name('maintenance.notifications.')->group(function (): void {
        Route::get('/', [MaintenanceNotificationController::class, 'index'])
            ->middleware('check.permission:maintenance.notifications.view')
            ->name('index');

        Route::post('/', [MaintenanceNotificationController::class, 'store'])
            ->middleware('check.permission:maintenance.notifications.create')
            ->name('store');

        Route::get('/equipment/{equipmentId}', [MaintenanceNotificationController::class, 'byEquipment'])
            ->middleware('check.permission:maintenance.notifications.view')
            ->name('by-equipment');

        Route::get('/{uuid}', [MaintenanceNotificationController::class, 'show'])
            ->middleware('check.permission:maintenance.notifications.view')
            ->name('show');

        Route::put('/{uuid}', [MaintenanceNotificationController::class, 'update'])
            ->middleware('check.permission:maintenance.notifications.edit')
            ->name('update');

        Route::post('/{uuid}/complete', [MaintenanceNotificationController::class, 'complete'])
            ->middleware('check.permission:maintenance.notifications.edit')
            ->name('complete');

        Route::post('/{uuid}/tasks', [MaintenanceNotificationController::class, 'addTask'])
            ->middleware('check.permission:maintenance.notifications.edit')
            ->name('tasks.store');
    });
});

/*
|--------------------------------------------------------------------------
| Equipment Hierarchy — SAP PM IL01/IE01
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('equipment-hierarchy')->name('maintenance.equipment-hierarchy.')->group(function (): void {
    Route::get('/tree', [EquipmentHierarchyController::class, 'tree'])->name('tree')->middleware('check.permission:maintenance.equipment-hierarchy.view');
    Route::get('/utilisation-summary', [EquipmentHierarchyController::class, 'utilisationSummary'])->name('utilisation-summary')->middleware('check.permission:maintenance.equipment-hierarchy.view');
    Route::post('/install', [EquipmentHierarchyController::class, 'install'])->name('install')->middleware('check.permission:maintenance.equipment-hierarchy.manage');
    Route::post('/deinstall', [EquipmentHierarchyController::class, 'deinstall'])->name('deinstall')->middleware('check.permission:maintenance.equipment-hierarchy.manage');
    Route::post('/relocate', [EquipmentHierarchyController::class, 'relocate'])->name('relocate')->middleware('check.permission:maintenance.equipment-hierarchy.manage');
    Route::get('/floc/{functionalLocation}/equipment', [EquipmentHierarchyController::class, 'underFloc'])->name('under-floc')->middleware('check.permission:maintenance.equipment-hierarchy.view');
    Route::get('/where-used/{equipment}', [EquipmentHierarchyController::class, 'whereUsed'])->name('where-used')->middleware('check.permission:maintenance.equipment-hierarchy.view');
});
