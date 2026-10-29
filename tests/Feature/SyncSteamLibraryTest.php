<?php

use App\Jobs\SyncSteamLibrary;
use App\Models\SteamGame;
use App\Models\SteamGamePlay;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.steam.key' => 'fake-steam-key',
        'services.steam.steam_id' => '76561198000000000',
    ]);
});

function fakeOwnedGames(array $games): array
{
    return ['response' => ['game_count' => count($games), 'games' => $games]];
}

it('imports owned games with playtime and last played date', function () {
    Http::fake([
        'api.steampowered.com/*' => Http::response(fakeOwnedGames([
            ['appid' => 1145360, 'name' => 'Hades', 'playtime_forever' => 3120, 'playtime_2weeks' => 45, 'rtime_last_played' => 1_760_000_000],
            ['appid' => 108600, 'name' => 'Project Zomboid', 'playtime_forever' => 0, 'rtime_last_played' => 0],
        ])),
    ]);

    $count = (new SyncSteamLibrary)->handle();

    expect($count)->toBe(2);

    $hades = SteamGame::where('appid', 1145360)->sole();
    expect($hades->name)->toBe('Hades')
        ->and($hades->playtime_forever)->toBe(3120)
        ->and($hades->playtime_2weeks)->toBe(45)
        ->and($hades->last_played_at?->timestamp)->toBe(1_760_000_000);

    $zomboid = SteamGame::where('appid', 108600)->sole();
    expect($zomboid->playtime_2weeks)->toBe(0)
        ->and($zomboid->last_played_at)->toBeNull();

    Http::assertSent(fn (Request $request) => $request['key'] === 'fake-steam-key'
        && $request['steamid'] === '76561198000000000'
        && (int) $request['include_appinfo'] === 1
        && (int) $request['skip_unvetted_apps'] === 0);
});

it('updates playtime on re-sync without touching the owner\'s choices or store details', function () {
    SteamGame::factory()->create([
        'appid' => 1145360,
        'name' => 'Hades',
        'playtime_forever' => 100,
        'never_suggest' => true,
        'tags' => ['Roguelike', 'Action'],
    ]);

    Http::fake([
        'api.steampowered.com/*' => Http::response(fakeOwnedGames([
            ['appid' => 1145360, 'name' => 'Hades', 'playtime_forever' => 250],
        ])),
    ]);

    (new SyncSteamLibrary)->handle();

    $hades = SteamGame::where('appid', 1145360)->sole();
    expect($hades->playtime_forever)->toBe(250)
        ->and($hades->never_suggest)->toBeTrue()
        ->and($hades->tags)->toBe(['Roguelike', 'Action']);
    $this->assertDatabaseCount('steam_games', 1);
});

it('explains the privacy setting when Steam returns an empty response', function () {
    Http::fake([
        'api.steampowered.com/*' => Http::response(['response' => new stdClass]),
    ]);

    expect(fn () => (new SyncSteamLibrary)->handle())
        ->toThrow(RuntimeException::class, 'Game details');

    $this->assertDatabaseCount('steam_games', 0);
});

it('refuses to run without a key and SteamID', function () {
    config(['services.steam.key' => null]);
    Http::fake();

    expect(fn () => (new SyncSteamLibrary)->handle())
        ->toThrow(RuntimeException::class, 'STEAM_API_KEY');

    Http::assertNothingSent();
});

it('reports the synced count from the artisan command', function () {
    Http::fake([
        'api.steampowered.com/*' => Http::response(fakeOwnedGames([
            ['appid' => 1145360, 'name' => 'Hades', 'playtime_forever' => 3120],
            ['appid' => 108600, 'name' => 'Project Zomboid', 'playtime_forever' => 0],
        ])),
    ]);

    $this->artisan('app:sync-steam-library')
        ->expectsOutputToContain('Synced 2 games (1 never played)')
        ->assertSuccessful();
});

it('fails the artisan command cleanly on an API error', function () {
    Http::fake([
        'api.steampowered.com/*' => Http::response('Forbidden', 403),
    ]);

    $this->artisan('app:sync-steam-library')->assertFailed();
});

it('logs games played since the last sync against their UK last-played date', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-29 04:00', 'Europe/London'));

    SteamGame::factory()->create(['appid' => 1, 'name' => 'Hades', 'playtime_forever' => 100, 'last_played_at' => '2026-10-20 20:00:00']);
    SteamGame::factory()->create(['appid' => 2, 'name' => 'Balatro', 'playtime_forever' => 50, 'last_played_at' => '2026-10-20 20:00:00']);
    SteamGame::factory()->create(['appid' => 3, 'name' => 'Untouched', 'playtime_forever' => 10, 'last_played_at' => '2026-10-01 20:00:00']);

    // 23:30 UTC on the 28th is 23:30 UK (GMT) — still the 28th.
    $evening = Carbon\Carbon::parse('2026-10-28 23:30', 'UTC')->timestamp;

    Http::fake([
        'api.steampowered.com/*' => Http::response(fakeOwnedGames([
            ['appid' => 1, 'name' => 'Hades', 'playtime_forever' => 147, 'rtime_last_played' => $evening],
            ['appid' => 2, 'name' => 'Balatro', 'playtime_forever' => 62, 'rtime_last_played' => $evening],
            ['appid' => 3, 'name' => 'Untouched', 'playtime_forever' => 10, 'rtime_last_played' => Carbon\Carbon::parse('2026-10-01 20:00:00', 'UTC')->timestamp],
        ])),
    ]);

    (new SyncSteamLibrary)->handle();

    $plays = SteamGamePlay::with('game')->get();

    expect($plays)->toHaveCount(2)
        ->and($plays->map(fn ($p) => [$p->game->name, $p->played_on->toDateString(), $p->minutes])->sortBy(0)->values()->all())
        ->toBe([['Balatro', '2026-10-28', 12], ['Hades', '2026-10-28', 47]]);
});

it('adds to the same day rather than duplicating when re-synced', function () {
    $game = SteamGame::factory()->create(['appid' => 1, 'playtime_forever' => 100, 'last_played_at' => '2026-10-28 18:00:00']);
    SteamGamePlay::create(['steam_game_id' => $game->id, 'played_on' => '2026-10-28', 'minutes' => 30]);

    Http::fake([
        'api.steampowered.com/*' => Http::response(fakeOwnedGames([
            ['appid' => 1, 'name' => $game->name, 'playtime_forever' => 120, 'rtime_last_played' => Carbon\Carbon::parse('2026-10-28 21:00', 'UTC')->timestamp],
        ])),
    ]);

    (new SyncSteamLibrary)->handle();

    expect(SteamGamePlay::sole()->minutes)->toBe(50);
});

it('does not log the whole library as played on the first import', function () {
    Http::fake([
        'api.steampowered.com/*' => Http::response(fakeOwnedGames([
            ['appid' => 1, 'name' => 'Hades', 'playtime_forever' => 3120, 'rtime_last_played' => now()->subHour()->timestamp],
        ])),
    ]);

    (new SyncSteamLibrary)->handle();

    $this->assertDatabaseCount('steam_game_plays', 0);
});

it('logs a newly bought game that was played since the last sync', function () {
    SteamGame::factory()->create(['appid' => 1]);

    Http::fake([
        'api.steampowered.com/*' => Http::response(fakeOwnedGames([
            ['appid' => 1, 'name' => 'Old', 'playtime_forever' => 0],
            ['appid' => 2, 'name' => 'New', 'playtime_forever' => 25, 'rtime_last_played' => now()->subHours(3)->timestamp],
        ])),
    ]);

    (new SyncSteamLibrary)->handle();

    expect(SteamGamePlay::sole()->minutes)->toBe(25);
});

it('shows the play log grouped by day', function () {
    $this->travelTo(Carbon\Carbon::parse('2026-10-29 09:00', 'Europe/London'));

    $hades = SteamGame::factory()->create(['name' => 'Hades']);
    $balatro = SteamGame::factory()->create(['name' => 'Balatro']);
    SteamGamePlay::create(['steam_game_id' => $hades->id, 'played_on' => '2026-10-28', 'minutes' => 47]);
    SteamGamePlay::create(['steam_game_id' => $balatro->id, 'played_on' => '2026-10-28', 'minutes' => 12]);

    $this->artisan('app:steam-play-log')
        ->expectsOutputToContain('Wed 28 Oct 2026')
        ->expectsOutputToContain('Hades')
        ->expectsOutputToContain('Balatro')
        ->assertSuccessful();
});
