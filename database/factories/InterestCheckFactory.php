<?php

namespace Database\Factories;

use App\Models\Interest;
use App\Models\InterestCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterestCheck>
 */
class InterestCheckFactory extends Factory
{
    protected $model = InterestCheck::class;

    public function definition(): array
    {
        return [
            'interest_id' => Interest::factory(),
            'http_status' => 200,
            'response_body' => [],
            'outcome' => 'checked_no_release',
            'error_message' => null,
        ];
    }
}
