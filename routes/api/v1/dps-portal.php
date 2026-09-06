<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Core\CustomerPortalController;
use App\Http\Controllers\Api\V1\Compliance\DeniedPartyScreeningController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Denied Party Screening — requires standard JWT auth
// -------------------------------------------------------------------------
// Denied-party screening writes: lists, entries, imports and screening runs.
// deny.readonly.writes keeps a read-only role out of them; without it these
// sat behind auth:api alone, outside both the permission checks and the
// organization group that carries the guard.
Route::middleware(['auth:api', 'deny.readonly.writes'])->group(function (): void {
    Route::prefix('compliance/dps')->name('compliance.dps.')->group(function (): void {
        Route::get('/lists', [DeniedPartyScreeningController::class, 'lists'])->name('lists')->middleware('check.permission:compliance.dps.view');
        Route::post('/lists', [DeniedPartyScreeningController::class, 'storeList'])->name('lists.store')->middleware('check.permission:compliance.dps.manage');
        Route::get('/lists/{id}', [DeniedPartyScreeningController::class, 'showList'])->name('lists.show')->middleware('check.permission:compliance.dps.view');
        Route::put('/lists/{id}', [DeniedPartyScreeningController::class, 'updateList'])->name('lists.update')->middleware('check.permission:compliance.dps.manage');
        Route::get('/lists/{listId}/entries', [DeniedPartyScreeningController::class, 'listEntries'])->name('entries')->middleware('check.permission:compliance.dps.view');
        Route::post('/lists/{listId}/entries', [DeniedPartyScreeningController::class, 'storeEntry'])->name('entries.store')->middleware('check.permission:compliance.dps.manage');
        Route::post('/lists/{listId}/import', [DeniedPartyScreeningController::class, 'importEntries'])->name('entries.import')->middleware('check.permission:compliance.dps.manage');
        Route::post('/screen-contact', [DeniedPartyScreeningController::class, 'screenContact'])->name('screen-contact')->middleware('check.permission:compliance.dps.manage');
        Route::post('/screen-all', [DeniedPartyScreeningController::class, 'screenAll'])->name('screen-all')->middleware('check.permission:compliance.dps.manage');
        Route::get('/runs', [DeniedPartyScreeningController::class, 'runs'])->name('runs')->middleware('check.permission:compliance.dps.view');
        Route::get('/runs/{id}', [DeniedPartyScreeningController::class, 'showRun'])->name('runs.show')->middleware('check.permission:compliance.dps.view');
        Route::post('/runs/{id}/clear', [DeniedPartyScreeningController::class, 'clearRun'])->name('runs.clear')->middleware('check.permission:compliance.dps.manage');
        Route::get('/pending-reviews', [DeniedPartyScreeningController::class, 'pendingReviews'])->name('pending-reviews')->middleware('check.permission:compliance.dps.view');
        Route::get('/contacts/{contactId}/status', [DeniedPartyScreeningController::class, 'checkContact'])->name('contacts.status')->middleware('check.permission:compliance.dps.view');
    });
});

// -------------------------------------------------------------------------
// Customer Self-Service Portal
// Public endpoints (register / login / forgot-password / reset-password)
// do NOT require JWT — the portal uses its own session token mechanism.
// -------------------------------------------------------------------------
Route::prefix('portal')->name('portal.')->group(function (): void {
    // Public, and rate limited because they are.
    //
    // Unthrottled, login is a credential brute force, forgot-password tells an
    // attacker which addresses are customers, and its organization_id rule -
    // exists:organizations,id - answers the same question about organizations.
    // These four are the only portal endpoints reachable without a token, so
    // they are the only ones where the limit does the work.
    Route::middleware('throttle:5,1')->group(function (): void {
        Route::post('/register', [CustomerPortalController::class, 'register'])->name('register');
        Route::post('/login', [CustomerPortalController::class, 'login'])->name('login');
        Route::post('/reset-password', [CustomerPortalController::class, 'resetPassword'])->name('reset-password');
    });

    Route::post('/forgot-password', [CustomerPortalController::class, 'forgotPassword'])
        ->middleware('throttle:3,1')
        ->name('forgot-password');

    // Portal-token-authenticated (bearer token is the portal session token)
    Route::group([], function (): void {
        Route::post('/logout', [CustomerPortalController::class, 'logout'])->name('logout');
        Route::get('/profile', [CustomerPortalController::class, 'profile'])->name('profile');
        Route::get('/invoices', [CustomerPortalController::class, 'invoices'])->name('invoices');
        Route::get('/invoices/{id}', [CustomerPortalController::class, 'showInvoice'])->name('invoices.show');
        Route::get('/orders', [CustomerPortalController::class, 'orders'])->name('orders');
        Route::get('/quotations', [CustomerPortalController::class, 'quotations'])->name('quotations');
        Route::get('/statement', [CustomerPortalController::class, 'statement'])->name('statement');

        // --- Extended portal endpoints (Gap 4) ---
        Route::get('/dashboard', [CustomerPortalController::class, 'dashboard'])->name('dashboard');
        Route::get('/invoices-list', [CustomerPortalController::class, 'invoicesPaginated'])->name('invoices.list');
        Route::get('/invoices/{invoice}/detail', [CustomerPortalController::class, 'invoiceDetail'])->name('invoices.detail');
        Route::get('/orders-list', [CustomerPortalController::class, 'ordersPaginated'])->name('orders.list');
        Route::get('/orders/{salesOrder}/detail', [CustomerPortalController::class, 'orderDetail'])->name('orders.detail');
        Route::get('/quotations-list', [CustomerPortalController::class, 'quotationsPaginated'])->name('quotations.list');
        Route::post('/quotations/{quotation}/accept', [CustomerPortalController::class, 'acceptQuotation'])->name('quotations.accept');
        Route::post('/quotations/{quotation}/decline', [CustomerPortalController::class, 'declineQuotation'])->name('quotations.decline');
        Route::get('/payments', [CustomerPortalController::class, 'payments'])->name('payments');
        Route::get('/outstanding-balance', [CustomerPortalController::class, 'outstandingBalance'])->name('outstanding-balance');
    });
});
