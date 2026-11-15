<?php

namespace App\Models;

use App\Support\OpenLibrary;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A book, picked from Open Library or typed in by hand (no open_library_key).
 *
 * @property int $id
 * @property string|null $open_library_key
 * @property string $title
 * @property string|null $author
 * @property int|null $year
 * @property int|null $cover_id
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
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'cover_id' => 'integer',
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
     * @param  'S'|'M'|'L'  $size
     */
    public function coverUrl(string $size = 'S'): ?string
    {
        return OpenLibrary::coverUrl($this->cover_id, $size);
    }
}
