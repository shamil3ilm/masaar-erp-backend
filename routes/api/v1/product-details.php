<?php

use App\Http\Controllers\Api\V1\Inventory\ProductDetailController;
use Illuminate\Support\Facades\Route;

Route::prefix('products/{product}')->group(function () {
    Route::get('/details', [ProductDetailController::class, 'show'])->middleware('check.permission:inventory.products.view');

    // Specifications
    Route::get('/specifications', [ProductDetailController::class, 'specifications'])->middleware('check.permission:inventory.products.view');
    Route::put('/specifications', [ProductDetailController::class, 'syncSpecifications'])->middleware('check.permission:inventory.products.edit');

    // Images
    Route::get('/images', [ProductDetailController::class, 'images'])->middleware('check.permission:inventory.products.view');
    Route::post('/images', [ProductDetailController::class, 'addImage'])->middleware('check.permission:inventory.products.edit');
    Route::delete('/images/{image}', [ProductDetailController::class, 'removeImage'])->middleware('check.permission:inventory.products.edit');
    Route::put('/images/reorder', [ProductDetailController::class, 'reorderImages'])->middleware('check.permission:inventory.products.edit');

    // Documents
    Route::get('/documents', [ProductDetailController::class, 'documents'])->middleware('check.permission:inventory.products.view');
    Route::post('/documents', [ProductDetailController::class, 'addDocument'])->middleware('check.permission:inventory.products.edit');
    Route::delete('/documents/{document}', [ProductDetailController::class, 'removeDocument'])->middleware('check.permission:inventory.products.edit');

    // Videos
    Route::get('/videos', [ProductDetailController::class, 'videos'])->middleware('check.permission:inventory.products.view');
    Route::post('/videos', [ProductDetailController::class, 'addVideo'])->middleware('check.permission:inventory.products.edit');
    Route::delete('/videos/{video}', [ProductDetailController::class, 'removeVideo'])->middleware('check.permission:inventory.products.edit');

    // Relations
    Route::get('/relations', [ProductDetailController::class, 'relations'])->middleware('check.permission:inventory.products.view');
    Route::put('/relations', [ProductDetailController::class, 'setRelations'])->middleware('check.permission:inventory.products.edit');

    // Reviews
    Route::get('/reviews', [ProductDetailController::class, 'reviews'])->middleware('check.permission:inventory.products.view');
    Route::post('/reviews', [ProductDetailController::class, 'submitReview'])->middleware('check.permission:inventory.products.edit');
    Route::post('/reviews/{review}/approve', [ProductDetailController::class, 'approveReview'])->middleware('check.permission:inventory.products.edit');
    Route::post('/reviews/{review}/reject', [ProductDetailController::class, 'rejectReview'])->middleware('check.permission:inventory.products.edit');

    // Price History
    Route::get('/price-history', [ProductDetailController::class, 'priceHistory'])->middleware('check.permission:inventory.products.view');

    // Certifications
    Route::get('/certifications', [ProductDetailController::class, 'certifications'])->middleware('check.permission:inventory.products.view');
    Route::post('/certifications', [ProductDetailController::class, 'addCertification'])->middleware('check.permission:inventory.products.edit');
    Route::put('/certifications/{certification}', [ProductDetailController::class, 'updateCertification'])->middleware('check.permission:inventory.products.edit');
});
