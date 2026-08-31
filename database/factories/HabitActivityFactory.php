<?php

namespace Database\Factories;

use App\Models\HabitActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitActivity>
 */
class HabitActivityFactory extends Factory
{
    protected $model = HabitActivity::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(['#22c55e', '#3b82f6', '#f97316', '#a855f7', '#ef4444']),
            'weight' => 1,
            'value_type' => 'boolean',
            'sort_order' => 0,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
