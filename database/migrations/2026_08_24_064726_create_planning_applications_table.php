<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planning_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('address');
            $table->text('proposal')->nullable();
            $table->string('status')->nullable();
            $table->string('decision')->nullable();
            $table->timestamp('decision_date')->nullable();
            $table->boolean('viewed')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_applications');
    }
};
