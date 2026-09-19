<?php

use App\Livewire\Reminders\Index;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Carbon;
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

test('today only forces the days to today and marks it as fires once', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-21 07:00:00', 'Europe/London')); // a Monday

    Livewire::test(Index::class)
        ->set('name', 'Take the bins out')
        ->set('time', '08:00')
        ->set('todayOnly', true)
        ->call('addReminder')
        ->assertHasNoErrors();

    $reminder = Reminder::where('name', 'Take the bins out')->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->days_of_week)->toBe([1])
        ->and($reminder->fires_once)->toBeTrue();

    Carbon::setTestNow();
});

test('today only rejects a time that has already passed', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-21 09:00:00', 'Europe/London'));

    Livewire::test(Index::class)
        ->set('name', 'Take the bins out')
        ->set('time', '08:00')
        ->set('todayOnly', true)
        ->call('addReminder')
        ->assertHasErrors('time');

    Carbon::setTestNow();
});

test('only once marks a regular reminder as fires once', function () {
    Livewire::test(Index::class)
        ->set('name', 'Call the dentist')
        ->set('time', '09:00')
        ->set('days', [1])
        ->set('firesOnce', true)
        ->call('addReminder')
        ->assertHasNoErrors();

    $reminder = Reminder::where('name', 'Call the dentist')->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->fires_once)->toBeTrue();
});

test('updating a reminder can toggle fires once', function () {
    $reminder = Reminder::factory()->create(['fires_once' => false]);

    Livewire::test(Index::class)
        ->set("edits.{$reminder->id}.fires_once", true)
        ->call('updateReminder', $reminder->id)
        ->assertHasNoErrors();

    expect($reminder->fresh()->fires_once)->toBeTrue();
});
