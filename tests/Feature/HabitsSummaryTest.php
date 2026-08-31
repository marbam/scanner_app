<?php

use App\Livewire\Habits\Summary;
use App\Models\HabitActivity;
use App\Models\HabitEntry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('computes today\'s weighted score from completed entries', function () {
    $reading = HabitActivity::factory()->create(['weight' => 1]);
    $running = HabitActivity::factory()->create(['weight' => 2]);

    HabitEntry::factory()->for($reading, 'activity')->create(['date' => today(), 'completed' => true]);
    HabitEntry::factory()->for($running, 'activity')->create(['date' => today(), 'completed' => true]);

    $points = Livewire::test(Summary::class)->instance()->points();

    expect(collect($points)->last()['score'])->toBe(3.0);
});

test('changing the range recomputes the points', function () {
    Livewire::test(Summary::class)
        ->call('setRange', 30)
        ->assertSet('rangeDays', 30);
});
