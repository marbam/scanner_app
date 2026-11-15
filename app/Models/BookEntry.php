<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\BookEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reading a book on a given day (at most once per book per day).
 *
 * @property int $book_id
 * @property CarbonInterface $read_on
 * @property-read Book $book The foreign key is required and cascades on delete, so this always exists.
 */
class BookEntry extends Model
{
    /** @use HasFactory<BookEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'book_id',
        'read_on',
    ];

    protected function casts(): array
    {
        return [
            'read_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
