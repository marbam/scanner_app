<?php

use App\Livewire\Habits\Tracker;
use App\Models\HabitActivity;
use App\Models\HabitEntry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('shows a grid per active activity', function () {
    HabitActivity::factory()->create(['name' => 'Reading']);
    HabitActivity::factory()->archived()->create(['name' => 'Retired habit']);

    Livewire::test(Tracker::class)
        ->assertSee('Reading')
        ->assertDontSee('Retired habit');
});

test('marks a completed day filled in its own activity\'s grid', function () {
    $reading = HabitActivity::factory()->create(['name' => 'Reading', 'color' => '#22c55e', 'weight' => 1]);
    $running = HabitActivity::factory()->create(['name' => 'Running', 'color' => '#3b82f6', 'weight' => 3]);

    HabitEntry::factory()->for($reading, 'activity')->create(['date' => today(), 'completed' => true]);

    $grids = Livewire::test(Tracker::class)->instance()->activityGrids();

    $readingCell = collect($grids[0]['weeks'])->flatten(1)->firstWhere('date', today()->toDateString());
    $runningCell = collect($grids[1]['weeks'])->flatten(1)->firstWhere('date', today()->toDateString());

    expect($readingCell['filled'])->toBeTrue();
    expect($runningCell['filled'])->toBeFalse();
});

test('the overall grid shades today by the combined weighted score', function () {
    $reading = HabitActivity::factory()->create(['weight' => 1]);
    $running = HabitActivity::factory()->create(['weight' => 3]);

    HabitEntry::factory()->for($reading, 'activity')->create(['date' => today(), 'completed' => true]);
    HabitEntry::factory()->for($running, 'activity')->create(['date' => today(), 'completed' => true]);

    $summaryGrid = Livewire::test(Tracker::class)->instance()->summaryGrid();
    $todayCell = collect($summaryGrid)->flatten(1)->firstWhere('date', today()->toDateString());

    expect($todayCell['score'])->toBe(4.0);
    expect($todayCell['intensity'])->toBe(4);
});

test('a day with no entries is not shaded in the overall grid', function () {
    HabitActivity::factory()->create();

    $summaryGrid = Livewire::test(Tracker::class)->instance()->summaryGrid();
    $emptyDayCell = collect($summaryGrid)->flatten(1)->first();

    expect($emptyDayCell['intensity'])->toBe(0);
    expect($emptyDayCell['score'])->toBe(0.0);
});
