<?php

namespace Database\Factories;

use App\Models\WatchTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchTitle>
 */
class WatchTitleFactory extends Factory
{
    protected $model = WatchTitle::class;

    public function definition(): array
    {
        return [
            'media_type' => WatchTitle::MOVIE,
            'tmdb_id' => fake()->unique()->numberBetween(1, 9_999_999),
            'title' => fake()->words(3, true),
            'year' => fake()->numberBetween(1970, 2026),
            'poster_path' => null,
            'runtime' => 120,
        ];
    }

    public function tv(): static
    {
        return $this->state(fn () => [
            'media_type' => WatchTitle::TV,
            'runtime' => null,
        ]);
    }
}
