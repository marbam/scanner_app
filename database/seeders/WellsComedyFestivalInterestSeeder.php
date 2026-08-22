<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;

class WellsComedyFestivalInterestSeeder extends Seeder
{
    /**
     * Seed the Wells Comedy Festival on-sale watch.
     */
    public function run(): void
    {
        Interest::updateOrCreate(
            ['provider' => 'wells_comedy_festival'],
            [
                'name' => 'Wells Comedy Festival',
                'provider_params' => [
                    'url' => 'https://www.wellscomfest.com/whats-on-by-day',
                    'not_on_sale_text' => 'is now over',
                ],
                'status' => 'watching',
                'enabled' => true,
            ]
        );
    }
}
