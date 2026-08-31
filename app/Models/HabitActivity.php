<?php

namespace App\Models;

use Database\Factories\HabitActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HabitActivity extends Model
{
    /** @use HasFactory<HabitActivityFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'weight',
        'value_type',
        'sort_order',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<HabitEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(HabitEntry::class);
    }

    /**
     * @param  Builder<HabitActivity>  $query
     * @return Builder<HabitActivity>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
