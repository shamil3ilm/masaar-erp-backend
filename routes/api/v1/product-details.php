<?php

use App\Http\Controllers\Api\V1\Inventory\ProductDetailController;
use Illuminate\Support\Facades\Route;

Route::prefix('products/{product}')->group(function () {
    Route::get('/details', [ProductDetailController::class, 'show']);

    // Specifications
    Route::get('/specifications', [ProductDetailController::class, 'specifications']);
    Route::put('/specifications', [ProductDetailController::class, 'syncSpecifications'])->middleware('check.permission:inventory.products.edit');

    // Images
    Route::get('/images', [ProductDetailController::class, 'images']);
    Route::post('/images', [ProductDetailController::class, 'addImage'])->middleware('check.permission:inventory.products.edit');
    Route::delete('/images/{image}', [ProductDetailController::class, 'removeImage'])->middleware('check.permission:inventory.products.edit');
    Route::put('/images/reorder', [ProductDetailController::class, 'reorderImages'])->middleware('check.permission:inventory.products.edit');

    // Documents
    Route::get('/documents', [ProductDetailController::class, 'documents']);
    Route::post('/documents', [ProductDetailController::class, 'addDocument'])->middleware('check.permission:inventory.products.edit');
    Route::delete('/documents/{document}', [ProductDetailController::class, 'removeDocument'])->middleware('check.permission:inventory.products.edit');

    // Videos
    Route::get('/videos', [ProductDetailController::class, 'videos']);
    Route::post('/videos', [ProductDetailController::class, 'addVideo'])->middleware('check.permission:inventory.products.edit');
    Route::delete('/videos/{video}', [ProductDetailController::class, 'removeVideo'])->middleware('check.permission:inventory.products.edit');

    // Relations
    Route::get('/relations', [ProductDetailController::class, 'relations']);
    Route::put('/relations', [ProductDetailController::class, 'setRelations'])->middleware('check.permission:inventory.products.edit');

    // Reviews
    Route::get('/reviews', [ProductDetailController::class, 'reviews']);
    Route::post('/reviews', [ProductDetailController::class, 'submitReview'])->middleware('check.permission:inventory.products.edit');
    Route::post('/reviews/{review}/approve', [ProductDetailController::class, 'approveReview'])->middleware('check.permission:inventory.products.edit');
    Route::post('/reviews/{review}/reject', [ProductDetailController::class, 'rejectReview'])->middleware('check.permission:inventory.products.edit');

    // Price History
    Route::get('/price-history', [ProductDetailController::class, 'priceHistory']);

    // Certifications
    Route::get('/certifications', [ProductDetailController::class, 'certifications']);
    Route::post('/certifications', [ProductDetailController::class, 'addCertification'])->middleware('check.permission:inventory.products.edit');
    Route::put('/certifications/{certification}', [ProductDetailController::class, 'updateCertification'])->middleware('check.permission:inventory.products.edit');
});
