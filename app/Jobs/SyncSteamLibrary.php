<?php

namespace App\Jobs;

use App\Models\SteamGame;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pulls the owner's Steam library from the official Web API and upserts it
 * into steam_games. Only library fields (name, playtime, last played) are
 * written here, so the owner's own "never suggest"/"not tonight" choices and
 * the store details fetched separately are never overwritten by a re-sync.
 */
class SyncSteamLibrary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected const API_URL = 'https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/';

    /**
     * @return int number of games in the library
     */
    public function handle(): int
    {
        $key = config('services.steam.key');
        $steamId = config('services.steam.steam_id');

        if (! $key || ! $steamId) {
            throw new RuntimeException('STEAM_API_KEY and STEAM_ID must both be set in .env.');
        }

        $response = Http::timeout(30)->get(self::API_URL, [
            'key' => $key,
            'steamid' => $steamId,
            'include_appinfo' => 1,
            'include_played_free_games' => 1,
            'format' => 'json',
        ]);

        $response->throw();

        // A private "Game details" setting returns 200 with an empty
        // response object rather than an error, so check for it explicitly.
        $games = $response->json('response.games');

        if (! is_array($games)) {
            throw new RuntimeException('Steam returned no games. Check that your profile\'s "Game details" privacy setting is Public.');
        }

        $rows = [];

        foreach ($games as $game) {
            if (! isset($game['appid'], $game['name'])) {
                continue;
            }

            $lastPlayed = (int) ($game['rtime_last_played'] ?? 0);

            $rows[] = [
                'appid' => (int) $game['appid'],
                'name' => $game['name'],
                'playtime_forever' => (int) ($game['playtime_forever'] ?? 0),
                'playtime_2weeks' => (int) ($game['playtime_2weeks'] ?? 0),
                'last_played_at' => $lastPlayed > 0 ? Carbon::createFromTimestamp($lastPlayed)->toDateTimeString() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            SteamGame::upsert(
                $chunk,
                uniqueBy: ['appid'],
                update: ['name', 'playtime_forever', 'playtime_2weeks', 'last_played_at', 'updated_at'],
            );
        }

        return count($rows);
    }
}
