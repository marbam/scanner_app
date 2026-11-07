<?php

namespace App\Support;

use App\Models\WatchTitle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Small client for The Movie Database (TMDB) API, used to search for films
 * and TV shows and copy their details into watch_titles.
 *
 * Accepts either kind of TMDB credential in TMDB_API_KEY: the short v3
 * "API Key" (sent as ?api_key=) or the long "API Read Access Token" (sent
 * as a bearer token).
 */
class Tmdb
{
    public const BASE_URL = 'https://api.themoviedb.org/3';

    public const IMAGE_BASE = 'https://image.tmdb.org/t/p/';

    public static function configured(): bool
    {
        return filled(config('services.tmdb.key'));
    }

    /**
     * @param  'movie'|'tv'  $type
     * @return list<array{tmdb_id: int, title: string, year: ?int, poster_path: ?string}>
     */
    public function search(string $type, string $query): array
    {
        $query = trim($query);

        if ($query === '' || ! self::configured()) {
            return [];
        }

        $cacheKey = 'tmdb:search:'.$type.':'.md5(mb_strtolower($query));

        /** @var list<array{tmdb_id: int, title: string, year: ?int, poster_path: ?string}>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $body = $this->client()
            ->get("/search/{$type}", $this->withKey(['query' => $query, 'include_adult' => 'false']))
            ->throw()
            ->json();

        $results = [];

        foreach (is_array($body['results'] ?? null) ? $body['results'] : [] as $item) {
            if (! is_array($item) || ! is_int($item['id'] ?? null)) {
                continue;
            }

            $results[] = $this->summarise($type, $item);
        }

        Cache::put($cacheKey, $results, now()->addDay());

        return $results;
    }

    /**
     * Fetch a title's full details from TMDB and save (or refresh) its row.
     *
     * @param  'movie'|'tv'  $type
     */
    public function import(string $type, int $tmdbId): WatchTitle
    {
        $body = $this->client()
            ->get("/{$type}/{$tmdbId}", $this->withKey())
            ->throw()
            ->json();

        $details = $this->summarise($type, is_array($body) ? $body : []);

        $attributes = [
            'title' => $details['title'],
            'year' => $details['year'],
            'poster_path' => $details['poster_path'],
            'runtime' => is_int($body['runtime'] ?? null) ? $body['runtime'] : null,
            'seasons' => null,
        ];

        if ($type === WatchTitle::TV) {
            $seasons = [];

            foreach (is_array($body['seasons'] ?? null) ? $body['seasons'] : [] as $season) {
                $number = is_array($season) ? ($season['season_number'] ?? null) : null;
                $count = is_array($season) ? ($season['episode_count'] ?? null) : null;

                // Season 0 is TMDB's "Specials" bucket, not a real season.
                if (is_int($number) && $number > 0 && is_int($count)) {
                    $seasons[$number] = $count;
                }
            }

            $attributes['seasons'] = $seasons;
        }

        $title = WatchTitle::updateOrCreate(['media_type' => $type, 'tmdb_id' => $tmdbId], $attributes);
        $title->touch(); // marks when details were last refreshed, even if nothing changed

        return $title;
    }

    /**
     * @param  array<mixed>  $item
     * @return array{tmdb_id: int, title: string, year: ?int, poster_path: ?string}
     */
    private function summarise(string $type, array $item): array
    {
        $name = $type === WatchTitle::TV ? ($item['name'] ?? null) : ($item['title'] ?? null);
        $date = $type === WatchTitle::TV ? ($item['first_air_date'] ?? null) : ($item['release_date'] ?? null);

        return [
            'tmdb_id' => is_int($item['id'] ?? null) ? $item['id'] : 0,
            'title' => is_string($name) && $name !== '' ? $name : 'Untitled',
            'year' => is_string($date) && preg_match('/^(\d{4})/', $date, $m) ? (int) $m[1] : null,
            'poster_path' => is_string($item['poster_path'] ?? null) ? $item['poster_path'] : null,
        ];
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl(self::BASE_URL)->acceptJson()->timeout(10);

        $key = (string) config('services.tmdb.key');

        // The read access token is a JWT, so it always starts "eyJ".
        return str_starts_with($key, 'eyJ') ? $request->withToken($key) : $request;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function withKey(array $query = []): array
    {
        $key = (string) config('services.tmdb.key');

        return str_starts_with($key, 'eyJ') ? $query : [...$query, 'api_key' => $key];
    }
}
