<?php

namespace Database\Factories;

use App\Models\PlanningApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanningApplication>
 */
class PlanningApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => $this->faker->unique()->numerify('26/#####/A'),
            'address' => $this->faker->streetAddress().', Bristol',
            'proposal' => $this->faker->sentence(),
            'status' => 'Pending Consideration',
            'decision' => null,
            'decision_date' => null,
            'viewed' => false,
        ];
    }
}
