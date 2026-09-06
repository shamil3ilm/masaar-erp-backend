<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\ClassificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Classification System Routes (Platform)
|--------------------------------------------------------------------------
*/

Route::prefix('classification')->name('core.classification.')->group(function () {
    Route::get('/classes', [ClassificationController::class, 'indexClasses'])->name('classes.index')->middleware('check.permission:core.classification.view');
    Route::post('/classes', [ClassificationController::class, 'storeClass'])->name('classes.store')->middleware('check.permission:core.classification.manage');
    Route::get('/classes/{id}', [ClassificationController::class, 'showClass'])->name('classes.show')->middleware('check.permission:core.classification.view');
    Route::put('/classes/{id}', [ClassificationController::class, 'updateClass'])->name('classes.update')->middleware('check.permission:core.classification.manage');
    Route::delete('/classes/{id}', [ClassificationController::class, 'destroyClass'])->name('classes.destroy')->middleware('check.permission:core.classification.manage');
    Route::post('/classes/{classId}/characteristics', [ClassificationController::class, 'addCharacteristic'])->name('chars.add')->middleware('check.permission:core.classification.manage');
    Route::put('/classes/{classId}/characteristics/{charId}', [ClassificationController::class, 'updateCharacteristic'])->name('chars.update')->middleware('check.permission:core.classification.manage');
    Route::post('/assign', [ClassificationController::class, 'assignToObject'])->name('assign')->middleware('check.permission:core.classification.manage');
    Route::post('/values', [ClassificationController::class, 'setValues'])->name('values.set')->middleware('check.permission:core.classification.manage');
    Route::get('/for-object', [ClassificationController::class, 'getForObject'])->name('for-object')->middleware('check.permission:core.classification.view');
    Route::post('/search', [ClassificationController::class, 'search'])->name('search')->middleware('check.permission:core.classification.manage');
});
