<?php

namespace App\Models;

use App\Support\Tmdb;
use Database\Factories\WatchTitleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A film or TV show, with its details copied from TMDB.
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
    ];

    protected function casts(): array
    {
        return [
            'tmdb_id' => 'integer',
            'year' => 'integer',
            'runtime' => 'integer',
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
}
