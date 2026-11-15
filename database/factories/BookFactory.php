<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'open_library_key' => '/works/OL'.fake()->unique()->numberBetween(1, 99_999_999).'W',
            'title' => fake()->words(3, true),
            'author' => fake()->name(),
            'year' => fake()->numberBetween(1950, 2026),
            'cover_id' => null,
        ];
    }

    /** Typed in by hand rather than picked from Open Library. */
    public function typed(): static
    {
        return $this->state(fn () => ['open_library_key' => null, 'author' => null, 'year' => null]);
    }
}
