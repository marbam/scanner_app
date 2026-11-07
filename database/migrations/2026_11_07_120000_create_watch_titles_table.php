<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A film or TV show, with its details copied from TMDB so pages
        // don't need to call TMDB every time they show it.
        Schema::create('watch_titles', function (Blueprint $table) {
            $table->id();
            $table->string('media_type', 10); // 'movie' or 'tv', same as TMDB's own naming
            $table->unsignedInteger('tmdb_id');
            $table->string('title');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('poster_path')->nullable();
            $table->unsignedSmallInteger('runtime')->nullable();
            $table->json('seasons')->nullable(); // TV only: {season number: episode count}
            $table->timestamps();

            $table->unique(['media_type', 'tmdb_id']);
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_titles');
    }
};
