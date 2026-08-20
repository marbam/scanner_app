<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterestCheck extends Model
{
    use HasFactory;

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

    public function interest(): BelongsTo
    {
        return $this->belongsTo(Interest::class);
    }
}
