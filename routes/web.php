<?php

use App\Http\Controllers\PushoverTestController;
use App\Livewire\Interests\Index as InterestsIndex;
use App\Livewire\PlanningApplications\Index as PlanningApplicationsIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('scans', InterestsIndex::class)->name('scans.index');

    Route::livewire('bristol-adverts', PlanningApplicationsIndex::class)->name('planning-applications.index');

    Route::get('pushover-test', PushoverTestController::class)
        ->middleware('throttle:6,1')
        ->name('pushover.test');
});

require __DIR__.'/settings.php';
