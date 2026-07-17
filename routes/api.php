<?php

use App\Http\Controllers\Api\BiteshipWebhookController;
use App\Http\Controllers\Api\MidtransWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/midtrans', MidtransWebhookController::class)
    ->middleware('throttle:20,1')
    ->name('webhook.midtrans');

Route::post('/webhook/biteship', BiteshipWebhookController::class)
    ->middleware('throttle:20,1')
    ->name('webhook.biteship');
