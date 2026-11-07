<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per viewing per day. A film watched over two evenings is
        // simply two rows; count distinct titles for "films watched this year".
        Schema::create('watch_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watch_title_id')->constrained()->cascadeOnDelete();
            $table->date('watched_on');
            $table->unsignedSmallInteger('season')->nullable();
            $table->unsignedSmallInteger('episode')->nullable();
            $table->string('location', 10)->nullable(); // films only: 'home' or 'cinema'
            $table->timestamps();

            $table->index('watched_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_entries');
    }
};
