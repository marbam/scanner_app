<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SteamGameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $appid
 * @property string $name
 * @property int $playtime_forever
 * @property int $playtime_2weeks
 * @property CarbonInterface|null $last_played_at
 * @property array<int, string>|null $genres
 * @property array<int, string>|null $categories
 * @property array<int, string>|null $tags
 * @property CarbonInterface|null $details_synced_at
 * @property int|null $achievements_total
 * @property int|null $achievements_unlocked
 * @property CarbonInterface|null $achievements_synced_at
 * @property bool $never_suggest
 * @property CarbonInterface|null $skipped_until
 */
class SteamGame extends Model
{
    /** @use HasFactory<SteamGameFactory> */
    use HasFactory;

    protected $fillable = [
        'appid',
        'name',
        'playtime_forever',
        'playtime_2weeks',
        'last_played_at',
        'store_type',
        'short_description',
        'genres',
        'categories',
        'tags',
        'details_synced_at',
        'achievements_total',
        'achievements_unlocked',
        'achievements_synced_at',
        'never_suggest',
        'skipped_until',
    ];

    protected function casts(): array
    {
        return [
            'appid' => 'integer',
            'playtime_forever' => 'integer',
            'playtime_2weeks' => 'integer',
            'last_played_at' => 'datetime',
            'genres' => 'array',
            'categories' => 'array',
            'tags' => 'array',
            'details_synced_at' => 'datetime',
            'achievements_total' => 'integer',
            'achievements_unlocked' => 'integer',
            'achievements_synced_at' => 'datetime',
            'never_suggest' => 'boolean',
            'skipped_until' => 'datetime',
        ];
    }

    public function hasAchievements(): bool
    {
        return ($this->achievements_total ?? 0) > 0;
    }

    /**
     * Percentage of achievements unlocked, or null if the game has none (or
     * they haven't been fetched yet).
     */
    public function achievementPercent(): ?float
    {
        if (! $this->hasAchievements()) {
            return null;
        }

        return round(100 * ($this->achievements_unlocked ?? 0) / (int) $this->achievements_total, 1);
    }

    public function isFullyAchieved(): bool
    {
        return $this->hasAchievements() && ($this->achievements_unlocked ?? 0) >= $this->achievements_total;
    }

    public function headerImageUrl(): string
    {
        return "https://cdn.akamai.steamstatic.com/steam/apps/{$this->appid}/header.jpg";
    }

    public function launchUrl(): string
    {
        return "steam://run/{$this->appid}";
    }

    /**
     * @return HasMany<SteamGamePlay, $this>
     */
    public function plays(): HasMany
    {
        return $this->hasMany(SteamGamePlay::class);
    }
}
