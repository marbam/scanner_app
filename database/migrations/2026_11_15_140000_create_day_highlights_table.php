<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The highlight of a day, written on Your Day. At most one per date.
        Schema::create('day_highlights', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('day_highlights');
    }
};
