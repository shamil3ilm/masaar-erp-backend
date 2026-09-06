<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Sales\IntercompanySalesController;

Route::prefix('intercompany/sales-orders')->name('ic.sales-orders.')->group(function () {
    Route::get('/', [IntercompanySalesController::class, 'index'])->name('index')->middleware('check.permission:sales.intercompany-orders.view');
    Route::post('/', [IntercompanySalesController::class, 'store'])->name('store')->middleware('check.permission:sales.intercompany-orders.manage');
    Route::get('/{id}', [IntercompanySalesController::class, 'show'])->name('show')->middleware('check.permission:sales.intercompany-orders.view');
    Route::put('/{id}', [IntercompanySalesController::class, 'update'])->name('update')->middleware('check.permission:sales.intercompany-orders.manage');
    Route::post('/{id}/confirm', [IntercompanySalesController::class, 'confirm'])->name('confirm')->middleware('check.permission:sales.intercompany-orders.manage');
    Route::post('/{id}/link-purchase-order', [IntercompanySalesController::class, 'linkPurchaseOrder'])->name('link-po')->middleware('check.permission:sales.intercompany-orders.manage');
    Route::post('/{id}/start-delivery', [IntercompanySalesController::class, 'startDelivery'])->name('start-delivery')->middleware('check.permission:sales.intercompany-orders.manage');
    Route::post('/{id}/billing-documents', [IntercompanySalesController::class, 'createBillingDocument'])->name('billing.create')->middleware('check.permission:sales.intercompany-orders.manage');
    Route::post('/{id}/billing-documents/{billingDocId}/post', [IntercompanySalesController::class, 'postBillingDocument'])->name('billing.post')->middleware('check.permission:sales.intercompany-orders.manage');
    Route::post('/{id}/cancel', [IntercompanySalesController::class, 'cancel'])->name('cancel')->middleware('check.permission:sales.intercompany-orders.manage');
});
