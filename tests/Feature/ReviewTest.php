<?php

use App\Livewire\Review\Index as Review;
use App\Models\Book;
use App\Models\BookEntry;
use App\Models\HabitActivity;
use App\Models\HabitEntry;
use App\Models\SteamGame;
use App\Models\SteamGamePlay;
use App\Models\User;
use App\Models\WatchEntry;
use App\Models\WatchTitle;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    // 15 March 2026: the year to date is 74 days, the last 365 days start 16 March 2025.
    $this->travelTo(now()->setDate(2026, 3, 15)->setTime(12, 0));
});

test('guests are redirected and the page loads on the year to date', function () {
    $this->get(route('review'))->assertOk()->assertSee('Year in review');

    auth()->logout();
    $this->get(route('review'))->assertRedirect(route('login'));

    Livewire::test(Review::class)
        ->assertSet('period', 'ytd')
        ->assertSet('range', ['start' => '2026-01-01', 'end' => '2026-03-15', 'days' => 74]);
});

test('the period can be switched to the last 365 days, and bad values fall back', function () {
    Livewire::test(Review::class)
        ->call('setPeriod', '365')
        ->assertSet('range', ['start' => '2025-03-16', 'end' => '2026-03-15', 'days' => 365])
        ->call('setPeriod', 'nonsense')
        ->assertSet('period', '365');

    Livewire::withQueryParams(['period' => 'nonsense'])->test(Review::class)->assertSet('period', 'ytd');
});

test('TV and films count unique titles in the period', function () {
    $show = WatchTitle::factory()->tv()->create(['title' => 'Slow Horses']);
    $film = WatchTitle::factory()->create(['title' => 'Heat']);
    $oldFilm = WatchTitle::factory()->create(['title' => 'Old Film']);

    WatchEntry::factory()->for($show, 'title')->create(['watched_on' => '2026-02-01', 'location' => null]);
    WatchEntry::factory()->for($show, 'title')->create(['watched_on' => '2026-02-02', 'location' => null]);
    WatchEntry::factory()->for($film, 'title')->create(['watched_on' => '2026-02-01', 'location' => WatchEntry::CINEMA]);
    WatchEntry::factory()->for($film, 'title')->create(['watched_on' => '2026-02-03']);
    WatchEntry::factory()->for($oldFilm, 'title')->create(['watched_on' => '2025-12-20']);

    $ytd = Livewire::test(Review::class)->instance()->watching;

    expect($ytd['shows']->pluck('title')->all())->toBe(['Slow Horses'])
        ->and($ytd['films']->pluck('title')->all())->toBe(['Heat'])
        ->and($ytd['tv_days'])->toBe(2)
        ->and($ytd['cinema'])->toBe(1)
        // First entry ever was 20 Dec 2025, so all 74 days of the year are covered.
        ->and($ytd['coverage'])->toBe(['days' => 74, 'from' => '2026-01-01']);

    $year = Livewire::test(Review::class)->call('setPeriod', '365')->instance()->watching;

    expect($year['films']->pluck('title')->all())->toBe(['Heat', 'Old Film'])
        // 20 Dec 2025 to 15 Mar 2026.
        ->and($year['coverage'])->toBe(['days' => 86, 'from' => '2025-12-20']);
});

test('games total the playtime and average it over the days with data', function () {
    $hades = SteamGame::factory()->create(['name' => 'Hades']);
    $celeste = SteamGame::factory()->create(['name' => 'Celeste']);

    // Play log starts 6 March, so 10 days of data to 15 March.
    SteamGamePlay::create(['steam_game_id' => $hades->id, 'played_on' => '2026-03-06', 'minutes' => 120]);
    SteamGamePlay::create(['steam_game_id' => $hades->id, 'played_on' => '2026-03-10', 'minutes' => 60]);
    SteamGamePlay::create(['steam_game_id' => $celeste->id, 'played_on' => '2026-03-12', 'minutes' => 20]);

    $component = Livewire::test(Review::class);
    $games = $component->instance()->games;

    expect($games['coverage'])->toBe(['days' => 10, 'from' => '2026-03-06'])
        ->and($games['minutes'])->toBe(200)
        ->and($games['per_day'])->toBe(20.0)
        ->and($games['games'])->toBe([
            ['name' => 'Hades', 'minutes' => 180, 'days' => 2, 'per_day' => 90.0],
            ['name' => 'Celeste', 'minutes' => 20, 'days' => 1, 'per_day' => 20.0],
        ]);

    $component->assertSee('3h 20m')
        ->assertSee('Data for 10 of 74 days')
        ->assertSee('(since 6 Mar 2026)')
        ->assertSeeInOrder(['Hades', '3h', '1h 30m', 'Celeste', '20m']);
});

test('books list everything read in the period and what was finished', function () {
    $finished = Book::factory()->create(['title' => 'Piranesi', 'finished_on' => '2026-02-10']);
    $reading = Book::factory()->create(['title' => 'Middlemarch']);
    $lastYear = Book::factory()->create(['title' => 'Last Year', 'finished_on' => '2025-11-01']);

    BookEntry::factory()->for($finished)->create(['read_on' => '2026-02-09']);
    BookEntry::factory()->for($finished)->create(['read_on' => '2026-02-10']);
    BookEntry::factory()->for($reading)->create(['read_on' => '2026-03-01']);
    BookEntry::factory()->for($lastYear)->create(['read_on' => '2025-11-01']);

    $books = Livewire::test(Review::class)->instance()->books;

    expect($books['books']->pluck('title')->all())->toBe(['Middlemarch', 'Piranesi'])
        ->and($books['finished']->pluck('title')->all())->toBe(['Piranesi'])
        ->and($books['days'])->toBe(3);
});

test('habits show the days each was done, including archived ones done in the period', function () {
    $gym = HabitActivity::factory()->create(['name' => 'Gym']);
    $unused = HabitActivity::factory()->create(['name' => 'Meditate']);
    $retired = HabitActivity::factory()->archived()->create(['name' => 'Old habit']);
    $retiredUnused = HabitActivity::factory()->archived()->create(['name' => 'Long gone']);

    HabitEntry::factory()->for($gym, 'activity')->create(['date' => '2026-03-14']);
    HabitEntry::factory()->for($gym, 'activity')->create(['date' => '2026-03-15']);
    HabitEntry::factory()->for($unused, 'activity')->create(['date' => '2026-03-15', 'completed' => false]);
    HabitEntry::factory()->for($retired, 'activity')->create(['date' => '2026-03-13']);
    HabitEntry::factory()->for($retiredUnused, 'activity')->create(['date' => '2025-01-01']);

    $habits = Livewire::test(Review::class)->instance()->habits;
    $days = collect($habits['habits'])->mapWithKeys(fn (array $h) => [$h['activity']->name => $h['days']])->all();

    expect($days)->toBe(['Gym' => 2, 'Meditate' => 0, 'Old habit' => 1])
        ->and($habits['logged_days'])->toBe(3)
        // First habit entry ever was 1 Jan 2025, so the whole year to date is covered.
        ->and($habits['coverage'])->toBe(['days' => 74, 'from' => '2026-01-01']);
});

test('sections with no data say so', function () {
    Livewire::test(Review::class)->assertSee('No data in this period yet.');
});
