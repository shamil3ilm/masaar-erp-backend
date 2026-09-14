<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Purchase\PurchaseRequisitionController;
use Illuminate\Support\Facades\Route;

// The controller binds the requisition as $purchaseRequisition, as the routes below do.
Route::apiResource('requisitions', PurchaseRequisitionController::class)
    ->parameters(['requisitions' => 'purchaseRequisition'])
    ->names('purchase.requisitions')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:purchase.requisitions.manage')->middlewareFor(['index', 'show'], 'check.permission:purchase.requisitions.view');

Route::post('requisitions/{purchaseRequisition}/submit', [PurchaseRequisitionController::class, 'submit'])
    ->name('purchase.requisitions.submit')->middleware('check.permission:purchase.requisitions.manage');

Route::post('requisitions/{purchaseRequisition}/approve', [PurchaseRequisitionController::class, 'approve'])
    ->name('purchase.requisitions.approve')->middleware('check.permission:purchase.requisitions.manage');

Route::post('requisitions/{purchaseRequisition}/convert-to-po', [PurchaseRequisitionController::class, 'convertToPO'])
    ->name('purchase.requisitions.convert-to-po')->middleware('check.permission:purchase.requisitions.manage');

Route::post('requisitions/{purchaseRequisition}/cancel', [PurchaseRequisitionController::class, 'cancel'])
    ->name('purchase.requisitions.cancel')->middleware('check.permission:purchase.requisitions.manage');
