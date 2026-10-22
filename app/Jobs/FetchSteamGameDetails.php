<?php

namespace App\Jobs;

use App\Models\SteamGame;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Fills in store details (type, genres, categories, description) and
 * community tags for library games that don't have them yet.
 *
 * - Store details come from Steam's own storefront API (unofficial but
 *   long-standing, ~200 requests per 5 minutes, one app per request).
 * - Tags come from SteamSpy (about one request per second), because the
 *   storefront API doesn't include the user-voted tags like "Cozy" or
 *   "Roguelike" that the suggestion scoring relies on.
 *
 * Games are throttled to roughly one every 1.5 seconds. A rate-limit (429)
 * or server error stops the batch early; unfinished games are simply picked
 * up again on the next run.
 */
class FetchSteamGameDetails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected const STORE_URL = 'https://store.steampowered.com/api/appdetails';

    protected const STEAMSPY_URL = 'https://steamspy.com/api.php';

    protected const MAX_TAGS = 20;

    public int $timeout = 900;

    public function __construct(public int $limit = 50) {}

    /**
     * @param  (callable(SteamGame): void)|null  $onProgress
     * @return int number of games processed
     */
    public function handle(?callable $onProgress = null): int
    {
        $games = SteamGame::whereNull('details_synced_at')
            ->orderBy('id')
            ->limit($this->limit)
            ->get();

        $processed = 0;

        foreach ($games as $index => $game) {
            if ($index > 0) {
                Sleep::for(1500)->milliseconds();
            }

            try {
                $details = $this->fetchStoreDetails($game->appid);
            } catch (\Throwable $e) {
                Log::warning("Steam store details fetch stopped at appid {$game->appid}: {$e->getMessage()}");

                break;
            }

            $game->fill($details);
            $game->tags = $this->fetchTags($game->appid);
            $game->details_synced_at = now();
            $game->save();

            $processed++;

            if ($onProgress !== null) {
                $onProgress($game);
            }
        }

        return $processed;
    }

    /**
     * @return array{store_type: string|null, short_description: string|null, genres: array<int, string>|null, categories: array<int, string>|null}
     */
    protected function fetchStoreDetails(int $appid): array
    {
        $response = Http::timeout(20)->get(self::STORE_URL, [
            'appids' => $appid,
            'l' => 'english',
            'cc' => 'gb',
        ]);

        $response->throw();

        $entry = $response->json((string) $appid);

        // Delisted or region-locked apps come back as {"success": false}.
        if (! is_array($entry) || ! ($entry['success'] ?? false) || ! is_array($entry['data'] ?? null)) {
            return [
                'store_type' => 'unavailable',
                'short_description' => null,
                'genres' => null,
                'categories' => null,
            ];
        }

        $data = $entry['data'];

        return [
            'store_type' => $data['type'] ?? null,
            'short_description' => isset($data['short_description']) ? html_entity_decode(strip_tags($data['short_description']), ENT_QUOTES) : null,
            'genres' => $this->descriptions($data['genres'] ?? []),
            'categories' => $this->descriptions($data['categories'] ?? []),
        ];
    }

    /**
     * Tag names ordered by vote count, strongest first. Returns null (rather
     * than failing the whole game) if SteamSpy is unavailable.
     *
     * @return array<int, string>|null
     */
    protected function fetchTags(int $appid): ?array
    {
        try {
            $response = Http::timeout(20)->get(self::STEAMSPY_URL, [
                'request' => 'appdetails',
                'appid' => $appid,
            ]);

            $response->throw();
        } catch (\Throwable $e) {
            Log::info("SteamSpy tags unavailable for appid {$appid}: {$e->getMessage()}");

            return null;
        }

        // SteamSpy returns an object of {tag: votes}, or an empty list when
        // a game has no tags.
        $tags = $response->json('tags');

        if (! is_array($tags) || $tags === [] || array_is_list($tags)) {
            return [];
        }

        arsort($tags);

        return array_slice(array_map('strval', array_keys($tags)), 0, self::MAX_TAGS);
    }

    /**
     * @return array<int, string>
     */
    protected function descriptions(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($item) => is_array($item) && isset($item['description']) ? (string) $item['description'] : null,
            $items
        )));
    }
}
