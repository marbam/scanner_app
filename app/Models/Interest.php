<?php

namespace App\Models;

use Database\Factories\InterestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interest extends Model
{
    /** @use HasFactory<InterestFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'provider_params',
        'status',
        'enabled',
        'last_response_hash',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_params' => 'array',
            'enabled' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function checks(): HasMany
    {
        return $this->hasMany(InterestCheck::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
