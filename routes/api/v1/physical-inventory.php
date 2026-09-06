<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Inventory\PhysicalInventoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('physical-inventory', PhysicalInventoryController::class)
    ->names('inventory.physical-inventory')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:inventory.physical-inventory.manage');

Route::post('physical-inventory/{physicalInventoryDocument}/counts', [PhysicalInventoryController::class, 'enterCounts'])
    ->name('inventory.physical-inventory.enter-counts')->middleware('check.permission:inventory.physical-inventory.manage');

Route::post('physical-inventory/{physicalInventoryDocument}/post', [PhysicalInventoryController::class, 'postAdjustments'])
    ->name('inventory.physical-inventory.post-adjustments')->middleware('check.permission:inventory.physical-inventory.manage');
