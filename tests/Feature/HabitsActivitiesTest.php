<?php

use App\Livewire\Habits\Activities;
use App\Models\HabitActivity;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('adds a new activity', function () {
    Livewire::test(Activities::class)
        ->set('name', 'Meditation')
        ->set('color', '#a855f7')
        ->set('weight', 1.5)
        ->call('addActivity')
        ->assertHasNoErrors();

    expect(HabitActivity::where('name', 'Meditation')->exists())->toBeTrue();
});

test('rejects a duplicate activity name', function () {
    HabitActivity::factory()->create(['name' => 'Reading']);

    Livewire::test(Activities::class)
        ->set('name', 'Reading')
        ->set('color', '#22c55e')
        ->set('weight', 1)
        ->call('addActivity')
        ->assertHasErrors('name');
});

test('archiving and unarchiving toggles the archived_at timestamp', function () {
    $activity = HabitActivity::factory()->create();

    Livewire::test(Activities::class)
        ->call('toggleArchived', $activity->id);

    expect($activity->fresh()->archived_at)->not->toBeNull();

    Livewire::test(Activities::class)
        ->call('toggleArchived', $activity->id);

    expect($activity->fresh()->archived_at)->toBeNull();
});
