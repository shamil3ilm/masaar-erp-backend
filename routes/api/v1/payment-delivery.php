<?php

use App\Http\Controllers\Api\V1\Sales\DeliveryModeController;
use App\Http\Controllers\Api\V1\Sales\PaymentModeController;
use App\Http\Controllers\Api\V1\Sales\ShipmentController;
use Illuminate\Support\Facades\Route;

// Payment Modes
Route::prefix('payment-modes')->group(function () {
    Route::get('/', [PaymentModeController::class, 'index'])->middleware('check.permission:sales.payment-modes.view');
    Route::post('/', [PaymentModeController::class, 'store'])->middleware('check.permission:sales.payment-modes.manage');
    Route::get('/{mode}', [PaymentModeController::class, 'show'])->middleware('check.permission:sales.payment-modes.view');
    Route::put('/{mode}', [PaymentModeController::class, 'update'])->middleware('check.permission:sales.payment-modes.manage');
    Route::delete('/{mode}', [PaymentModeController::class, 'destroy'])->middleware('check.permission:sales.payment-modes.manage');
});

// Delivery Modes
Route::prefix('delivery-modes')->group(function () {
    Route::get('/', [DeliveryModeController::class, 'index'])->middleware('check.permission:sales.delivery-modes.view');
    Route::post('/', [DeliveryModeController::class, 'store'])->middleware('check.permission:sales.delivery-modes.manage');
    Route::get('/{mode}', [DeliveryModeController::class, 'show'])->middleware('check.permission:sales.delivery-modes.view');
    Route::put('/{mode}', [DeliveryModeController::class, 'update'])->middleware('check.permission:sales.delivery-modes.manage');
    Route::delete('/{mode}', [DeliveryModeController::class, 'destroy'])->middleware('check.permission:sales.delivery-modes.manage');
    Route::post('/calculate-shipping', [DeliveryModeController::class, 'calculateShipping'])->middleware('check.permission:sales.delivery-modes.manage');
});

// Shipments
Route::apiResource('shipments', ShipmentController::class)
    ->middlewareFor(['store'], 'check.permission:sales.shipments.create')
    ->middlewareFor(['update'], 'check.permission:sales.shipments.update')
    ->middlewareFor(['destroy'], 'check.permission:sales.shipments.delete')->middlewareFor(['index', 'show'], 'check.permission:sales.shipments.view');
Route::post('shipments/{shipment}/status', [ShipmentController::class, 'updateStatus'])->middleware('check.permission:sales.shipments.update');
Route::get('shipments/{shipment}/tracking', [ShipmentController::class, 'tracking'])->middleware('check.permission:sales.shipments.view');
