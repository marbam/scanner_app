<?php

namespace App\Models;

use App\Support\OpenLibrary;
use Carbon\CarbonInterface;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A book, picked from Open Library or typed in by hand (no open_library_key).
 *
 * A book is finished when it has a `finished_on` date and hasn't been read
 * since. Reading it again after that counts as a re-read (back to reading)
 * until it's finished again, which moves `finished_on` on.
 *
 * @property int $id
 * @property string|null $open_library_key
 * @property string $title
 * @property string|null $author
 * @property int|null $year
 * @property int|null $cover_id
 * @property CarbonInterface|null $finished_on
 */
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    protected $fillable = [
        'open_library_key',
        'title',
        'author',
        'year',
        'cover_id',
        'finished_on',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'cover_id' => 'integer',
            'finished_on' => 'date',
        ];
    }

    /**
     * @return HasMany<BookEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(BookEntry::class);
    }

    /**
     * Has a finish date and no reading logged after it.
     *
     * @param  Builder<Book>  $query
     * @return Builder<Book>
     */
    public function scopeFinished(Builder $query): Builder
    {
        return $query->whereNotNull('finished_on')
            ->whereDoesntHave('entries', fn (Builder $q) => $q->whereColumn('book_entries.read_on', '>', 'books.finished_on'));
    }

    /**
     * Never finished, or read again since it was.
     *
     * @param  Builder<Book>  $query
     * @return Builder<Book>
     */
    public function scopeReading(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('finished_on')
            ->orWhereHas('entries', fn (Builder $q) => $q->whereColumn('book_entries.read_on', '>', 'books.finished_on')));
    }

    /**
     * Whether it's finished given when it was last read. Pass the last read
     * date if you have it (e.g. from a withMax), otherwise it's looked up.
     */
    public function isFinished(?string $lastReadOn = null): bool
    {
        if ($this->finished_on === null) {
            return false;
        }

        $lastReadOn ??= $this->entries()->max('read_on');

        return $lastReadOn === null || substr($lastReadOn, 0, 10) <= $this->finished_on->toDateString();
    }

    /**
     * @param  'S'|'M'|'L'  $size
     */
    public function coverUrl(string $size = 'S'): ?string
    {
        return OpenLibrary::coverUrl($this->cover_id, $size);
    }
}
