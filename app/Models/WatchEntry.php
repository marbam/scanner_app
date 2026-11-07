<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\WatchEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One viewing of a film or TV episode on a given day.
 *
 * @property int $watch_title_id
 * @property CarbonInterface $watched_on
 * @property int|null $season
 * @property int|null $episode
 * @property string|null $location
 * @property-read WatchTitle $title The foreign key is required and cascades on delete, so this always exists.
 */
class WatchEntry extends Model
{
    /** @use HasFactory<WatchEntryFactory> */
    use HasFactory;

    public const HOME = 'home';

    public const CINEMA = 'cinema';

    protected $fillable = [
        'watch_title_id',
        'watched_on',
        'season',
        'episode',
        'location',
    ];

    protected function casts(): array
    {
        return [
            'watched_on' => 'date',
            'season' => 'integer',
            'episode' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WatchTitle, $this>
     */
    public function title(): BelongsTo
    {
        return $this->belongsTo(WatchTitle::class, 'watch_title_id');
    }

    /** e.g. "S2 E6", or null when no episode was recorded. */
    public function episodeLabel(): ?string
    {
        if ($this->season === null && $this->episode === null) {
            return null;
        }

        return trim(($this->season !== null ? "S{$this->season} " : '').($this->episode !== null ? "E{$this->episode}" : ''));
    }
}
