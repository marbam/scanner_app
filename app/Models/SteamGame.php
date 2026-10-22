<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\SteamGameFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            'never_suggest' => 'boolean',
            'skipped_until' => 'datetime',
        ];
    }

    public function headerImageUrl(): string
    {
        return "https://cdn.akamai.steamstatic.com/steam/apps/{$this->appid}/header.jpg";
    }

    public function launchUrl(): string
    {
        return "steam://run/{$this->appid}";
    }
}
