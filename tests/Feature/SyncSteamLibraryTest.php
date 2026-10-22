<?php

use App\Jobs\SyncSteamLibrary;
use App\Models\SteamGame;
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
        && (int) $request['include_appinfo'] === 1);
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
