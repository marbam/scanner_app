<?php

namespace App\Models;

use Database\Factories\HabitEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HabitEntry extends Model
{
    /** @use HasFactory<HabitEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'habit_activity_id',
        'date',
        'completed',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'completed' => 'boolean',
            'value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<HabitActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(HabitActivity::class, 'habit_activity_id');
    }
}
