<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('steam_games', function (Blueprint $table) {
            // Null = not fetched yet; total 0 = the game has no achievements.
            $table->unsignedInteger('achievements_total')->nullable()->after('tags');
            $table->unsignedInteger('achievements_unlocked')->nullable()->after('achievements_total');
            $table->timestamp('achievements_synced_at')->nullable()->after('achievements_unlocked');
        });
    }

    public function down(): void
    {
        Schema::table('steam_games', function (Blueprint $table) {
            $table->dropColumn(['achievements_total', 'achievements_unlocked', 'achievements_synced_at']);
        });
    }
};
