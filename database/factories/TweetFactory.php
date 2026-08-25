<?php

namespace Database\Factories;

use App\Models\Tweet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tweet>
 */
class TweetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tweet_id' => (string) $this->faker->unique()->numberBetween(1_000_000_000, 9_999_999_999),
            'posted_at' => $this->faker->dateTimeBetween('-15 years'),
            'body' => $this->faker->sentence(),
            'attachments' => null,
        ];
    }
}
