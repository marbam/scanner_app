<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shows are now logged once per show per day, with no season/episode, so
     * this drops the episode columns and the unused TMDB season counts.
     */
    public function up(): void
    {
        // Before episodes were dropped, a show could be logged several times
        // on one day (one row per episode). Keep the first row of each.
        $duplicates = DB::table('watch_entries as e')
            ->join('watch_titles as t', 't.id', '=', 'e.watch_title_id')
            ->where('t.media_type', 'tv')
            ->whereNotIn('e.id', fn ($q) => $q->from('watch_entries')
                ->selectRaw('MIN(id)')
                ->groupBy('watch_title_id', DB::raw('date(watched_on)')))
            ->pluck('e.id');

        DB::table('watch_entries')->whereIn('id', $duplicates)->delete();

        Schema::table('watch_entries', function (Blueprint $table) {
            $table->dropColumn(['season', 'episode']);
        });

        Schema::table('watch_titles', function (Blueprint $table) {
            $table->dropColumn('seasons');
        });
    }

    /**
     * Puts the columns back (empty). The deleted data isn't restored.
     */
    public function down(): void
    {
        Schema::table('watch_entries', function (Blueprint $table) {
            $table->unsignedSmallInteger('season')->nullable()->after('watched_on');
            $table->unsignedSmallInteger('episode')->nullable()->after('season');
        });

        Schema::table('watch_titles', function (Blueprint $table) {
            $table->json('seasons')->nullable()->after('runtime');
        });
    }
};
