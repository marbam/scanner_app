<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steam_games', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appid')->unique();
            $table->string('name');

            // From GetOwnedGames (refreshed on every library sync). Minutes.
            $table->unsignedInteger('playtime_forever')->default(0);
            $table->unsignedInteger('playtime_2weeks')->default(0);
            $table->timestamp('last_played_at')->nullable();

            // From the store/tag lookups (filled in by the details sync).
            $table->string('store_type')->nullable();
            $table->text('short_description')->nullable();
            $table->json('genres')->nullable();
            $table->json('categories')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('details_synced_at')->nullable();

            // The owner's own choices from the suggestions page.
            $table->boolean('never_suggest')->default(false);
            $table->timestamp('skipped_until')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steam_games');
    }
};
