<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Campaign\CampaignController;
use App\Http\Controllers\Api\V1\Campaign\SegmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('segments')->group(function () {
    Route::get('/', [SegmentController::class, 'index'])->middleware('check.permission:crm.segments.view');
    Route::post('/', [SegmentController::class, 'store'])->middleware('check.permission:crm.segments.create');
    Route::get('/{id}', [SegmentController::class, 'show'])->middleware('check.permission:crm.segments.view');
    Route::put('/{id}', [SegmentController::class, 'update'])->middleware('check.permission:crm.segments.edit');
    Route::delete('/{id}', [SegmentController::class, 'destroy'])->middleware('check.permission:crm.segments.delete');
    Route::get('/{id}/members', [SegmentController::class, 'members'])->middleware('check.permission:crm.segments.view');
});

Route::prefix('campaigns')->group(function () {
    Route::get('/', [CampaignController::class, 'index'])->middleware('check.permission:crm.campaigns.view');
    Route::post('/', [CampaignController::class, 'store'])->middleware('check.permission:crm.campaigns.create');
    Route::get('/{id}', [CampaignController::class, 'show'])->middleware('check.permission:crm.campaigns.view');
    Route::put('/{id}', [CampaignController::class, 'update'])->middleware('check.permission:crm.campaigns.edit');
    Route::delete('/{id}', [CampaignController::class, 'destroy'])->middleware('check.permission:crm.campaigns.delete');
    Route::post('/{id}/activate', [CampaignController::class, 'activate'])->middleware('check.permission:crm.campaigns.edit');
    Route::post('/{id}/pause', [CampaignController::class, 'pause'])->middleware('check.permission:crm.campaigns.edit');
});
