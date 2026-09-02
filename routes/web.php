<?php

use App\Http\Controllers\PushoverTestController;
use App\Livewire\Facebook\Memories\Index as FacebookMemoriesIndex;
use App\Livewire\Facebook\Posts\Index as FacebookPostsIndex;
use App\Livewire\Habits\Activities as HabitsActivities;
use App\Livewire\Habits\Log as HabitsLog;
use App\Livewire\Habits\Summary as HabitsSummary;
use App\Livewire\Habits\Tracker as HabitsTracker;
use App\Livewire\Interests\Index as InterestsIndex;
use App\Livewire\PlanningApplications\Index as PlanningApplicationsIndex;
use App\Livewire\Squares\Board as SquaresBoard;
use App\Livewire\Twitter\Memories\Index as TwitterMemoriesIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('scans', InterestsIndex::class)->name('scans.index');

    Route::livewire('bristol-adverts', PlanningApplicationsIndex::class)->name('planning-applications.index');

    Route::livewire('facebook/posts', FacebookPostsIndex::class)->name('facebook.posts.index');

    Route::livewire('facebook/memories', FacebookMemoriesIndex::class)->name('facebook.memories.index');

    Route::livewire('twitter/memories', TwitterMemoriesIndex::class)->name('twitter.memories.index');

    Route::livewire('habits/log', HabitsLog::class)->name('habits.log');

    Route::livewire('habits/tracker', HabitsTracker::class)->name('habits.tracker');

    Route::livewire('habits/summary', HabitsSummary::class)->name('habits.summary');

    Route::livewire('habits/activities', HabitsActivities::class)->name('habits.activities');

    Route::livewire('squares', SquaresBoard::class)->name('squares');

    Route::get('pushover-test', PushoverTestController::class)
        ->middleware('throttle:6,1')
        ->name('pushover.test');
});

require __DIR__.'/settings.php';
