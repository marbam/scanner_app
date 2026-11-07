<?php

use App\Livewire\Watching\Backdate;
use App\Livewire\Watching\Today;
use App\Models\User;
use App\Models\WatchEntry;
use App\Models\WatchTitle;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    config(['services.tmdb.key' => 'test-key']);
    $this->travelTo(now()->setDate(2026, 11, 7)->setTime(12, 0));
});

test('the pages load', function () {
    $this->get(route('watching.today'))->assertOk()->assertSee('What did you watch?');
    $this->get(route('watching.backdate'))->assertOk();
    $this->get(route('watching.history'))->assertOk();
});

test('searching TMDB and adding a new show logs the first episode for today', function () {
    Http::fake([
        'api.themoviedb.org/3/search/tv*' => Http::response(['results' => [
            ['id' => 1396, 'name' => 'Breaking Bad', 'first_air_date' => '2008-01-20', 'poster_path' => '/bb.jpg'],
        ]]),
        'api.themoviedb.org/3/tv/1396*' => Http::response([
            'id' => 1396, 'name' => 'Breaking Bad', 'first_air_date' => '2008-01-20', 'poster_path' => '/bb.jpg',
            'seasons' => [
                ['season_number' => 0, 'episode_count' => 9],
                ['season_number' => 1, 'episode_count' => 7],
                ['season_number' => 2, 'episode_count' => 13],
            ],
        ]),
    ]);

    Livewire::test(Today::class)
        ->set('search', 'breaking')
        ->assertSee('Breaking Bad')
        ->call('chooseTmdb', 1396)
        ->assertSet('season', 1)
        ->assertSet('episode', 1)
        ->call('add');

    $title = WatchTitle::sole();
    expect($title->media_type)->toBe(WatchTitle::TV)
        ->and($title->year)->toBe(2008)
        ->and($title->seasons)->toBe([1 => 7, 2 => 13]);

    $entry = WatchEntry::sole();
    expect($entry->watched_on->toDateString())->toBe('2026-11-07')
        ->and($entry->season)->toBe(1)
        ->and($entry->episode)->toBe(1)
        ->and($entry->location)->toBeNull();

    Http::assertSent(fn ($request) => $request['api_key'] === 'test-key');
});

test('a recently watched show is offered with the next episode, rolling into the next season', function () {
    $show = WatchTitle::factory()->tv([1 => 7, 2 => 13])->create(['title' => 'Breaking Bad']);
    WatchEntry::factory()->for($show, 'title')->create(['watched_on' => '2026-11-05', 'season' => 1, 'episode' => 7, 'location' => null]);

    $component = Livewire::test(Today::class)->assertSee('Next: S2 E1');

    $component->call('chooseTitle', $show->id, 2, 1)->call('add');

    expect(WatchEntry::latest('id')->first())
        ->season->toBe(2)
        ->episode->toBe(1);

    // After logging S2E1 the pick moves on to S2E2.
    $component->assertSee('Next: S2 E2');
});

test('films can be logged as a cinema trip', function () {
    $film = WatchTitle::factory()->create(['title' => 'Dune: Part Two']);

    Livewire::test(Today::class)
        ->call('goTo', 'films')
        ->call('chooseTitle', $film->id)
        ->set('location', WatchEntry::CINEMA)
        ->call('add')
        ->assertSee('Dune: Part Two');

    expect(WatchEntry::sole())
        ->location->toBe(WatchEntry::CINEMA)
        ->season->toBeNull();
});

test('a film started earlier in the week is offered again and shows up in history search first', function () {
    $film = WatchTitle::factory()->create(['title' => 'Oppenheimer', 'tmdb_id' => 872585]);
    WatchEntry::factory()->for($film, 'title')->create(['watched_on' => '2026-11-03']);

    Http::fake([
        'api.themoviedb.org/3/search/movie*' => Http::response(['results' => [
            ['id' => 872585, 'title' => 'Oppenheimer', 'release_date' => '2023-07-19'],
            ['id' => 999, 'title' => 'Oppenheimer: The Documentary', 'release_date' => '2024-01-01'],
        ]]),
    ]);

    $component = Livewire::test(Today::class)
        ->call('goTo', 'films')
        ->assertSee('Watched recently')
        ->assertSee('Oppenheimer')
        ->set('search', 'oppen');

    expect($component->get('historyResults')->pluck('id')->all())->toBe([$film->id])
        ->and(array_column($component->get('tmdbResults')['results'], 'tmdb_id'))->toBe([999]);
});

test('entries can be removed', function () {
    $entry = WatchEntry::factory()->create();

    Livewire::test(Today::class)->call('goTo', 'films')->call('remove', $entry->id);

    expect(WatchEntry::count())->toBe(0);
});

test('the today page ignores attempts to change its date', function () {
    $film = WatchTitle::factory()->create();

    Livewire::test(Today::class)
        ->call('goTo', 'films')
        ->set('date', '2026-01-01')
        ->assertSet('date', '2026-11-07')
        ->call('chooseTitle', $film->id)
        ->call('add');

    expect(WatchEntry::sole()->watched_on->toDateString())->toBe('2026-11-07');
});

test('backdating defaults to yesterday, logs against the picked date and refuses future dates', function () {
    $film = WatchTitle::factory()->create();

    Livewire::test(Backdate::class)
        ->assertSet('date', '2026-11-06')
        ->set('date', '2026-10-31')
        ->call('goTo', 'films')
        ->call('chooseTitle', $film->id)
        ->call('add')
        ->set('date', '2027-01-01')
        ->assertSet('date', '2026-11-07');

    expect(WatchEntry::sole()->watched_on->toDateString())->toBe('2026-10-31');
});

test('a TMDB failure shows a message instead of breaking the page', function () {
    Http::fake(['api.themoviedb.org/*' => Http::response([], 500)]);

    Livewire::test(Today::class)
        ->set('search', 'anything')
        ->assertSee('TMDB search isn\'t responding right now.');
});
