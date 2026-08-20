<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interest_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interest_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response_body')->nullable();
            $table->string('outcome');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_checks');
    }
};
