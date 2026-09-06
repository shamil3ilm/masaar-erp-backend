<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Tax\TaxDeterminationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tax Determination Rules
|--------------------------------------------------------------------------
|
| SAP TAXINJ/TAXINN equivalent — configurable rules engine that determines
| which tax type and rate applies given a document type, country/region,
| tax category, and customer type.
|
*/

// The simulate endpoint must come before apiResource so it is not treated
// as a {taxDeterminationRule} parameter.
Route::post('tax-determination-rules/simulate', [TaxDeterminationController::class, 'simulate'])
    ->name('tax.determination-rules.simulate')->middleware('check.permission:tax.determination-rules.manage');

Route::apiResource('tax-determination-rules', TaxDeterminationController::class)
    ->names('tax.determination-rules')->middlewareFor(['store', 'update', 'destroy'], 'check.permission:tax.determination-rules.manage')->middlewareFor(['index', 'show'], 'check.permission:tax.determination-rules.view');
