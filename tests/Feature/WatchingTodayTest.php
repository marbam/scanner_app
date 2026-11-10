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

test('picking a new show from TMDB search logs it for today in one tap', function () {
    Http::fake([
        'api.themoviedb.org/3/search/tv*' => Http::response(['results' => [
            ['id' => 1396, 'name' => 'Breaking Bad', 'first_air_date' => '2008-01-20', 'poster_path' => '/bb.jpg'],
        ]]),
        'api.themoviedb.org/3/tv/1396*' => Http::response([
            'id' => 1396, 'name' => 'Breaking Bad', 'first_air_date' => '2008-01-20', 'poster_path' => '/bb.jpg',
        ]),
    ]);

    Livewire::test(Today::class)
        ->set('search', 'breaking')
        ->assertSee('Breaking Bad')
        ->call('chooseTmdb', 1396)
        ->assertSet('selectedTitleId', null) // no form to fill in
        ->assertSet('search', '');

    $title = WatchTitle::sole();
    expect($title->media_type)->toBe(WatchTitle::TV)
        ->and($title->year)->toBe(2008);

    $entry = WatchEntry::sole();
    expect($entry->watched_on->toDateString())->toBe('2026-11-07')
        ->and($entry->location)->toBeNull();

    Http::assertSent(fn ($request) => $request['api_key'] === 'test-key');
});

test('recently watched shows can be ticked and unticked for the day', function () {
    $show = WatchTitle::factory()->tv()->create(['title' => 'Slow Horses']);
    WatchEntry::factory()->for($show, 'title')->create(['watched_on' => '2026-11-05', 'location' => null]);

    $component = Livewire::test(Today::class)->assertSee('Last watched Thu 5 Nov');

    $logged = fn () => array_column($component->get('recentShows'), 'logged');
    $loggedToday = fn () => WatchEntry::whereDate('watched_on', '2026-11-07')->count();

    expect($logged())->toBe([false]);

    $component->call('toggleShow', $show->id);
    expect($loggedToday())->toBe(1)
        ->and($logged())->toBe([true]); // still listed, now ticked

    $component->call('toggleShow', $show->id);
    expect($loggedToday())->toBe(0)
        ->and($logged())->toBe([false])
        ->and(WatchEntry::count())->toBe(1); // the earlier day is untouched
});

test('the recent list covers the last 15 days, once per show, in a stable order', function () {
    $older = WatchTitle::factory()->tv()->create(['title' => 'Andor']);
    $recent = WatchTitle::factory()->tv()->create(['title' => 'Slow Horses']);
    $tooOld = WatchTitle::factory()->tv()->create(['title' => 'Taskmaster']);
    $film = WatchTitle::factory()->create(['title' => 'Dune']);

    foreach (['2026-11-01', '2026-11-03'] as $day) {
        WatchEntry::factory()->for($older, 'title')->create(['watched_on' => $day, 'location' => null]);
    }
    WatchEntry::factory()->for($recent, 'title')->create(['watched_on' => '2026-11-06', 'location' => null]);
    WatchEntry::factory()->for($tooOld, 'title')->create(['watched_on' => '2026-10-20', 'location' => null]);
    WatchEntry::factory()->for($film, 'title')->create(['watched_on' => '2026-11-06']);

    $component = Livewire::test(Today::class);
    $titles = fn () => array_map(fn ($s) => $s['title']->title, $component->get('recentShows'));

    expect($titles())->toBe(['Slow Horses', 'Andor']);

    // Ticking the older show today doesn't move it to the top.
    $component->call('toggleShow', $older->id);
    expect($titles())->toBe(['Slow Horses', 'Andor']);
});

test('a show added from search appears ticked at the top of the recent list', function () {
    $new = WatchTitle::factory()->tv()->create(['title' => 'Brand New Show']);
    $recent = WatchTitle::factory()->tv()->create(['title' => 'Slow Horses']);
    WatchEntry::factory()->for($recent, 'title')->create(['watched_on' => '2026-11-06', 'location' => null]);

    $component = Livewire::test(Today::class)->call('chooseTitle', $new->id);

    $shows = $component->get('recentShows');
    expect($shows[0]['title']->id)->toBe($new->id)
        ->and($shows[0]['logged'])->toBeTrue()
        ->and($shows[0]['last_watched'])->toBeNull();

    $component->assertSee('First time');
});

test('logging the same show twice on one day only records it once', function () {
    $show = WatchTitle::factory()->tv()->create();

    Livewire::test(Today::class)
        ->call('chooseTitle', $show->id)
        ->call('chooseTitle', $show->id);

    expect(WatchEntry::count())->toBe(1);
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
        ->location->toBe(WatchEntry::CINEMA);
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
