<?php

namespace App\Models;

use Database\Factories\InterestCheckFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterestCheck extends Model
{
    /** @use HasFactory<InterestCheckFactory> */
    use HasFactory;

    use Prunable;

    protected $fillable = [
        'interest_id',
        'http_status',
        'response_body',
        'outcome',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
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
     * @return Builder<InterestCheck>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subMonth());
    }
}
