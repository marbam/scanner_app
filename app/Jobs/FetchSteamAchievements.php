<?php

namespace App\Jobs;

use App\Models\SteamGame;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Records how many achievements the owner has unlocked per game, using the
 * official ISteamUserStats/GetPlayerAchievements endpoint (one request per
 * game). A game is (re)fetched when it has never been fetched, or when it's
 * been played since the last fetch — so after the first full pass, only
 * games actually played get re-checked.
 *
 * Games without achievements come back as HTTP 400 "Requested app has no
 * stats"; they're stored as 0 total rather than retried. Any other failure
 * (rate limit, private profile, Steam down) stops the batch early and the
 * remaining games are picked up on the next run.
 */
class FetchSteamAchievements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected const API_URL = 'https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v1/';

    protected const MAX_CONSECUTIVE_SERVER_ERRORS = 3;

    public int $timeout = 900;

    public function __construct(public int $limit = 100) {}

    /**
     * @return Builder<SteamGame>
     */
    public static function pendingQuery(): Builder
    {
        return SteamGame::query()->where(fn (Builder $query) => $query
            ->whereNull('achievements_synced_at')
            ->orWhereColumn('last_played_at', '>', 'achievements_synced_at'));
    }

    /**
     * @param  (callable(SteamGame): void)|null  $onProgress
     * @return int number of games processed
     */
    public function handle(?callable $onProgress = null): int
    {
        $key = config('services.steam.key');
        $steamId = config('services.steam.steam_id');

        if (! $key || ! $steamId) {
            throw new RuntimeException('STEAM_API_KEY and STEAM_ID must both be set in .env.');
        }

        $games = self::pendingQuery()->orderBy('id')->limit($this->limit)->get();
        $processed = 0;
        $consecutiveServerErrors = 0;

        foreach ($games as $index => $game) {
            if ($index > 0) {
                Sleep::for(300)->milliseconds();
            }

            try {
                [$total, $unlocked] = $this->fetchCounts($game->appid, $key, $steamId);
                $consecutiveServerErrors = 0;
            } catch (RequestException $e) {
                // Steam returns a permanent 500 for a few specific apps. Record
                // those as "unknown" so they don't block every later game, but
                // treat several 5xx in a row as Steam being down and stop.
                if ($e->response->serverError() && ++$consecutiveServerErrors < self::MAX_CONSECUTIVE_SERVER_ERRORS) {
                    Log::info("Steam achievements unavailable for appid {$game->appid} (HTTP {$e->response->status()}); recorded as unknown.");
                    [$total, $unlocked] = [null, null];
                } else {
                    Log::warning("Steam achievements fetch stopped at appid {$game->appid}: {$e->getMessage()}");

                    break;
                }
            } catch (\Throwable $e) {
                Log::warning("Steam achievements fetch stopped at appid {$game->appid}: {$e->getMessage()}");

                break;
            }

            $game->forceFill([
                'achievements_total' => $total,
                'achievements_unlocked' => $unlocked,
                'achievements_synced_at' => now(),
            ])->save();

            $processed++;

            if ($onProgress !== null) {
                $onProgress($game);
            }
        }

        return $processed;
    }

    /**
     * @return array{0: int, 1: int} [total, unlocked]
     *
     * @throws RequestException
     */
    protected function fetchCounts(int $appid, string $key, string $steamId): array
    {
        $response = Http::timeout(20)->get(self::API_URL, [
            'key' => $key,
            'steamid' => $steamId,
            'appid' => $appid,
        ]);

        $error = (string) $response->json('playerstats.error', '');

        if ($response->status() === 400 && str_contains(strtolower($error), 'no stats')) {
            return [0, 0];
        }

        $response->throw();

        $achievements = $response->json('playerstats.achievements', []);

        if (! is_array($achievements)) {
            return [0, 0];
        }

        $unlocked = count(array_filter($achievements, fn ($achievement) => is_array($achievement) && ! empty($achievement['achieved'])));

        return [count($achievements), $unlocked];
    }
}
