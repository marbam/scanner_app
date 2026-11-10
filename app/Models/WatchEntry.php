<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\WatchEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Watching a film or show on a given day. Shows are logged at most once per
 * show per day; films can be logged again (e.g. finished on a later day).
 *
 * @property int $watch_title_id
 * @property CarbonInterface $watched_on
 * @property string|null $location Films only: 'home' or 'cinema'.
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
        'location',
    ];

    protected function casts(): array
    {
        return [
            'watched_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<WatchTitle, $this>
     */
    public function title(): BelongsTo
    {
        return $this->belongsTo(WatchTitle::class, 'watch_title_id');
    }
}
