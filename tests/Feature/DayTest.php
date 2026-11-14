<?php

use App\Livewire\Day\Index as Day;
use App\Models\HabitActivity;
use App\Models\HabitEntry;
use App\Models\User;
use App\Models\WatchEntry;
use App\Models\WatchTitle;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    config(['services.tmdb.key' => 'test-key']);
    $this->travelTo(now()->setDate(2026, 11, 7)->setTime(12, 0));
});

test('the page loads and starts on habits', function () {
    HabitActivity::factory()->create(['name' => 'Reading']);

    $this->get(route('day'))->assertOk()->assertSee('Your day')->assertSee('Reading');

    Livewire::test(Day::class)->assertSet('step', 'habits');
});

test('walks through habits, TV and films, then summarises the lot', function () {
    $reading = HabitActivity::factory()->create(['name' => 'Reading']);
    HabitActivity::factory()->create(['name' => 'Gym']);
    $show = WatchTitle::factory()->tv()->create(['title' => 'Slow Horses']);
    WatchEntry::factory()->for($show, 'title')->create(['watched_on' => '2026-11-05', 'location' => null]);
    $film = WatchTitle::factory()->create(['title' => 'Heat']);

    $component = Livewire::test(Day::class)
        ->call('toggleHabit', $reading->id)
        ->assertSee('1/2')
        ->call('goTo', 'tv')
        ->assertSee('Recently watched')
        ->call('toggleShow', $show->id)
        ->call('goTo', 'films')
        ->call('chooseTitle', $film->id)
        ->set('location', WatchEntry::CINEMA)
        ->call('add')
        ->call('goTo', 'done');

    $component->assertSee('Reading')->assertDontSee('Gym')->assertSee('Slow Horses')->assertSee('Heat');

    expect(HabitEntry::where('habit_activity_id', $reading->id)->whereDate('date', '2026-11-07')->sole()->completed)->toBeTrue()
        ->and(WatchEntry::whereDate('watched_on', '2026-11-07')->count())->toBe(2);
});

test('archived habits cannot be toggled', function () {
    $retired = HabitActivity::factory()->archived()->create();

    Livewire::test(Day::class)->call('toggleHabit', $retired->id);

    expect(HabitEntry::count())->toBe(0);
});

test('the date is always today and unknown steps fall back to habits', function () {
    $reading = HabitActivity::factory()->create();

    Livewire::withQueryParams(['step' => 'nonsense'])
        ->test(Day::class)
        ->assertSet('step', 'habits')
        ->set('date', '2026-01-01')
        ->assertSet('date', '2026-11-07')
        ->call('toggleHabit', $reading->id);

    expect(HabitEntry::sole()->date->toDateString())->toBe('2026-11-07');
});

test('the search box comes before the recently watched list', function () {
    $show = WatchTitle::factory()->tv()->create(['title' => 'Slow Horses']);
    WatchEntry::factory()->for($show, 'title')->create(['watched_on' => '2026-11-05', 'location' => null]);

    Livewire::test(Day::class)
        ->call('goTo', 'tv')
        ->assertSeeInOrder(['Search for a show', 'Recently watched', 'Slow Horses']);
});
