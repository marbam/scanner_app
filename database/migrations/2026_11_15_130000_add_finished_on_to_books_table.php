<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The day a book was (most recently) finished. A book read again after
        // this date counts as being re-read, until it's finished again.
        Schema::table('books', function (Blueprint $table) {
            $table->date('finished_on')->nullable()->after('cover_id');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('finished_on');
        });
    }
};
