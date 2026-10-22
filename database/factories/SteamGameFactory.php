<?php

namespace Database\Factories;

use App\Models\SteamGame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SteamGame>
 */
class SteamGameFactory extends Factory
{
    protected $model = SteamGame::class;

    public function definition(): array
    {
        return [
            'appid' => fake()->unique()->numberBetween(10, 3_000_000),
            'name' => fake()->unique()->words(3, true),
            'playtime_forever' => 0,
            'playtime_2weeks' => 0,
            'last_played_at' => null,
            'never_suggest' => false,
            'skipped_until' => null,
        ];
    }
}
