<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A book, either picked from Open Library (details copied in) or
        // typed in by hand (open_library_key null).
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('open_library_key')->nullable()->unique(); // e.g. /works/OL21745884W
            $table->string('title');
            $table->string('author')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('cover_id')->nullable(); // Open Library cover id
            $table->timestamps();

            $table->index('title');
        });

        // Reading a book on a given day: at most once per book per day.
        Schema::create('book_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->date('read_on');
            $table->timestamps();

            $table->unique(['book_id', 'read_on']);
            $table->index('read_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_entries');
        Schema::dropIfExists('books');
    }
};
