<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookEntry>
 */
class BookEntryFactory extends Factory
{
    protected $model = BookEntry::class;

    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'read_on' => now('Europe/London')->toDateString(),
        ];
    }
}
