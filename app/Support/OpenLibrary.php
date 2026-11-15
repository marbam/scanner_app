<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Small client for Open Library's free search API (no key needed), used to
 * find books for the reading log. Open Library asks API users to send a
 * descriptive User-Agent and to keep request volumes low, so searches are
 * cached for a day.
 */
class OpenLibrary
{
    public const SEARCH_URL = 'https://openlibrary.org/search.json';

    public const COVER_BASE = 'https://covers.openlibrary.org/b/id/';

    public const USER_AGENT = 'scanner_app personal reading log (single user)';

    /**
     * @return list<array{key: string, title: string, author: ?string, year: ?int, cover_id: ?int}>
     */
    public function search(string $query): array
    {
        $query = trim($query);

        if ($query === '') {
            return [];
        }

        $cacheKey = 'openlibrary:search:'.md5(mb_strtolower($query));

        /** @var list<array{key: string, title: string, author: ?string, year: ?int, cover_id: ?int}>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $body = Http::withUserAgent(self::USER_AGENT)
            ->acceptJson()
            ->timeout(10)
            ->get(self::SEARCH_URL, [
                'q' => $query,
                'fields' => 'key,title,author_name,first_publish_year,cover_i',
                'limit' => 10,
            ])
            ->throw()
            ->json();

        $results = [];

        foreach (is_array($body['docs'] ?? null) ? $body['docs'] : [] as $doc) {
            if (! is_array($doc) || ! is_string($doc['key'] ?? null) || ! is_string($doc['title'] ?? null)) {
                continue;
            }

            $authors = is_array($doc['author_name'] ?? null) ? array_filter($doc['author_name'], 'is_string') : [];

            $results[] = [
                'key' => $doc['key'],
                'title' => mb_substr($doc['title'], 0, 255),
                'author' => $authors === [] ? null : mb_substr(implode(', ', array_slice($authors, 0, 2)), 0, 255),
                'year' => is_int($doc['first_publish_year'] ?? null) ? $doc['first_publish_year'] : null,
                'cover_id' => is_int($doc['cover_i'] ?? null) ? $doc['cover_i'] : null,
            ];
        }

        Cache::put($cacheKey, $results, now()->addDay());

        return $results;
    }

    /**
     * @param  'S'|'M'|'L'  $size
     */
    public static function coverUrl(?int $coverId, string $size = 'S'): ?string
    {
        return $coverId ? self::COVER_BASE.$coverId.'-'.$size.'.jpg' : null;
    }
}
