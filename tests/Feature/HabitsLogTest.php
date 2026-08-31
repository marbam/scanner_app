<?php

use App\Livewire\Habits\Log;
use App\Models\HabitActivity;
use App\Models\HabitEntry;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('shows active activities and lets today\'s date be toggled complete', function () {
    $reading = HabitActivity::factory()->create(['name' => 'Reading']);
    HabitActivity::factory()->archived()->create(['name' => 'Retired habit']);

    Livewire::test(Log::class)
        ->assertSee('Reading')
        ->assertDontSee('Retired habit')
        ->call('toggle', $reading->id);

    expect(HabitEntry::where('habit_activity_id', $reading->id)->first()->completed)->toBeTrue();
});

test('toggling twice clears the entry back to not completed', function () {
    $reading = HabitActivity::factory()->create();

    Livewire::test(Log::class)
        ->call('toggle', $reading->id)
        ->call('toggle', $reading->id);

    expect(HabitEntry::where('habit_activity_id', $reading->id)->first()->completed)->toBeFalse();
});

test('previous and next day navigation shifts the browsed date', function () {
    Livewire::test(Log::class, ['date' => '2026-08-31'])
        ->call('nextDay')
        ->assertSet('date', '2026-09-01')
        ->call('previousDay')
        ->call('previousDay')
        ->assertSet('date', '2026-08-30');
});
