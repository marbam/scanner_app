<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A day on which a Steam game was played, recorded by SyncSteamLibrary.
 *
 * @property int $steam_game_id
 * @property CarbonInterface $played_on
 * @property int $minutes
 */
class SteamGamePlay extends Model
{
    protected $fillable = [
        'steam_game_id',
        'played_on',
        'minutes',
    ];

    protected function casts(): array
    {
        return [
            'played_on' => 'date',
            'minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SteamGame, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(SteamGame::class, 'steam_game_id');
    }
}
