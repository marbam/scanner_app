<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per game per (UK) day it was played, built up by the daily
        // library sync. Steam has no play-history API, so this starts empty.
        Schema::create('steam_game_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('steam_game_id')->constrained()->cascadeOnDelete();
            $table->date('played_on');
            $table->unsignedInteger('minutes')->default(0);
            $table->timestamps();

            $table->unique(['steam_game_id', 'played_on']);
            $table->index('played_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steam_game_plays');
    }
};
