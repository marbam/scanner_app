<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use HasFactory;

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

    public function interest(): BelongsTo
    {
        return $this->belongsTo(Interest::class);
    }
}
