<?php

use App\Http\Controllers\Api\NumartOrderApiController;
use App\Http\Controllers\Webhook\BriWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/bri/payment', [BriWebhookController::class, 'payment']);

Route::post('/numart/orders/{order}/confirm-payment', [NumartOrderApiController::class, 'confirmPayment'])
    ->middleware('numart.api')
    ->whereNumber('order');

Route::post('/numart/orders/{order}/tracking', [NumartOrderApiController::class, 'updateTracking'])
    ->middleware('numart.api')
    ->whereNumber('order');
