<?php

namespace Database\Seeders;

use App\Models\Interest;
use Illuminate\Database\Seeder;

class InterestSeeder extends Seeder
{
    /**
     * Seed the watched Interests. Idempotent — matched on `name`, so
     * existing rows (and their status/enabled/history) are left alone;
     * only missing ones get created. Safe to re-run in production via
     * `php artisan db:seed --class=InterestSeeder`.
     */
    public function run(): void
    {
        $interests = [
            [
                'name' => 'BRS to VLC, 20 March 2027',
                'provider' => 'ryanair',
                'provider_params' => ['origin' => 'BRS', 'destination' => 'VLC', 'month' => '2027-03-01', 'day' => 20],
            ],
            [
                'name' => 'Wells Comedy Festival',
                'provider' => 'wells_comedy_festival',
                'provider_params' => ['url' => 'https://www.wellscomfest.com/whats-on-by-day', 'not_on_sale_text' => 'is now over'],
            ],
            [
                'name' => 'Avengers: Doomsday - Showcase Avonmeads',
                'provider' => 'showcase_cinemas',
                'provider_params' => ['movie_id' => '269068', 'theater_code' => 'X06JH'],
            ],
            [
                'name' => 'Avengers: Doomsday - Odeon Cabot Circus',
                'provider' => 'odeon',
                'provider_params' => ['cinema_url' => 'https://www.odeon.co.uk/cinemas/bristol/', 'film_title' => 'Avengers: Doomsday'],
            ],
            [
                'name' => 'Avengers: Endgame Encore - Showcase Avonmeads',
                'provider' => 'showcase_cinemas',
                'provider_params' => ['movie_id' => '1000036867', 'theater_code' => 'X06JH'],
            ],
            [
                'name' => 'Avengers: Endgame (Re-release) - Odeon Cabot Circus',
                'provider' => 'odeon',
                'provider_params' => ['cinema_url' => 'https://www.odeon.co.uk/cinemas/bristol/', 'film_title' => 'Avengers: Endgame'],
            ],
            [
                'name' => 'Foo Fighters UK',
                'provider' => 'ticketmaster',
                'provider_params' => ['attraction_id' => 'K8vZ91713Y7', 'country_code' => 'GB'],
            ],
            [
                'name' => 'Bryan Adams UK',
                'provider' => 'ticketmaster',
                'provider_params' => ['attraction_id' => 'K8vZ9171ugV', 'country_code' => 'GB'],
            ],
        ];

        foreach ($interests as $interest) {
            Interest::firstOrCreate(
                ['name' => $interest['name']],
                ['provider' => $interest['provider'], 'provider_params' => $interest['provider_params'], 'status' => 'watching']
            );
        }
    }
}
