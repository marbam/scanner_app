<?php

namespace App\Models;

use App\Support\Tmdb;
use Database\Factories\WatchTitleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, int>|null $seasons
 */
class WatchTitle extends Model
{
    /** @use HasFactory<WatchTitleFactory> */
    use HasFactory;

    public const MOVIE = 'movie';

    public const TV = 'tv';

    protected $fillable = [
        'media_type',
        'tmdb_id',
        'title',
        'year',
        'poster_path',
        'runtime',
        'seasons',
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'year' => 'integer',
            'runtime' => 'integer',
            'seasons' => 'array',
        ];
    }

    /**
     * @return HasMany<WatchEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WatchEntry::class);
    }

    public function isTv(): bool
    {
        return $this->media_type === self::TV;
    }

    public function posterUrl(string $size = 'w92'): ?string
    {
        return $this->poster_path ? Tmdb::IMAGE_BASE.$size.$this->poster_path : null;
    }

    public function episodeCount(int $season): ?int
    {
        return $this->seasons[$season] ?? null;
    }

    /**
     * The episode to suggest after the given one: the next in the season, or
     * the first of the next season once this one is finished.
     *
     * @return array{0: int, 1: int}
     */
    public function nextEpisodeAfter(?int $season, ?int $episode): array
    {
        $seasonNumbers = array_keys($this->seasons ?? []);
        sort($seasonNumbers);

        if ($season === null || $episode === null) {
            return [$seasonNumbers[0] ?? 1, 1];
        }

        $count = $this->episodeCount($season);

        if ($count !== null && $episode >= $count) {
            foreach ($seasonNumbers as $number) {
                if ($number > $season) {
                    return [$number, 1];
                }
            }
        }

        return [$season, $episode + 1];
    }
}
