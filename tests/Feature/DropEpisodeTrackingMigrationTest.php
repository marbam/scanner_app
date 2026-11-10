<?php

use App\Models\WatchTitle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('dropping episode tracking keeps one row per show per day and removes the columns', function () {
    $migration = require database_path('migrations/2026_11_10_120000_drop_episode_tracking_from_watch_tables.php');

    // Recreate the old columns, as they were before this migration ran.
    $migration->down();

    $show = WatchTitle::factory()->tv()->create();
    $film = WatchTitle::factory()->create();

    $row = fn (int $titleId, string $date, ?int $episode) => DB::table('watch_entries')->insertGetId([
        'watch_title_id' => $titleId,
        'watched_on' => "{$date} 00:00:00",
        'season' => $episode ? 1 : null,
        'episode' => $episode,
        'location' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $kept = $row($show->id, '2026-11-08', 3);
    $row($show->id, '2026-11-08', 4); // second episode the same day: merged away
    $nextDay = $row($show->id, '2026-11-09', 5);
    $filmFirst = $row($film->id, '2026-11-08', null);
    $filmAgain = $row($film->id, '2026-11-08', null); // films can be logged twice, so left alone

    $migration->up();

    expect(DB::table('watch_entries')->orderBy('id')->pluck('id')->all())->toBe([$kept, $nextDay, $filmFirst, $filmAgain])
        ->and(Schema::hasColumns('watch_entries', ['season']))->toBeFalse()
        ->and(Schema::hasColumns('watch_entries', ['episode']))->toBeFalse()
        ->and(Schema::hasColumns('watch_titles', ['seasons']))->toBeFalse();
});
