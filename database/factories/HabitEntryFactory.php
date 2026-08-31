<?php

namespace Database\Factories;

use App\Models\HabitActivity;
use App\Models\HabitEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitEntry>
 */
class HabitEntryFactory extends Factory
{
    protected $model = HabitEntry::class;

    public function definition(): array
    {
        return [
            'habit_activity_id' => HabitActivity::factory(),
            'date' => now()->toDateString(),
            'completed' => true,
            'value' => null,
        ];
    }
}
