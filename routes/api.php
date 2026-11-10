<?php

use App\Http\Controllers\Api\NumartOrderApiController;
use App\Http\Controllers\Webhook\BriWebhookController;
use App\Http\Controllers\Webhook\NumartErpWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/bri/payment', [BriWebhookController::class, 'payment']);

// NUMART ERP (D-31): pesanan.dipenuhi, pesanan.dibatalkan, pengiriman.diperbarui — HMAC + X-Event-Id
Route::post('/webhooks/numart-erp', NumartErpWebhookController::class)->name('webhooks.numart-erp');

Route::post('/numart/orders/{order}/confirm-payment', [NumartOrderApiController::class, 'confirmPayment'])
    ->middleware('numart.api')
    ->whereNumber('order');

Route::post('/numart/orders/{order}/accrue-affiliate', [NumartOrderApiController::class, 'accrueAffiliate'])
    ->middleware('numart.api')
    ->whereNumber('order');

Route::post('/numart/orders/{order}/tracking', [NumartOrderApiController::class, 'updateTracking'])
    ->middleware('numart.api')
    ->whereNumber('order');
