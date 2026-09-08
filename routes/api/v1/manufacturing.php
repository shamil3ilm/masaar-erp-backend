<?php

use App\Http\Controllers\Api\V1\Manufacturing\AuditManagementController;
use App\Http\Controllers\Api\V1\Manufacturing\BomAlternativeController;
use App\Http\Controllers\Api\V1\Manufacturing\BomController;
use App\Http\Controllers\Api\V1\Manufacturing\CalibrationController;
use App\Http\Controllers\Api\V1\Manufacturing\Capa8DController;
use App\Http\Controllers\Api\V1\Manufacturing\CapacityController;
use App\Http\Controllers\Api\V1\Manufacturing\CapacityLevelingController;
use App\Http\Controllers\Api\V1\Manufacturing\CapaController;
use App\Http\Controllers\Api\V1\Manufacturing\ComplaintController;
use App\Http\Controllers\Api\V1\Manufacturing\CoProductController;
use App\Http\Controllers\Api\V1\Manufacturing\DetailedSchedulingController;
use App\Http\Controllers\Api\V1\Manufacturing\DynamicModificationController;
use App\Http\Controllers\Api\V1\Manufacturing\EngineeringChangeController;
use App\Http\Controllers\Api\V1\Manufacturing\LongTermPlanningController;
use App\Http\Controllers\Api\V1\Manufacturing\MrpController;
use App\Http\Controllers\Api\V1\Manufacturing\ProcessOrderController;
use App\Http\Controllers\Api\V1\Manufacturing\ProcurementInspectionController;
use App\Http\Controllers\Api\V1\Manufacturing\ProductCostCollectorController;
use App\Http\Controllers\Api\V1\Manufacturing\ProductionResourceToolController;
use App\Http\Controllers\Api\V1\Manufacturing\ProductionVersionController;
use App\Http\Controllers\Api\V1\Manufacturing\QInfoRecordController;
use App\Http\Controllers\Api\V1\Manufacturing\QualityCostController;
use App\Http\Controllers\Api\V1\Manufacturing\RepetitiveManufacturingController;
use App\Http\Controllers\Api\V1\Manufacturing\ReturnsInspectionController;
use App\Http\Controllers\Api\V1\Manufacturing\ScrapReportingController;
use App\Http\Controllers\Api\V1\Manufacturing\SkipLotController;
use App\Http\Controllers\Api\V1\Manufacturing\SpcController;
use App\Http\Controllers\Api\V1\Manufacturing\StabilityStudyController;
use App\Http\Controllers\Api\V1\Manufacturing\SupplierQualityController;
use App\Http\Controllers\Api\V1\Manufacturing\WorkCenterController;
use App\Http\Controllers\Api\V1\Manufacturing\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Manufacturing API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | BOM Templates
    |--------------------------------------------------------------------------
    */
    Route::prefix('bom-templates')->group(function () {
        Route::get('/', [BomController::class, 'index'])->middleware('check.permission:manufacturing.bom.view');
        Route::post('/', [BomController::class, 'store'])->middleware('check.permission:manufacturing.bom.create');
        Route::get('/for-product', [BomController::class, 'forProduct'])->middleware('check.permission:manufacturing.bom.view');
        Route::get('/{bom}', [BomController::class, 'show'])->middleware('check.permission:manufacturing.bom.view');
        Route::put('/{bom}', [BomController::class, 'update'])->middleware('check.permission:manufacturing.bom.edit');
        Route::delete('/{bom}', [BomController::class, 'destroy'])->middleware('check.permission:manufacturing.bom.delete');

        // Actions
        Route::patch('/{bom}/active', [BomController::class, 'setActive'])->middleware('check.permission:manufacturing.bom.edit');
        Route::post('/{bom}/duplicate', [BomController::class, 'duplicate'])->middleware('check.permission:manufacturing.bom.create');

        // Analysis
        Route::get('/{bom}/cost-breakdown', [BomController::class, 'costBreakdown'])->middleware('check.permission:manufacturing.bom.view');
        Route::get('/{bom}/check-availability', [BomController::class, 'checkAvailability'])->middleware('check.permission:manufacturing.bom.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Work Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('work-orders')->group(function () {
        Route::get('/', [WorkOrderController::class, 'index'])->middleware('check.permission:manufacturing.workorders.view');
        Route::post('/', [WorkOrderController::class, 'store'])->middleware('check.permission:manufacturing.workorders.create');
        Route::get('/statistics', [WorkOrderController::class, 'statistics'])->middleware('check.permission:manufacturing.workorders.view');
        Route::get('/production-schedule', [WorkOrderController::class, 'productionSchedule'])->middleware('check.permission:manufacturing.workorders.view');
        Route::get('/{workOrder}', [WorkOrderController::class, 'show'])->middleware('check.permission:manufacturing.workorders.view');
        Route::put('/{workOrder}', [WorkOrderController::class, 'update'])->middleware('check.permission:manufacturing.workorders.edit');
        Route::delete('/{workOrder}', [WorkOrderController::class, 'destroy'])->middleware('check.permission:manufacturing.workorders.delete');

        // Status transitions
        Route::post('/{workOrder}/release', [WorkOrderController::class, 'release'])->middleware('check.permission:manufacturing.workorders.edit');
        Route::post('/{workOrder}/schedule', [WorkOrderController::class, 'schedule'])->middleware('check.permission:manufacturing.workorders.edit');
        Route::post('/{workOrder}/start', [WorkOrderController::class, 'start'])->middleware('check.permission:manufacturing.workorders.start');
        Route::post('/{workOrder}/complete', [WorkOrderController::class, 'complete'])->middleware('check.permission:manufacturing.workorders.complete');
        Route::post('/{workOrder}/cancel', [WorkOrderController::class, 'cancel'])->middleware('check.permission:manufacturing.workorders.cancel');

        // Material management
        Route::post('/{workOrder}/issue-materials', [WorkOrderController::class, 'issueMaterials'])->middleware('check.permission:manufacturing.workorders.produce');
        Route::post('/{workOrder}/return-materials', [WorkOrderController::class, 'returnMaterials'])->middleware('check.permission:manufacturing.workorders.produce');
        Route::post('/{workOrder}/consume-materials', [WorkOrderController::class, 'consumeMaterials'])->middleware('check.permission:manufacturing.workorders.produce');

        // Production
        Route::post('/{workOrder}/record-production', [WorkOrderController::class, 'recordProduction'])->middleware('check.permission:manufacturing.workorders.produce');

        // Operations
        Route::post('/{workOrder}/operations/{operation}/start', [WorkOrderController::class, 'startOperation'])->middleware('check.permission:manufacturing.workorders.produce');
        Route::post('/{workOrder}/operations/{operation}/complete', [WorkOrderController::class, 'completeOperation'])->middleware('check.permission:manufacturing.workorders.produce');
    });

    /*
    |--------------------------------------------------------------------------
    | MRP (Material Requirements Planning)
    |--------------------------------------------------------------------------
    */
    Route::prefix('mrp')->name('manufacturing.mrp.')->group(function () {
        Route::get('/runs', [MrpController::class, 'index'])->middleware('check.permission:manufacturing.mrp.view')->name('runs.index');
        Route::post('/runs', [MrpController::class, 'run'])->middleware('check.permission:manufacturing.mrp.run')->name('runs.run');
        Route::get('/runs/{id}', [MrpController::class, 'show'])->middleware('check.permission:manufacturing.mrp.view')->name('runs.show');
        Route::get('/runs/{id}/planned-orders', [MrpController::class, 'plannedOrders'])->middleware('check.permission:manufacturing.mrp.view')->name('runs.planned-orders');
        Route::post('/runs/{mrpRun}/convert-to-pr', [MrpController::class, 'convertToPR'])->middleware('check.permission:manufacturing.mrp.convert')->name('runs.convert-to-pr');
        Route::post('/planned-orders/{id}/firm', [MrpController::class, 'firmOrder'])->middleware('check.permission:manufacturing.mrp.manage')->name('planned-orders.firm');
        Route::post('/planned-orders/{id}/convert', [MrpController::class, 'convertOrder'])->middleware('check.permission:manufacturing.mrp.convert')->name('planned-orders.convert');
        Route::get('/exceptions', [MrpController::class, 'exceptions'])->middleware('check.permission:manufacturing.mrp.view')->name('exceptions');
        Route::get('/forecasts', [MrpController::class, 'forecasts'])->middleware('check.permission:manufacturing.mrp.view')->name('forecasts.index');
        Route::post('/forecasts', [MrpController::class, 'storeForecast'])->middleware('check.permission:manufacturing.mrp.manage')->name('forecasts.store');
        Route::put('/forecasts/{id}', [MrpController::class, 'updateForecast'])->middleware('check.permission:manufacturing.mrp.manage')->name('forecasts.update');
        Route::delete('/forecasts/{id}', [MrpController::class, 'destroyForecast'])->middleware('check.permission:manufacturing.mrp.manage')->name('forecasts.destroy');
        Route::get('/forecast-accuracy', [MrpController::class, 'forecastAccuracy'])->middleware('check.permission:manufacturing.mrp.view')->name('forecast-accuracy');
        Route::post('/capacity-check', [MrpController::class, 'capacityCheck'])->middleware('check.permission:manufacturing.mrp.manage')->name('capacity-check');
        Route::get('/capacity-load', [MrpController::class, 'capacityLoad'])->middleware('check.permission:manufacturing.mrp.view')->name('capacity-load');
    });

    /*
    |--------------------------------------------------------------------------
    | Work Centers & Capacity Planning
    |--------------------------------------------------------------------------
    */
    Route::prefix('work-centers')->name('manufacturing.work-centers.')->group(function (): void {
        Route::get('/', [WorkCenterController::class, 'index'])->middleware('check.permission:manufacturing.capacity.view')->name('index');
        Route::post('/', [WorkCenterController::class, 'store'])->middleware('check.permission:manufacturing.capacity.create')->name('store');
        Route::get('/{workCenter}', [WorkCenterController::class, 'show'])->middleware('check.permission:manufacturing.capacity.view')->name('show');
        Route::put('/{workCenter}', [WorkCenterController::class, 'update'])->middleware('check.permission:manufacturing.capacity.edit')->name('update');
        Route::delete('/{workCenter}', [WorkCenterController::class, 'destroy'])->middleware('check.permission:manufacturing.capacity.delete')->name('destroy');
        Route::post('/{workCenter}/exceptions', [WorkCenterController::class, 'storeException'])->middleware('check.permission:manufacturing.capacity.edit')->name('exceptions.store');
        Route::get('/{workCenter}/load', [CapacityController::class, 'workCenterLoad'])->middleware('check.permission:manufacturing.capacity.view')->name('load');
    });

    Route::prefix('capacity')->name('manufacturing.capacity.')->group(function (): void {
        Route::get('/load', [CapacityController::class, 'capacityLoad'])->middleware('check.permission:manufacturing.capacity.view')->name('load');
        Route::get('/bottlenecks', [CapacityController::class, 'bottlenecks'])->middleware('check.permission:manufacturing.capacity.view')->name('bottlenecks');
        Route::get('/requirements', [CapacityController::class, 'requirements'])->middleware('check.permission:manufacturing.capacity.view')->name('requirements');
    });

    Route::prefix('capacity-leveling')->name('manufacturing.capacity-leveling.')->group(function (): void {
        Route::get('/suggest', [CapacityLevelingController::class, 'suggest'])->middleware('check.permission:manufacturing.capacity.view')->name('suggest');
        Route::post('/apply', [CapacityLevelingController::class, 'apply'])->middleware('check.permission:manufacturing.capacity.manage')->name('apply');
        Route::get('/work-orders/{workOrder}/alternative-work-centers', [CapacityLevelingController::class, 'alternativeWorkCenters'])->middleware('check.permission:manufacturing.capacity.view')->name('alternative-work-centers');
    });

    /*
    |--------------------------------------------------------------------------
    | Statistical Process Control (SPC)
    |--------------------------------------------------------------------------
    */
    Route::prefix('spc')->middleware('check.permission:manufacturing.quality.view')->group(function (): void {
        Route::post('/xbar-r', [SpcController::class, 'calculateXbarR'])->name('manufacturing.spc.xbar-r');
        Route::post('/cpk', [SpcController::class, 'calculateCpk'])->name('manufacturing.spc.cpk');
        Route::get('/inspection-lot/{inspectionLot}/chart', [SpcController::class, 'inspectionLotChart'])
            ->name('manufacturing.spc.inspection_lot_chart')->middleware('check.permission:manufacturing.quality.view');

        // Persistent SPC charts
        Route::post('/charts', [SpcController::class, 'createChart'])
            ->middleware('check.permission:manufacturing.quality.create')
            ->name('manufacturing.spc.charts.store');
        Route::post('/charts/{chartId}/subgroups', [SpcController::class, 'recordSubgroup'])
            ->middleware('check.permission:manufacturing.quality.create')
            ->name('manufacturing.spc.subgroups.store');
        Route::get('/charts/{chartId}/trend', [SpcController::class, 'trend'])
            ->name('manufacturing.spc.trend')->middleware('check.permission:manufacturing.quality.view');
    });

    /*
    |--------------------------------------------------------------------------
    | QM-CA: Calibration Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('calibration')->name('manufacturing.calibration.')->group(function (): void {
        Route::get('/equipment', [CalibrationController::class, 'equipment'])->name('equipment')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/equipment', [CalibrationController::class, 'storeEquipment'])->name('equipment.store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/equipment/{id}', [CalibrationController::class, 'showEquipment'])->name('equipment.show')->middleware('check.permission:manufacturing.quality.view');
        Route::put('/equipment/{id}', [CalibrationController::class, 'updateEquipment'])->name('equipment.update')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/plans', [CalibrationController::class, 'plans'])->name('plans')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/plans', [CalibrationController::class, 'storePlan'])->name('plans.store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/plans/{id}', [CalibrationController::class, 'showPlan'])->name('plans.show')->middleware('check.permission:manufacturing.quality.view');
        Route::get('/orders', [CalibrationController::class, 'orders'])->name('orders')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/orders', [CalibrationController::class, 'storeOrder'])->name('orders.store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/orders/{id}', [CalibrationController::class, 'showOrder'])->name('orders.show')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/orders/{id}/complete', [CalibrationController::class, 'completeOrder'])->name('orders.complete')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/orders/{id}/certificates', [CalibrationController::class, 'certificates'])->name('orders.certificates')->middleware('check.permission:manufacturing.quality.view');
        Route::get('/overdue', [CalibrationController::class, 'overdue'])->name('overdue')->middleware('check.permission:manufacturing.quality.view');
        Route::get('/upcoming', [CalibrationController::class, 'upcoming'])->name('upcoming')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/generate-orders', [CalibrationController::class, 'generateOrders'])->name('generate-orders')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM-IM: QM in Procurement (Goods Receipt Inspection)
    |--------------------------------------------------------------------------
    */
    Route::prefix('procurement-inspection')->name('manufacturing.procurement-inspection.')->group(function (): void {
        Route::get('/configs', [ProcurementInspectionController::class, 'configs'])->name('configs')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/configs', [ProcurementInspectionController::class, 'storeConfig'])->name('configs.store')->middleware('check.permission:manufacturing.quality.manage');
        Route::put('/configs/{id}', [ProcurementInspectionController::class, 'updateConfig'])->name('configs.update')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/inspections', [ProcurementInspectionController::class, 'inspections'])->name('inspections')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/inspections', [ProcurementInspectionController::class, 'createInspection'])->name('inspections.store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/inspections/{id}', [ProcurementInspectionController::class, 'showInspection'])->name('inspections.show')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/inspections/{id}/results', [ProcurementInspectionController::class, 'recordResults'])->name('inspections.results')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/inspections/{id}/approve', [ProcurementInspectionController::class, 'approve'])->name('inspections.approve')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/inspections/{id}/reject', [ProcurementInspectionController::class, 'reject'])->name('inspections.reject')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/vendor/{vendorId}/quality-score', [ProcurementInspectionController::class, 'vendorQualityScore'])->name('vendor-quality-score')->middleware('check.permission:manufacturing.quality.view');
    });

    /*
    |--------------------------------------------------------------------------
    | Production Versions (SAP PP - Production Versions)
    |--------------------------------------------------------------------------
    */
    Route::prefix('production-versions')->name('manufacturing.production-versions.')->group(function (): void {
        Route::get('/', [ProductionVersionController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.planning.view');
        Route::post('/', [ProductionVersionController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.planning.manage');
        Route::get('/product/{productId}', [ProductionVersionController::class, 'forProduct'])->name('for-product')->middleware('check.permission:manufacturing.planning.view');
        Route::get('/{id}', [ProductionVersionController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.planning.view');
        Route::put('/{id}', [ProductionVersionController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.planning.manage');
        Route::delete('/{id}', [ProductionVersionController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.planning.manage');
        Route::post('/{id}/set-default', [ProductionVersionController::class, 'setDefault'])->name('set-default')->middleware('check.permission:manufacturing.planning.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Repetitive Manufacturing (SAP PP-REM)
    |--------------------------------------------------------------------------
    */
    Route::prefix('repetitive-manufacturing')->name('manufacturing.repetitive.')->group(function (): void {
        Route::get('/lines', [RepetitiveManufacturingController::class, 'lines'])->name('lines')->middleware('check.permission:manufacturing.production.view');
        Route::post('/lines', [RepetitiveManufacturingController::class, 'storeLine'])->name('lines.store')->middleware('check.permission:manufacturing.production.manage');
        Route::get('/schedules', [RepetitiveManufacturingController::class, 'schedules'])->name('schedules')->middleware('check.permission:manufacturing.production.view');
        Route::post('/schedules', [RepetitiveManufacturingController::class, 'storeSchedule'])->name('schedules.store')->middleware('check.permission:manufacturing.production.manage');
        Route::get('/schedules/{id}', [RepetitiveManufacturingController::class, 'showSchedule'])->name('schedules.show')->middleware('check.permission:manufacturing.production.view');
        Route::get('/schedules/{id}/progress', [RepetitiveManufacturingController::class, 'progress'])->name('schedules.progress')->middleware('check.permission:manufacturing.production.view');
        Route::post('/schedule-lines/{lineId}/confirm', [RepetitiveManufacturingController::class, 'confirmLine'])->name('lines.confirm')->middleware('check.permission:manufacturing.production.manage');
        Route::post('/backflush', [RepetitiveManufacturingController::class, 'backflush'])->name('backflush')->middleware('check.permission:manufacturing.production.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Process Manufacturing / Process Industries (SAP PP-PI)
    |--------------------------------------------------------------------------
    */
    Route::prefix('process')->name('manufacturing.process.')->group(function (): void {
        Route::get('/recipes', [ProcessOrderController::class, 'recipes'])->name('recipes')->middleware('check.permission:manufacturing.production.view');
        Route::post('/recipes', [ProcessOrderController::class, 'storeRecipe'])->name('recipes.store')->middleware('check.permission:manufacturing.production.manage');
        Route::get('/recipes/{id}', [ProcessOrderController::class, 'showRecipe'])->name('recipes.show')->middleware('check.permission:manufacturing.production.view');
        Route::get('/orders', [ProcessOrderController::class, 'orders'])->name('orders')->middleware('check.permission:manufacturing.production.view');
        Route::post('/orders', [ProcessOrderController::class, 'storeOrder'])->name('orders.store')->middleware('check.permission:manufacturing.production.manage');
        Route::get('/orders/{id}', [ProcessOrderController::class, 'showOrder'])->name('orders.show')->middleware('check.permission:manufacturing.production.view');
        Route::post('/orders/{id}/release', [ProcessOrderController::class, 'releaseOrder'])->name('orders.release')->middleware('check.permission:manufacturing.production.manage');
        Route::post('/orders/{id}/complete', [ProcessOrderController::class, 'completeOrder'])->name('orders.complete')->middleware('check.permission:manufacturing.production.manage');
        Route::post('/phases/{phaseId}/start', [ProcessOrderController::class, 'startPhase'])->name('phases.start')->middleware('check.permission:manufacturing.production.manage');
        Route::post('/phases/{phaseId}/complete', [ProcessOrderController::class, 'completePhase'])->name('phases.complete')->middleware('check.permission:manufacturing.production.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Long-Term Planning Simulation (SAP PP-LTP)
    |--------------------------------------------------------------------------
    */
    Route::prefix('long-term-planning')->name('manufacturing.ltp.')->group(function (): void {
        Route::get('/', [LongTermPlanningController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.planning.view');
        Route::post('/', [LongTermPlanningController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.planning.manage');
        Route::get('/{id}', [LongTermPlanningController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.planning.view');
        Route::put('/{id}', [LongTermPlanningController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.planning.manage');
        Route::delete('/{id}', [LongTermPlanningController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.planning.manage');
        Route::post('/{id}/run', [LongTermPlanningController::class, 'run'])->name('run')->middleware('check.permission:manufacturing.planning.manage');
        Route::get('/{id}/capacity', [LongTermPlanningController::class, 'capacity'])->name('capacity')->middleware('check.permission:manufacturing.planning.view');
        Route::get('/{id}/planned-orders', [LongTermPlanningController::class, 'plannedOrders'])->name('planned-orders')->middleware('check.permission:manufacturing.planning.view');
        Route::get('/{id}/compare', [LongTermPlanningController::class, 'compare'])->name('compare')->middleware('check.permission:manufacturing.planning.view');
    });

    /*
    |--------------------------------------------------------------------------
    | QM-RE: Returns Inspection
    |--------------------------------------------------------------------------
    */
    Route::prefix('returns-inspection')->name('qm.returns-inspection.')->group(function (): void {
        Route::get('/', [ReturnsInspectionController::class, 'index'])->middleware('check.permission:manufacturing.quality.view')->name('index');
        Route::post('/', [ReturnsInspectionController::class, 'store'])->middleware('check.permission:manufacturing.quality.manage')->name('store');
        Route::get('/{id}', [ReturnsInspectionController::class, 'show'])->middleware('check.permission:manufacturing.quality.view')->name('show');
        Route::post('/{id}/start-inspection', [ReturnsInspectionController::class, 'startInspection'])->middleware('check.permission:manufacturing.quality.manage')->name('start');
        Route::post('/{id}/defects', [ReturnsInspectionController::class, 'addDefect'])->middleware('check.permission:manufacturing.quality.manage')->name('defects.add');
        Route::put('/{id}/defects/{defectId}', [ReturnsInspectionController::class, 'updateDefect'])->middleware('check.permission:manufacturing.quality.manage')->name('defects.update');
        Route::delete('/{id}/defects/{defectId}', [ReturnsInspectionController::class, 'removeDefect'])->middleware('check.permission:manufacturing.quality.manage')->name('defects.remove');
        Route::post('/{id}/usage-decision', [ReturnsInspectionController::class, 'makeUsageDecision'])->middleware('check.permission:manufacturing.quality.manage')->name('usage-decision');
        Route::post('/{id}/post-stock', [ReturnsInspectionController::class, 'postStock'])->middleware('check.permission:manufacturing.quality.manage')->name('post-stock');
        Route::post('/{id}/cancel', [ReturnsInspectionController::class, 'cancel'])->middleware('check.permission:manufacturing.quality.manage')->name('cancel');
    });

    /*
    |--------------------------------------------------------------------------
    | Detailed Scheduling (SAP PP-DS)
    |--------------------------------------------------------------------------
    */
    Route::prefix('detailed-scheduling')->name('manufacturing.scheduling.')->group(function (): void {
        Route::get('/boards', [DetailedSchedulingController::class, 'boards'])->name('boards')->middleware('check.permission:manufacturing.planning.view');
        Route::post('/boards', [DetailedSchedulingController::class, 'storeBoard'])->name('boards.store')->middleware('check.permission:manufacturing.planning.manage');
        Route::get('/boards/{id}/data', [DetailedSchedulingController::class, 'boardData'])->name('boards.data')->middleware('check.permission:manufacturing.planning.view');
        Route::get('/operations', [DetailedSchedulingController::class, 'operations'])->name('operations')->middleware('check.permission:manufacturing.planning.view');
        Route::post('/operations', [DetailedSchedulingController::class, 'storeOperation'])->name('operations.store')->middleware('check.permission:manufacturing.planning.manage');
        Route::put('/operations/{id}', [DetailedSchedulingController::class, 'updateOperation'])->name('operations.update')->middleware('check.permission:manufacturing.planning.manage');
        Route::post('/operations/{id}/reschedule', [DetailedSchedulingController::class, 'reschedule'])->name('operations.reschedule')->middleware('check.permission:manufacturing.planning.manage');
        Route::post('/optimize', [DetailedSchedulingController::class, 'optimize'])->name('optimize')->middleware('check.permission:manufacturing.planning.manage');
        Route::get('/conflicts', [DetailedSchedulingController::class, 'conflicts'])->name('conflicts')->middleware('check.permission:manufacturing.planning.view');
    });
});

// Product Cost Collectors (CO-PC-OBJ / Repetitive Manufacturing)
Route::middleware(['auth:api'])->prefix('cost-collectors')->name('pp.cost-collectors.')->group(function () {
    Route::get('/', [ProductCostCollectorController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.production.view');
    Route::get('/{id}', [ProductCostCollectorController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.production.view');
    Route::post('/{id}/post-cost', [ProductCostCollectorController::class, 'postCost'])->name('post-cost')->middleware('check.permission:manufacturing.production.manage');
    Route::post('/{id}/recalculate', [ProductCostCollectorController::class, 'recalculate'])->name('recalculate')->middleware('check.permission:manufacturing.production.manage');
    Route::post('/{id}/close', [ProductCostCollectorController::class, 'close'])->name('close')->middleware('check.permission:manufacturing.production.manage');
});

// BOM Alternatives
// {productId} and {id} are numeric so /bom-alternatives/determine, declared
// at the end of this group, is not swallowed by /bom-alternatives/{productId}.
Route::middleware(['auth:api'])->prefix('bom-alternatives')->name('pp.bom-alternatives.')
    ->whereNumber(['productId', 'id'])->group(function () {
        Route::get('/{productId}', [BomAlternativeController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.planning.view');
        Route::post('/{productId}', [BomAlternativeController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.planning.manage');
        Route::get('/{productId}/{id}', [BomAlternativeController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.planning.view');
        Route::put('/{productId}/{id}', [BomAlternativeController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.planning.manage');
        Route::delete('/{productId}/{id}', [BomAlternativeController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.planning.manage');
        Route::post('/{productId}/{id}/set-default', [BomAlternativeController::class, 'setDefault'])->name('set-default')->middleware('check.permission:manufacturing.planning.manage');
        Route::post('/determine', [BomAlternativeController::class, 'determine'])->name('determine')->middleware('check.permission:manufacturing.planning.manage');
    });

// Engineering Change Management
Route::middleware(['auth:api'])->prefix('engineering-changes')->name('pp.ecm.')->group(function () {
    Route::get('/', [EngineeringChangeController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.planning.view');
    Route::post('/', [EngineeringChangeController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.planning.manage');
    Route::get('/for-object', [EngineeringChangeController::class, 'getForObject'])->name('for-object')->middleware('check.permission:manufacturing.planning.view');
    Route::get('/{id}', [EngineeringChangeController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.planning.view');
    Route::put('/{id}', [EngineeringChangeController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.planning.manage');
    Route::delete('/{id}', [EngineeringChangeController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.planning.manage');
    Route::post('/{id}/submit', [EngineeringChangeController::class, 'submit'])->name('submit')->middleware('check.permission:manufacturing.planning.manage');
    Route::post('/{id}/approve', [EngineeringChangeController::class, 'approve'])->name('approve')->middleware('check.permission:manufacturing.planning.manage');
    Route::post('/{id}/reject', [EngineeringChangeController::class, 'reject'])->name('reject')->middleware('check.permission:manufacturing.planning.manage');
    Route::post('/{id}/implement', [EngineeringChangeController::class, 'implement'])->name('implement')->middleware('check.permission:manufacturing.planning.manage');
    Route::post('/{id}/affected-objects', [EngineeringChangeController::class, 'addAffectedObject'])->name('affected-objects.add')->middleware('check.permission:manufacturing.planning.manage');
});

// Production Resource Tools
Route::middleware(['auth:api'])->prefix('production-resources')->name('pp.prt.')->group(function () {
    Route::get('/', [ProductionResourceToolController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.planning.view');
    Route::post('/', [ProductionResourceToolController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.planning.manage');
    Route::get('/available', [ProductionResourceToolController::class, 'getAvailable'])->name('available')->middleware('check.permission:manufacturing.planning.view');
    Route::get('/for-work-order/{workOrderId}', [ProductionResourceToolController::class, 'getForWorkOrder'])->name('for-work-order')->middleware('check.permission:manufacturing.planning.view');
    Route::get('/{id}', [ProductionResourceToolController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.planning.view');
    Route::put('/{id}', [ProductionResourceToolController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.planning.manage');
    Route::delete('/{id}', [ProductionResourceToolController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.planning.manage');
    Route::post('/{id}/assign', [ProductionResourceToolController::class, 'assign'])->name('assign')->middleware('check.permission:manufacturing.planning.manage');
    Route::post('/{id}/assignments/{assignmentId}/release', [ProductionResourceToolController::class, 'release'])->name('release')->middleware('check.permission:manufacturing.planning.manage');
});

// Co-Products & By-Products
Route::middleware(['auth:api'])->prefix('co-products')->name('pp.co-products.')->group(function () {
    Route::get('/bom/{bomId}', [CoProductController::class, 'indexForBom'])->name('bom-index')->middleware('check.permission:manufacturing.planning.view');
    Route::post('/bom/{bomId}', [CoProductController::class, 'addToBom'])->name('bom-add')->middleware('check.permission:manufacturing.planning.manage');
    Route::put('/bom/{bomId}/{id}', [CoProductController::class, 'updateCoProduct'])->name('update')->middleware('check.permission:manufacturing.planning.manage');
    Route::delete('/bom/{bomId}/{id}', [CoProductController::class, 'removeFromBom'])->name('remove')->middleware('check.permission:manufacturing.planning.manage');
    Route::get('/work-order/{workOrderId}', [CoProductController::class, 'indexForWorkOrder'])->name('wo-index')->middleware('check.permission:manufacturing.planning.view');
    Route::post('/work-order/{workOrderId}/actuals', [CoProductController::class, 'postActuals'])->name('wo-actuals')->middleware('check.permission:manufacturing.planning.manage');
    Route::post('/work-order/{workOrderId}/actuals/{actualId}/post-stock', [CoProductController::class, 'postToStock'])->name('wo-post-stock')->middleware('check.permission:manufacturing.planning.manage');
});

// Scrap Reporting
Route::middleware(['auth:api'])->prefix('scrap-reports')->name('pp.scrap.')->group(function () {
    Route::get('/', [ScrapReportingController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.production.view');
    Route::post('/', [ScrapReportingController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.production.manage');
    Route::get('/summary', [ScrapReportingController::class, 'summary'])->name('summary')->middleware('check.permission:manufacturing.production.view');
    Route::get('/{id}', [ScrapReportingController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.production.view');
    Route::put('/{id}', [ScrapReportingController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.production.manage');
    Route::delete('/{id}', [ScrapReportingController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.production.manage');
    Route::post('/{id}/post-gl', [ScrapReportingController::class, 'postToGL'])->name('post-gl')->middleware('check.permission:manufacturing.production.manage');
});

/*
|--------------------------------------------------------------------------
| QM: Skip Lots / Sampling Procedures
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->group(function (): void {
    Route::prefix('skip-lot-plans')->name('qm.skip-lot.')->group(function (): void {
        Route::get('/', [SkipLotController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [SkipLotController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/{id}', [SkipLotController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::put('/{id}', [SkipLotController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.quality.manage');
        Route::delete('/{id}', [SkipLotController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.quality.manage');
    });

    Route::prefix('skip-lot-decisions')->name('qm.skip-lot-decisions.')->group(function (): void {
        Route::get('/', [SkipLotController::class, 'decisions'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/should-inspect', [SkipLotController::class, 'shouldInspect'])->name('should-inspect')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/record-result', [SkipLotController::class, 'recordResult'])->name('record-result')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Quality Cost Analysis
    |--------------------------------------------------------------------------
    */
    Route::prefix('quality-costs')->name('qm.quality-costs.')->group(function (): void {
        Route::get('/', [QualityCostController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [QualityCostController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/summary', [QualityCostController::class, 'summary'])->name('summary')->middleware('check.permission:manufacturing.quality.view');
        Route::get('/trend', [QualityCostController::class, 'trend'])->name('trend')->middleware('check.permission:manufacturing.quality.view');
        Route::get('/{id}', [QualityCostController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::put('/{id}', [QualityCostController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.quality.manage');
        Route::delete('/{id}', [QualityCostController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Q-Info Records
    |--------------------------------------------------------------------------
    */
    Route::prefix('q-info-records')->name('qm.q-info.')->group(function (): void {
        Route::get('/', [QInfoRecordController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [QInfoRecordController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/due-for-inspection', [QInfoRecordController::class, 'dueForInspection'])->name('due')->middleware('check.permission:manufacturing.quality.view');
        Route::get('/{id}', [QInfoRecordController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::put('/{id}', [QInfoRecordController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.quality.manage');
        Route::delete('/{id}', [QInfoRecordController::class, 'destroy'])->name('destroy')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Stability Studies
    |--------------------------------------------------------------------------
    */
    Route::prefix('stability-studies')->name('qm.stability.')->group(function (): void {
        Route::get('/', [StabilityStudyController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [StabilityStudyController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/{id}', [StabilityStudyController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::put('/{id}', [StabilityStudyController::class, 'update'])->name('update')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/{id}/summary', [StabilityStudyController::class, 'summary'])->name('summary')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/{id}/activate', [StabilityStudyController::class, 'activate'])->name('activate')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/complete', [StabilityStudyController::class, 'complete'])->name('complete')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/time-points', [StabilityStudyController::class, 'addTimePoint'])->name('timepoints.add')->middleware('check.permission:manufacturing.quality.manage');
        Route::put('/{id}/time-points/{tpId}', [StabilityStudyController::class, 'updateTimePoint'])->name('timepoints.update')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/time-points/{tpId}/results', [StabilityStudyController::class, 'addResult'])->name('results.add')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Audit Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('audit-plans')->name('qm.audits.')->group(function (): void {
        Route::get('/', [AuditManagementController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [AuditManagementController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/{id}', [AuditManagementController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/{id}/checklists', [AuditManagementController::class, 'addChecklist'])->name('checklists.add')->middleware('check.permission:manufacturing.quality.manage');
        Route::put('/{id}/checklists/{checklistId}', [AuditManagementController::class, 'updateChecklist'])->name('checklists.update')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/findings', [AuditManagementController::class, 'addFinding'])->name('findings.add')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/findings/{findingId}/close', [AuditManagementController::class, 'closeFinding'])->name('findings.close')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/report', [AuditManagementController::class, 'createReport'])->name('report.create')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: CAPA Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('capas')->name('qm.capas.')->group(function (): void {
        Route::get('/', [CapaController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [CapaController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/{id}', [CapaController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/{id}/actions', [CapaController::class, 'addAction'])->name('actions.add')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/actions/{actionId}/complete', [CapaController::class, 'completeAction'])->name('actions.complete')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/effectiveness-reviews', [CapaController::class, 'addEffectivenessReview'])->name('effectiveness.add')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Complaint Management
    |--------------------------------------------------------------------------
    */
    Route::prefix('complaints')->name('qm.complaints.')->group(function (): void {
        Route::get('/', [ComplaintController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [ComplaintController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/{id}', [ComplaintController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/{id}/communications', [ComplaintController::class, 'addCommunication'])->name('comms.add')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{id}/resolve', [ComplaintController::class, 'resolve'])->name('resolve')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Supplier Quality
    |--------------------------------------------------------------------------
    */
    Route::prefix('supplier-quality')->name('qm.sq.')->group(function (): void {
        Route::get('/ratings', [SupplierQualityController::class, 'ratings'])->name('ratings')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/ratings', [SupplierQualityController::class, 'storeRating'])->name('ratings.store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/avl', [SupplierQualityController::class, 'avl'])->name('avl')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/avl', [SupplierQualityController::class, 'storeAvl'])->name('avl.store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/ncrs', [SupplierQualityController::class, 'ncrs'])->name('ncrs')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/ncrs', [SupplierQualityController::class, 'storeNcr'])->name('ncrs.store')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/ncrs/{id}/close', [SupplierQualityController::class, 'closeNcr'])->name('ncrs.close')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: Dynamic Modification Rules (SAP QP27)
    |--------------------------------------------------------------------------
    */
    Route::prefix('dynamic-modification-rules')->name('qm.dmr.')->group(function (): void {
        Route::get('/', [DynamicModificationController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [DynamicModificationController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/{uuid}', [DynamicModificationController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::get('/{uuid}/stage', [DynamicModificationController::class, 'currentStage'])->name('stage')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/{uuid}/evaluate', [DynamicModificationController::class, 'evaluate'])->name('evaluate')->middleware('check.permission:manufacturing.quality.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | QM: 8D CAPA (Eight-Discipline Problem Solving)
    |--------------------------------------------------------------------------
    */
    Route::prefix('capa-8d')->name('qm.capa8d.')->group(function (): void {
        Route::get('/', [Capa8DController::class, 'index'])->name('index')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/', [Capa8DController::class, 'store'])->name('store')->middleware('check.permission:manufacturing.quality.manage');
        Route::get('/{uuid}', [Capa8DController::class, 'show'])->name('show')->middleware('check.permission:manufacturing.quality.view');
        Route::post('/{uuid}/steps/{step}', [Capa8DController::class, 'updateStep'])->name('steps.update')->middleware('check.permission:manufacturing.quality.manage');
        Route::post('/{uuid}/close', [Capa8DController::class, 'close'])->name('close')->middleware('check.permission:manufacturing.quality.manage');
    });
});
