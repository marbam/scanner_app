<?php

namespace App\Jobs;

use App\Models\SteamGame;
use App\Models\SteamGamePlay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pulls the owner's Steam library from the official Web API and upserts it
 * into steam_games. Only library fields (name, playtime, last played) are
 * written here, so the owner's own "never suggest"/"not tonight" choices and
 * the store details fetched separately are never overwritten by a re-sync.
 *
 * It also builds a play log (steam_game_plays): Steam only reports each
 * game's total playtime and latest session time, so any game whose total went
 * up or whose last-played time moved since the previous sync is recorded as
 * played on its last-played date (UK time), with the minutes added.
 */
class SyncSteamLibrary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected const API_URL = 'https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/';

    protected const TIMEZONE = 'Europe/London';

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
            // Steam hides "unvetted" apps by default, which drops some new or
            // small releases (e.g. Click & Mine) from the library entirely.
            'skip_unvetted_apps' => 0,
            'format' => 'json',
        ]);

        $response->throw();

        // A private "Game details" setting returns 200 with an empty
        // response object rather than an error, so check for it explicitly.
        $games = $response->json('response.games');

        if (! is_array($games)) {
            throw new RuntimeException('Steam returned no games. Check that your profile\'s "Game details" privacy setting is Public.');
        }

        // Snapshot of the previous sync, to work out what's been played since.
        $previous = SteamGame::query()
            ->get(['appid', 'playtime_forever', 'last_played_at'])
            ->keyBy('appid');

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

        DB::transaction(function () use ($rows, $previous) {
            foreach (array_chunk($rows, 500) as $chunk) {
                SteamGame::upsert(
                    $chunk,
                    uniqueBy: ['appid'],
                    update: ['name', 'playtime_forever', 'playtime_2weeks', 'last_played_at', 'updated_at'],
                );
            }

            // On the very first import there's nothing to compare against, so
            // the whole library would look "just played"; skip logging then.
            if ($previous->isNotEmpty()) {
                $this->recordPlays($rows, $previous);
            }
        });

        return count($rows);
    }

    /**
     * @param  array<int, array{appid: int, playtime_forever: int, last_played_at: string|null}>  $rows
     * @param  Collection<int|string, SteamGame>  $previous
     */
    protected function recordPlays(array $rows, Collection $previous): void
    {
        $plays = [];

        foreach ($rows as $row) {
            $before = $previous->get($row['appid']);
            $lastPlayed = $row['last_played_at'] !== null ? Carbon::parse($row['last_played_at']) : null;

            if ($before === null) {
                // Newly added to the library: count it only if it's actually
                // been played since the previous sync could have seen it.
                $played = $row['playtime_forever'] > 0 && $lastPlayed !== null && $lastPlayed->gt(now()->subDays(2));
                $minutes = $row['playtime_forever'];
            } else {
                $minutes = max(0, $row['playtime_forever'] - $before->playtime_forever);
                $movedOn = $lastPlayed !== null && ($before->last_played_at === null || $lastPlayed->gt($before->last_played_at));
                $played = $minutes > 0 || $movedOn;
            }

            if (! $played) {
                continue;
            }

            // Attribute the play to the UK date of the latest session; if Steam
            // didn't give one, fall back to yesterday (the sync runs overnight).
            $playedOn = ($lastPlayed ?? now()->subDay())->copy()->setTimezone(self::TIMEZONE)->toDateString();

            $plays[] = ['appid' => $row['appid'], 'played_on' => $playedOn, 'minutes' => $minutes];
        }

        if ($plays === []) {
            return;
        }

        $ids = SteamGame::whereIn('appid', array_column($plays, 'appid'))->pluck('id', 'appid');

        foreach ($plays as $play) {
            $log = SteamGamePlay::where('steam_game_id', $ids[$play['appid']])
                ->whereDate('played_on', $play['played_on'])
                ->first()
                ?? new SteamGamePlay(['steam_game_id' => $ids[$play['appid']], 'played_on' => $play['played_on'], 'minutes' => 0]);

            $log->minutes += $play['minutes'];
            $log->save();
        }
    }
}
