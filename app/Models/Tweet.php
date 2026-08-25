<?php

namespace App\Models;

use Database\Factories\TweetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $posted_at
 */
class Tweet extends Model
{
    /** @use HasFactory<TweetFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tweet_id',
        'posted_at',
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
