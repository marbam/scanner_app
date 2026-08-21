<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Interest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'interest_id' => Interest::factory(),
            'title' => fake()->sentence(3),
            'url' => fake()->url(),
            'detected_at' => now(),
            'notified_at' => now(),
        ];
    }
}
