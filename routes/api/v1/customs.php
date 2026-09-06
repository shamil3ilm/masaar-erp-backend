<?php

use App\Http\Controllers\Api\V1\Customs\CustomsDeclarationController;
use App\Http\Controllers\Api\V1\Customs\CustomsTariffController;
use App\Http\Controllers\Api\V1\Customs\ExciseCategoryController;
use App\Http\Controllers\Api\V1\Customs\ExciseDeclarationController;
use Illuminate\Support\Facades\Route;

// Customs Tariff Codes
Route::prefix('tariff-codes')->group(function () {
    Route::get('/', [CustomsTariffController::class, 'index']);
    Route::post('/', [CustomsTariffController::class, 'store'])->middleware('check.permission:customs.tariff-codes.create');
    Route::get('/lookup/{code}', [CustomsTariffController::class, 'lookup']);
    Route::get('/{tariffCode}', [CustomsTariffController::class, 'show']);
    Route::put('/{tariffCode}', [CustomsTariffController::class, 'update'])->middleware('check.permission:customs.tariff-codes.manage');
    Route::delete('/{tariffCode}', [CustomsTariffController::class, 'destroy'])->middleware('check.permission:customs.tariff-codes.manage');
});

// Customs Declarations
Route::prefix('declarations')->group(function () {
    Route::get('/', [CustomsDeclarationController::class, 'index']);
    Route::post('/', [CustomsDeclarationController::class, 'store'])->middleware('check.permission:customs.declarations.create');
    Route::get('/{declaration}', [CustomsDeclarationController::class, 'show']);
    Route::put('/{declaration}', [CustomsDeclarationController::class, 'update'])->middleware('check.permission:customs.declarations.manage');
    Route::delete('/{declaration}', [CustomsDeclarationController::class, 'destroy'])->middleware('check.permission:customs.declarations.manage');
    Route::post('/{declaration}/submit', [CustomsDeclarationController::class, 'submit'])->middleware('check.permission:customs.declarations.manage');
    Route::post('/{declaration}/assess', [CustomsDeclarationController::class, 'assess'])->middleware('check.permission:customs.declarations.manage');
    Route::post('/{declaration}/pay-duty', [CustomsDeclarationController::class, 'payDuty'])->middleware('check.permission:customs.declarations.manage');
    Route::post('/{declaration}/clear', [CustomsDeclarationController::class, 'clear'])->middleware('check.permission:customs.declarations.manage');
    Route::post('/{declaration}/reject', [CustomsDeclarationController::class, 'reject'])->middleware('check.permission:customs.declarations.manage');
});

// Excise Categories
Route::prefix('excise-categories')->group(function () {
    Route::get('/', [ExciseCategoryController::class, 'index']);
    Route::post('/', [ExciseCategoryController::class, 'store'])->middleware('check.permission:customs.excise-categories.create');
    Route::get('/{category}', [ExciseCategoryController::class, 'show']);
    Route::put('/{category}', [ExciseCategoryController::class, 'update'])->middleware('check.permission:customs.excise-categories.manage');
    Route::delete('/{category}', [ExciseCategoryController::class, 'destroy'])->middleware('check.permission:customs.excise-categories.manage');
    Route::post('/{category}/rates', [ExciseCategoryController::class, 'addRate'])->middleware('check.permission:customs.excise-categories.manage');
    Route::post('/{category}/map-product', [ExciseCategoryController::class, 'mapProduct'])->middleware('check.permission:customs.excise-categories.manage');
});

// Excise Declarations
Route::prefix('excise-declarations')->group(function () {
    Route::get('/', [ExciseDeclarationController::class, 'index']);
    Route::post('/', [ExciseDeclarationController::class, 'store'])->middleware('check.permission:customs.excise-declarations.manage');
    Route::get('/{declaration}', [ExciseDeclarationController::class, 'show']);
    Route::put('/{declaration}', [ExciseDeclarationController::class, 'update'])->middleware('check.permission:customs.excise-declarations.manage');
    Route::delete('/{declaration}', [ExciseDeclarationController::class, 'destroy'])->middleware('check.permission:customs.excise-declarations.manage');
    Route::post('/{declaration}/submit', [ExciseDeclarationController::class, 'submit'])->middleware('check.permission:customs.excise-declarations.manage');
    Route::post('/{declaration}/pay', [ExciseDeclarationController::class, 'pay'])->middleware('check.permission:customs.excise-declarations.manage');
});
