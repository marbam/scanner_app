<?php

namespace Database\Factories;

use App\Models\WatchEntry;
use App\Models\WatchTitle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchEntry>
 */
class WatchEntryFactory extends Factory
{
    protected $model = WatchEntry::class;

    public function definition(): array
    {
        return [
            'watch_title_id' => WatchTitle::factory(),
            'watched_on' => now('Europe/London')->toDateString(),
            'location' => WatchEntry::HOME,
        ];
    }
}
