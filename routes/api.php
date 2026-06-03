<?php

use App\Http\Controllers\Webhook\BriWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/bri/payment', [BriWebhookController::class, 'payment']);
