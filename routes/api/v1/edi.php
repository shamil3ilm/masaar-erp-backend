<?php

use App\Http\Controllers\Api\V1\Core\EdiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EDI / IDoc Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/edi
|
*/

Route::prefix('edi')->name('core.edi.')->group(function (): void {
    // Partners
    Route::get('/partners', [EdiController::class, 'indexPartners'])->name('partners.index');
    Route::post('/partners', [EdiController::class, 'storePartner'])->name('partners.store')->middleware('check.permission:core.edi.manage');
    Route::get('/partners/{id}', [EdiController::class, 'showPartner'])->name('partners.show');
    Route::put('/partners/{id}', [EdiController::class, 'updatePartner'])->name('partners.update')->middleware('check.permission:core.edi.manage');
    Route::delete('/partners/{id}', [EdiController::class, 'destroyPartner'])->name('partners.destroy')->middleware('check.permission:core.edi.manage');
    Route::get('/partners/{partnerId}/history', [EdiController::class, 'history'])->name('history');

    // Messages
    Route::get('/messages', [EdiController::class, 'indexMessages'])->name('messages.index');
    Route::get('/messages/{id}', [EdiController::class, 'showMessage'])->name('messages.show');
    Route::post('/messages/receive', [EdiController::class, 'receive'])->name('messages.receive')->middleware('check.permission:core.edi.manage');
    Route::post('/messages/send', [EdiController::class, 'send'])->name('messages.send')->middleware('check.permission:core.edi.manage');
    Route::post('/messages/{id}/process', [EdiController::class, 'process'])->name('messages.process')->middleware('check.permission:core.edi.manage');
    Route::post('/messages/{id}/reprocess', [EdiController::class, 'reprocess'])->name('messages.reprocess')->middleware('check.permission:core.edi.manage');
});
