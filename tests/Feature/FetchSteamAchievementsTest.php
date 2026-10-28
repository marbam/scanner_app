<?php

use App\Jobs\FetchSteamAchievements;
use App\Models\SteamGame;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();

    config([
        'services.steam.key' => 'fake-steam-key',
        'services.steam.steam_id' => '76561198000000000',
    ]);
});

function fakeAchievements(array $achievedFlags): array
{
    return ['playerstats' => [
        'success' => true,
        'achievements' => array_map(
            fn (int $index, bool $achieved) => ['apiname' => "ACH_{$index}", 'achieved' => (int) $achieved, 'unlocktime' => 0],
            array_keys($achievedFlags),
            $achievedFlags,
        ),
    ]];
}

it('stores unlocked and total achievement counts', function () {
    $game = SteamGame::factory()->create(['appid' => 413150]);

    Http::fake([
        'api.steampowered.com/*' => Http::response(fakeAchievements([true, true, false, true])),
    ]);

    expect((new FetchSteamAchievements)->handle())->toBe(1);

    $game->refresh();
    expect($game->achievements_total)->toBe(4)
        ->and($game->achievements_unlocked)->toBe(3)
        ->and($game->achievementPercent())->toBe(75.0)
        ->and($game->isFullyAchieved())->toBeFalse()
        ->and($game->achievements_synced_at)->not->toBeNull();

    Http::assertSent(fn (Request $request) => (int) $request['appid'] === 413150 && $request['steamid'] === '76561198000000000');
});

it('records games without achievements as zero instead of failing', function () {
    $game = SteamGame::factory()->create(['appid' => 70]);

    Http::fake([
        'api.steampowered.com/*' => Http::response(['playerstats' => ['error' => 'Requested app has no stats', 'success' => false]], 400),
    ]);

    (new FetchSteamAchievements)->handle();

    $game->refresh();
    expect($game->achievements_total)->toBe(0)
        ->and($game->hasAchievements())->toBeFalse()
        ->and($game->achievementPercent())->toBeNull()
        ->and($game->achievements_synced_at)->not->toBeNull();
});

it('marks a game with everything unlocked as fully achieved', function () {
    $game = SteamGame::factory()->create();

    Http::fake(['api.steampowered.com/*' => Http::response(fakeAchievements([true, true]))]);

    (new FetchSteamAchievements)->handle();

    expect($game->refresh()->isFullyAchieved())->toBeTrue();
});

it('stops the batch on other errors and leaves games pending', function () {
    $first = SteamGame::factory()->create();
    $second = SteamGame::factory()->create();

    Http::fake([
        'api.steampowered.com/*' => Http::sequence()
            ->push(fakeAchievements([true]))
            ->push('Too Many Requests', 429),
    ]);

    expect((new FetchSteamAchievements)->handle())->toBe(1)
        ->and($first->refresh()->achievements_synced_at)->not->toBeNull()
        ->and($second->refresh()->achievements_synced_at)->toBeNull();
});

it('skips a single game Steam errors on and carries on with the rest', function () {
    $broken = SteamGame::factory()->create(['appid' => 399780]);
    $fine = SteamGame::factory()->create(['appid' => 413150]);

    Http::fake([
        'api.steampowered.com/*' => Http::sequence()
            ->push(['playerstats' => ['error' => 'Internal server error', 'success' => false]], 500)
            ->push(fakeAchievements([true, false])),
    ]);

    expect((new FetchSteamAchievements)->handle())->toBe(2);

    $broken->refresh();
    expect($broken->achievements_total)->toBeNull()
        ->and($broken->achievements_synced_at)->not->toBeNull()
        ->and($fine->refresh()->achievements_total)->toBe(2);
});

it('retries a dropped connection before giving up', function () {
    $game = SteamGame::factory()->create();

    Http::fake([
        'api.steampowered.com/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(fakeAchievements([true, false, false])),
    ]);

    expect((new FetchSteamAchievements)->handle())->toBe(1)
        ->and($game->refresh()->achievements_total)->toBe(3);
});

it('stops when a connection keeps dropping', function () {
    SteamGame::factory()->count(2)->create();

    Http::fake([
        'api.steampowered.com/*' => Http::sequence()
            ->pushFailedConnection()
            ->pushFailedConnection()
            ->pushFailedConnection(),
    ]);

    expect((new FetchSteamAchievements)->handle())->toBe(0)
        ->and(FetchSteamAchievements::pendingQuery()->count())->toBe(2);
});

it('stops when several games in a row hit server errors', function () {
    SteamGame::factory()->count(5)->create();

    Http::fake(['api.steampowered.com/*' => Http::response('Bad Gateway', 502)]);

    expect((new FetchSteamAchievements)->handle())->toBe(2)
        ->and(FetchSteamAchievements::pendingQuery()->count())->toBe(3);
});

it('only re-fetches games played since their last fetch', function () {
    $fresh = SteamGame::factory()->create([
        'last_played_at' => now()->subDays(3),
        'achievements_total' => 10, 'achievements_unlocked' => 5, 'achievements_synced_at' => now()->subDay(),
    ]);
    $playedSince = SteamGame::factory()->create([
        'last_played_at' => now()->subHour(),
        'achievements_total' => 10, 'achievements_unlocked' => 5, 'achievements_synced_at' => now()->subDay(),
    ]);

    Http::fake(['api.steampowered.com/*' => Http::response(fakeAchievements(array_fill(0, 10, true)))]);

    expect((new FetchSteamAchievements)->handle())->toBe(1)
        ->and($playedSince->refresh()->achievements_unlocked)->toBe(10)
        ->and($fresh->refresh()->achievements_unlocked)->toBe(5);
});

it('reports a summary from the artisan command', function () {
    SteamGame::factory()->count(2)->create();

    Http::fake(['api.steampowered.com/*' => Http::response(fakeAchievements([true, true]))]);

    $this->artisan('app:sync-steam-achievements')
        ->expectsOutputToContain('Done: 2 games updated. 2 games at 100%.')
        ->assertSuccessful();
});
