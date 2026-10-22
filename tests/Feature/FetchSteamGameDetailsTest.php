<?php

use App\Jobs\FetchSteamGameDetails;
use App\Models\SteamGame;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    Sleep::fake();
});

function fakeStoreDetails(int $appid, array $data): array
{
    return [(string) $appid => ['success' => true, 'data' => $data]];
}

it('stores type, genres, categories, description and vote-ordered tags', function () {
    $game = SteamGame::factory()->create(['appid' => 108200, 'name' => 'Ticket to Ride']);

    Http::fake([
        'store.steampowered.com/*' => Http::response(fakeStoreDetails(108200, [
            'type' => 'game',
            'short_description' => 'Connect more than cities! &amp; more',
            'genres' => [['id' => '9', 'description' => 'Casual'], ['id' => '2', 'description' => 'Strategy']],
            'categories' => [['id' => 2, 'description' => 'Single-player'], ['id' => 36, 'description' => 'Online PvP']],
        ])),
        'steamspy.com/*' => Http::response(['tags' => ['Strategy' => 291, 'Board Game' => 363, 'Casual' => 232]]),
    ]);

    $processed = (new FetchSteamGameDetails)->handle();

    expect($processed)->toBe(1);

    $game->refresh();
    expect($game->store_type)->toBe('game')
        ->and($game->short_description)->toBe('Connect more than cities! & more')
        ->and($game->genres)->toBe(['Casual', 'Strategy'])
        ->and($game->categories)->toBe(['Single-player', 'Online PvP'])
        ->and($game->tags)->toBe(['Board Game', 'Strategy', 'Casual'])
        ->and($game->details_synced_at)->not->toBeNull();
});

it('marks delisted games as unavailable instead of retrying them forever', function () {
    $game = SteamGame::factory()->create(['appid' => 999]);

    Http::fake([
        'store.steampowered.com/*' => Http::response(['999' => ['success' => false]]),
        'steamspy.com/*' => Http::response(['tags' => []]),
    ]);

    (new FetchSteamGameDetails)->handle();

    $game->refresh();
    expect($game->store_type)->toBe('unavailable')
        ->and($game->tags)->toBe([])
        ->and($game->details_synced_at)->not->toBeNull();
});

it('keeps the store details when SteamSpy is down', function () {
    $game = SteamGame::factory()->create(['appid' => 108200]);

    Http::fake([
        'store.steampowered.com/*' => Http::response(fakeStoreDetails(108200, ['type' => 'game', 'categories' => [['id' => 2, 'description' => 'Single-player']]])),
        'steamspy.com/*' => Http::response('down', 503),
    ]);

    (new FetchSteamGameDetails)->handle();

    $game->refresh();
    expect($game->categories)->toBe(['Single-player'])
        ->and($game->tags)->toBeNull()
        ->and($game->details_synced_at)->not->toBeNull();
});

it('stops the batch on a rate limit and leaves remaining games pending', function () {
    $first = SteamGame::factory()->create(['appid' => 1]);
    $second = SteamGame::factory()->create(['appid' => 2]);

    Http::fake([
        'store.steampowered.com/*' => Http::sequence()
            ->push(fakeStoreDetails(1, ['type' => 'game']))
            ->push('Too Many Requests', 429),
        'steamspy.com/*' => Http::response(['tags' => ['Puzzle' => 10]]),
    ]);

    $processed = (new FetchSteamGameDetails)->handle();

    expect($processed)->toBe(1)
        ->and($first->refresh()->details_synced_at)->not->toBeNull()
        ->and($second->refresh()->details_synced_at)->toBeNull();
});

it('skips games that already have details and respects the limit', function () {
    SteamGame::factory()->create(['details_synced_at' => now()]);
    SteamGame::factory()->count(3)->create();

    Http::fake([
        'store.steampowered.com/*' => Http::response([]),
        'steamspy.com/*' => Http::response(['tags' => []]),
    ]);

    $processed = (new FetchSteamGameDetails(limit: 2))->handle();

    expect($processed)->toBe(2)
        ->and(SteamGame::whereNull('details_synced_at')->count())->toBe(1);
    Sleep::assertSleptTimes(1);
});

it('reports progress from the artisan command', function () {
    SteamGame::factory()->count(2)->create();

    Http::fake([
        'store.steampowered.com/*' => Http::response([]),
        'steamspy.com/*' => Http::response(['tags' => []]),
    ]);

    $this->artisan('app:sync-steam-game-details')
        ->expectsOutputToContain('Done: 2 games updated, 0 still pending.')
        ->assertSuccessful();
});
