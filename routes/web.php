<?php

use App\Http\Controllers\PushoverTestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('pushover-test', PushoverTestController::class)
        ->middleware('throttle:6,1')
        ->name('pushover.test');
});

require __DIR__.'/settings.php';
