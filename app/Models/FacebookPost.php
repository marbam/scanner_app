<?php

namespace App\Models;

use Database\Factories\FacebookPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $posted_at
 */
class FacebookPost extends Model
{
    /** @use HasFactory<FacebookPostFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'source_index',
        'posted_at',
        'title',
        'body',
        'attachments',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'attachments' => 'array',
        ];
    }
}
