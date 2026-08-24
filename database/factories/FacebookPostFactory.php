<?php

namespace Database\Factories;

use App\Models\FacebookPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacebookPost>
 */
class FacebookPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_index' => $this->faker->unique()->numberBetween(1, 1_000_000),
            'posted_at' => $this->faker->dateTimeBetween('-15 years'),
            'title' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'attachments' => null,
        ];
    }
}
