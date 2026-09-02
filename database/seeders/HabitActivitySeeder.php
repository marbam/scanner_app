<?php

namespace Database\Seeders;

use App\Models\HabitActivity;
use Illuminate\Database\Seeder;

class HabitActivitySeeder extends Seeder
{
    /**
     * Seed a couple of starter habit activities. Idempotent — matched on
     * `name`, so existing rows (and their color/weight/history) are left
     * alone; only missing ones get created. Safe to re-run in production
     * via `php artisan db:seed --class=HabitActivitySeeder`.
     */
    public function run(): void
    {
        $activities = [
            ['name' => 'Reading', 'color' => '#22c55e', 'weight' => 1, 'sort_order' => 1],
            ['name' => 'Running', 'color' => '#3b82f6', 'weight' => 2, 'sort_order' => 2],
            ['name' => 'Drank Enough Water', 'color' => '#06b6d4', 'weight' => 1, 'sort_order' => 3],
            ['name' => 'Did Daily Steps', 'color' => '#f97316', 'weight' => 1, 'sort_order' => 4],
        ];

        foreach ($activities as $activity) {
            HabitActivity::firstOrCreate(
                ['name' => $activity['name']],
                ['color' => $activity['color'], 'weight' => $activity['weight'], 'sort_order' => $activity['sort_order']]
            );
        }
    }
}
