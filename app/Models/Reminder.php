<?php

namespace App\Models;

use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int, int> $days_of_week
 */
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'time',
        'days_of_week',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'days_of_week' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Reminder>  $query
     * @return Builder<Reminder>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isDueAt(Carbon $now): bool
    {
        return $this->is_active
            && in_array($now->dayOfWeek, $this->days_of_week, true)
            && $now->format('H:i') === substr((string) $this->time, 0, 5);
    }
}
