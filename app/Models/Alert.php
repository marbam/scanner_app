<?php

namespace App\Models;

use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory;

    use Prunable;

    protected $fillable = [
        'interest_id',
        'title',
        'url',
        'detected_at',
        'notified_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Interest, $this>
     */
    public function interest(): BelongsTo
    {
        return $this->belongsTo(Interest::class);
    }

    /**
     * @return Builder<Alert>
     */
    public function prunable(): Builder
    {
        return static::where('detected_at', '<=', now()->subMonth());
    }
}
