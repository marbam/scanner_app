<?php

use App\Http\Controllers\CinemaCheckWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('cinema-checks', CinemaCheckWebhookController::class)
    ->middleware(['cinema_webhook.token', 'throttle:20,1'])
    ->name('cinema-checks.store');
