<?php

namespace Database\Factories;

use App\Models\Interest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Interest>
 */
class InterestFactory extends Factory
{
    protected $model = Interest::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'provider' => 'ryanair',
            'provider_params' => [
                'origin' => 'BRS',
                'destination' => 'VLC',
                'month' => '2027-03-01',
            ],
            'status' => 'watching',
            'enabled' => true,
            'last_response_hash' => null,
            'last_checked_at' => null,
        ];
    }
}
