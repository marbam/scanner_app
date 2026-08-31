<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_activity_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('completed')->default(false);
            $table->decimal('value', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['habit_activity_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_entries');
    }
};
