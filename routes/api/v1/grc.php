<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Compliance\AuditFindingController;
use App\Http\Controllers\Api\V1\Compliance\CcmController;
use App\Http\Controllers\Api\V1\Compliance\ControlLibraryController;
use App\Http\Controllers\Api\V1\Compliance\CsaController;
use App\Http\Controllers\Api\V1\Compliance\KriController;
use App\Http\Controllers\Api\V1\Compliance\RiskManagementController;
use App\Http\Controllers\Api\V1\Compliance\SodController;
use Illuminate\Support\Facades\Route;

// GRC — Audit Finding Lifecycle (SAP GRC-IA)
Route::prefix('audit')->group(function (): void {
    Route::get('engagements', [AuditFindingController::class, 'indexEngagements']);
    Route::post('engagements', [AuditFindingController::class, 'storeEngagement'])->middleware('check.permission:compliance.audit.manage');
    Route::get('findings/dashboard', [AuditFindingController::class, 'dashboard']);
    Route::get('findings', [AuditFindingController::class, 'index']);
    Route::post('findings', [AuditFindingController::class, 'store'])->middleware('check.permission:compliance.audit.manage');
    Route::get('findings/{uuid}', [AuditFindingController::class, 'show']);
    Route::post('findings/{uuid}/assign', [AuditFindingController::class, 'assign'])->middleware('check.permission:compliance.audit.manage');
    Route::post('findings/{uuid}/remediate', [AuditFindingController::class, 'submitRemediation'])->middleware('check.permission:compliance.audit.manage');
    Route::post('findings/{uuid}/verify', [AuditFindingController::class, 'verify'])->middleware('check.permission:compliance.audit.manage');
    Route::post('findings/{uuid}/close', [AuditFindingController::class, 'close'])->middleware('check.permission:compliance.audit.manage');
});

// GRC — Segregation of Duties (SAP GRC-AC)
Route::prefix('sod')->group(function (): void {
    Route::get('functions', [SodController::class, 'indexFunctions']);
    Route::post('functions', [SodController::class, 'storeFunction'])->middleware('check.permission:compliance.sod.manage');
    Route::get('conflicts', [SodController::class, 'indexConflicts']);
    Route::post('conflicts', [SodController::class, 'storeConflict'])->middleware('check.permission:compliance.sod.manage');
    Route::get('violations', [SodController::class, 'indexViolations']);
    Route::post('scan', [SodController::class, 'runScan'])->middleware('check.permission:compliance.sod.manage');
    Route::get('users/{userId}/review', [SodController::class, 'reviewUser']);
    Route::post('violations/{uuid}/accept-risk', [SodController::class, 'acceptRisk'])->middleware('check.permission:compliance.sod.manage');
});

// GRC — Control Self-Assessment (SAP GRC-PC)
Route::prefix('csa')->group(function (): void {
    Route::get('questionnaires', [CsaController::class, 'index']);
    Route::post('questionnaires', [CsaController::class, 'store'])->middleware('check.permission:compliance.csa.manage');
    Route::get('questionnaires/{uuid}', [CsaController::class, 'show']);
    Route::post('questionnaires/{uuid}/publish', [CsaController::class, 'publish'])->middleware('check.permission:compliance.csa.manage');
    Route::post('questionnaires/{uuid}/responses', [CsaController::class, 'respond'])->middleware('check.permission:compliance.csa.manage');
    Route::get('questionnaires/{uuid}/completion', [CsaController::class, 'completion']);
    Route::post('questionnaires/{uuid}/review', [CsaController::class, 'review'])->middleware('check.permission:compliance.csa.manage');
});

// GRC — Continuous Controls Monitoring (SAP GRC-PC CCM)
Route::prefix('ccm')->group(function (): void {
    Route::get('monitors', [CcmController::class, 'indexMonitors']);
    Route::post('monitors', [CcmController::class, 'storeMonitor'])->middleware('check.permission:compliance.ccm.manage');
    Route::post('monitors/{uuid}/run', [CcmController::class, 'runMonitor'])->middleware('check.permission:compliance.ccm.manage');
    Route::get('exceptions', [CcmController::class, 'indexExceptions']);
    Route::post('exceptions/{uuid}/resolve', [CcmController::class, 'resolveException'])->middleware('check.permission:compliance.ccm.manage');
    Route::get('dashboard', [CcmController::class, 'dashboard']);
});

// GRC — Risk Management (SAP GRC-RM)
Route::prefix('rm')->group(function (): void {
    Route::get('dashboard', [RiskManagementController::class, 'dashboard']);
    Route::get('heat-map', [RiskManagementController::class, 'heatMap']);

    Route::get('categories', [RiskManagementController::class, 'indexCategories']);
    Route::post('categories', [RiskManagementController::class, 'storeCategory'])->middleware('check.permission:compliance.risk.manage');

    Route::get('risks', [RiskManagementController::class, 'index']);
    Route::post('risks', [RiskManagementController::class, 'store'])->middleware('check.permission:compliance.risk.manage');
    Route::get('risks/{uuid}', [RiskManagementController::class, 'show']);
    Route::post('risks/{uuid}/assess', [RiskManagementController::class, 'assess'])->middleware('check.permission:compliance.risk.manage');
    Route::post('risks/{uuid}/treatments', [RiskManagementController::class, 'addTreatment'])->middleware('check.permission:compliance.risk.manage');
    Route::put('treatments/{treatmentUuid}', [RiskManagementController::class, 'updateTreatment'])->middleware('check.permission:compliance.risk.manage');

    Route::get('kris', [KriController::class, 'index']);
    Route::post('kris', [KriController::class, 'store'])->middleware('check.permission:compliance.risk.manage');
    Route::get('kris/{uuid}', [KriController::class, 'show']);
    Route::post('kris/{uuid}/readings', [KriController::class, 'recordReading'])->middleware('check.permission:compliance.risk.manage');
    Route::get('kris/{uuid}/readings', [KriController::class, 'readings']);
});

// GRC — Control Library (SAP GRC-PC)
Route::prefix('pc')->group(function (): void {
    Route::apiResource('controls', ControlLibraryController::class)->names('grc.pc.controls')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:compliance.process-control.manage');
});
