<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\WorkflowEscalationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workflow Escalation & Substitution Routes (Platform)
|--------------------------------------------------------------------------
*/

Route::prefix('workflow-escalation')->name('core.workflow-escalation.')->group(function () {
    Route::get('/rules', [WorkflowEscalationController::class, 'indexRules'])->name('rules.index')->middleware('check.permission:core.workflow-escalation.view');
    Route::post('/rules', [WorkflowEscalationController::class, 'storeRule'])->name('rules.store')->middleware('check.permission:core.workflow-escalation.manage');
    Route::put('/rules/{id}', [WorkflowEscalationController::class, 'updateRule'])->name('rules.update')->middleware('check.permission:core.workflow-escalation.manage');
    Route::delete('/rules/{id}', [WorkflowEscalationController::class, 'destroyRule'])->name('rules.destroy')->middleware('check.permission:core.workflow-escalation.manage');
    Route::post('/check-and-escalate', [WorkflowEscalationController::class, 'checkAndEscalate'])->name('check')->middleware('check.permission:core.workflow-escalation.manage');
    Route::get('/substitutions', [WorkflowEscalationController::class, 'indexSubstitutions'])->name('substitutions.index')->middleware('check.permission:core.workflow-escalation.view');
    Route::post('/substitutions', [WorkflowEscalationController::class, 'createSubstitution'])->name('substitutions.store')->middleware('check.permission:core.workflow-escalation.manage');
    Route::delete('/substitutions/{id}', [WorkflowEscalationController::class, 'revokeSubstitution'])->name('substitutions.revoke')->middleware('check.permission:core.workflow-escalation.manage');
    Route::get('/substitute/{approverId}', [WorkflowEscalationController::class, 'getSubstitute'])->name('get-substitute')->middleware('check.permission:core.workflow-escalation.view');
});
