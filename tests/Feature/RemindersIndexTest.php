<?php

use App\Livewire\Reminders\Index;
use App\Models\Reminder;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('adds a new reminder', function () {
    Livewire::test(Index::class)
        ->set('name', 'Drink water')
        ->set('description', 'Stay hydrated')
        ->set('time', '07:30')
        ->set('days', [1, 2, 3, 4, 5])
        ->call('addReminder')
        ->assertHasNoErrors();

    $reminder = Reminder::where('name', 'Drink water')->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->days_of_week)->toBe([1, 2, 3, 4, 5])
        ->and($reminder->is_active)->toBeTrue();
});

test('requires at least one day', function () {
    Livewire::test(Index::class)
        ->set('name', 'Drink water')
        ->set('time', '07:30')
        ->set('days', [])
        ->call('addReminder')
        ->assertHasErrors('days');
});

test('updates an existing reminder', function () {
    $reminder = Reminder::factory()->create(['name' => 'Stretch', 'days_of_week' => [1]]);

    $component = Livewire::test(Index::class);
    $component->set("edits.{$reminder->id}.name", 'Stretch and breathe');
    $component->set("edits.{$reminder->id}.days", [1, 3, 5]);
    $component->call('updateReminder', $reminder->id)->assertHasNoErrors();

    expect($reminder->fresh()->name)->toBe('Stretch and breathe')
        ->and($reminder->fresh()->days_of_week)->toBe([1, 3, 5]);
});

test('toggling active flips the flag', function () {
    $reminder = Reminder::factory()->create(['is_active' => true]);

    Livewire::test(Index::class)->call('toggleActive', $reminder->id);

    expect($reminder->fresh()->is_active)->toBeFalse();

    Livewire::test(Index::class)->call('toggleActive', $reminder->id);

    expect($reminder->fresh()->is_active)->toBeTrue();
});

test('deletes a reminder', function () {
    $reminder = Reminder::factory()->create();

    Livewire::test(Index::class)->call('deleteReminder', $reminder->id);

    expect(Reminder::find($reminder->id))->toBeNull();
});
