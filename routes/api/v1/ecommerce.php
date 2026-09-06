<?php

use App\Http\Controllers\Api\V1\Ecommerce\EcommerceChannelController;
use App\Http\Controllers\Api\V1\Ecommerce\EcommerceOrderController;
use App\Http\Controllers\Api\V1\Ecommerce\OnlinePaymentController;
use App\Http\Controllers\Api\V1\Ecommerce\PaymentGatewayController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| E-Commerce API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1/ecommerce
|
*/

Route::middleware(['auth:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | E-Commerce Channels
    |--------------------------------------------------------------------------
    */
    Route::prefix('channels')->group(function () {
        Route::get('/', [EcommerceChannelController::class, 'index'])->name('ecommerce.channels.index');
        Route::post('/', [EcommerceChannelController::class, 'store'])->name('ecommerce.channels.store')->middleware('check.permission:ecommerce.channels.manage');
        Route::get('/{ecommerceChannel}', [EcommerceChannelController::class, 'show'])->name('ecommerce.channels.show');
        Route::put('/{ecommerceChannel}', [EcommerceChannelController::class, 'update'])->name('ecommerce.channels.update')->middleware('check.permission:ecommerce.channels.manage');
        Route::delete('/{ecommerceChannel}', [EcommerceChannelController::class, 'destroy'])->name('ecommerce.channels.destroy')->middleware('check.permission:ecommerce.channels.manage');
        Route::post('/{ecommerceChannel}/sync', [EcommerceChannelController::class, 'sync'])->name('ecommerce.channels.sync')->middleware('check.permission:ecommerce.channels.manage');
        Route::post('/{ecommerceChannel}/connect', [EcommerceChannelController::class, 'connect'])->name('ecommerce.channels.connect')->middleware('check.permission:ecommerce.channels.manage');
        Route::post('/{ecommerceChannel}/disconnect', [EcommerceChannelController::class, 'disconnect'])->name('ecommerce.channels.disconnect')->middleware('check.permission:ecommerce.channels.manage');
        Route::get('/{ecommerceChannel}/stats', [EcommerceChannelController::class, 'stats'])->name('ecommerce.channels.stats');
    });

    /*
    |--------------------------------------------------------------------------
    | E-Commerce Orders
    |--------------------------------------------------------------------------
    */
    Route::prefix('orders')->group(function () {
        Route::get('/', [EcommerceOrderController::class, 'index'])->name('ecommerce.orders.index');
        Route::get('/stats', [EcommerceOrderController::class, 'stats'])->name('ecommerce.orders.stats');
        Route::post('/import', [EcommerceOrderController::class, 'import'])->name('ecommerce.orders.import')->middleware('check.permission:ecommerce.orders.manage');
        Route::get('/{ecommerceOrder}', [EcommerceOrderController::class, 'show'])->name('ecommerce.orders.show');
        Route::post('/{ecommerceOrder}/process', [EcommerceOrderController::class, 'process'])->name('ecommerce.orders.process')->middleware('check.permission:ecommerce.orders.manage');
        Route::post('/{ecommerceOrder}/fulfill', [EcommerceOrderController::class, 'fulfill'])->name('ecommerce.orders.fulfill')->middleware('check.permission:ecommerce.orders.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    */
    Route::prefix('payment-gateways')->group(function () {
        Route::get('/', [PaymentGatewayController::class, 'index'])->name('ecommerce.payment-gateways.index');
        Route::post('/', [PaymentGatewayController::class, 'store'])->name('ecommerce.payment-gateways.store')->middleware('check.permission:ecommerce.payment-gateways.manage');
        Route::get('/{paymentGateway}', [PaymentGatewayController::class, 'show'])->name('ecommerce.payment-gateways.show');
        Route::put('/{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('ecommerce.payment-gateways.update')->middleware('check.permission:ecommerce.payment-gateways.manage');
        Route::delete('/{paymentGateway}', [PaymentGatewayController::class, 'destroy'])->name('ecommerce.payment-gateways.destroy')->middleware('check.permission:ecommerce.payment-gateways.manage');
    });

    /*
    |--------------------------------------------------------------------------
    | Online Payments
    |--------------------------------------------------------------------------
    */
    Route::prefix('payments')->group(function () {
        Route::get('/', [OnlinePaymentController::class, 'index'])->name('ecommerce.payments.index');
        Route::get('/{onlinePayment}', [OnlinePaymentController::class, 'show'])->name('ecommerce.payments.show');
        Route::get('/{onlinePayment}/status', [OnlinePaymentController::class, 'status'])->name('ecommerce.payments.status');
        Route::post('/{onlinePayment}/callback', [OnlinePaymentController::class, 'callback'])->name('ecommerce.payments.callback')->middleware('check.permission:ecommerce.payments.manage');
        Route::post('/{onlinePayment}/refund', [OnlinePaymentController::class, 'refund'])->name('ecommerce.payments.refund')->middleware('check.permission:ecommerce.payments.manage');
    });
});
