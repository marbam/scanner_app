<?php

namespace App\Livewire\Concerns;

use App\Models\Book;
use App\Models\BookEntry;
use App\Support\OpenLibrary;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * The books step of Your Day: tick off the books read on `$this->date`, the
 * same one-tap way as TV shows. Searches with the component's `$search`:
 * your own books first, then Open Library, or add the title as typed.
 *
 * Expects the using component to provide `$date`, `$search` and
 * `normalisedDate()` (Watching\Today does).
 *
 * @property-read Collection<int, BookEntry> $bookEntries
 * @property-read Collection<int, Book> $bookHistoryResults
 * @property-read list<array{book: Book, last_read: ?CarbonInterface, logged: bool, finished: bool}> $recentBooks
 * @property-read array{results: list<array{key: string, title: string, author: ?string, year: ?int, cover_id: ?int}>, error: bool} $openLibraryResults
 */
trait LogsBooks
{
    /** How far back the "Recently read" list looks. Books take a while, so longer than TV. */
    public const RECENT_BOOK_DAYS = 30;

    /**
     * Books read on this date.
     *
     * @return Collection<int, BookEntry>
     */
    #[Computed]
    public function bookEntries(): Collection
    {
        return BookEntry::with('book')->whereDate('read_on', $this->date)->orderBy('id')->get();
    }

    /**
     * Every book read in the last RECENT_BOOK_DAYS days (counted back from this
     * date, including it), once each, ticked if read on this date. Ordered by
     * the last read *before* this date, so ticking doesn't reorder it; books
     * only read on this date come first.
     *
     * Books finished before this date (and not read since) drop out.
     *
     * @return list<array{book: Book, last_read: ?CarbonInterface, logged: bool, finished: bool}>
     */
    #[Computed]
    public function recentBooks(): array
    {
        $entries = BookEntry::with('book')
            ->whereDate('read_on', '>=', Carbon::parse($this->date)->subDays(self::RECENT_BOOK_DAYS)->toDateString())
            ->whereDate('read_on', '<=', $this->date)
            ->get();

        $books = [];

        foreach ($entries->groupBy('book_id') as $bookEntries) {
            $book = $bookEntries->first()->book;
            $finishedOn = $book->finished_on?->toDateString();
            $lastRead = $bookEntries->max(fn (BookEntry $e) => $e->read_on->toDateString());

            // Finished before this date and not picked up again since: done with.
            // Finished *on* this date stays, so it can be undone.
            if ($finishedOn !== null && $finishedOn < $this->date && $lastRead <= $finishedOn) {
                continue;
            }

            $onThisDate = $bookEntries->filter(fn (BookEntry $e) => $e->read_on->toDateString() === $this->date);
            $before = $bookEntries->diff($onThisDate)->sortByDesc(fn (BookEntry $e) => $e->read_on->toDateString())->first();

            $books[] = [
                'book' => $book,
                'last_read' => $before?->read_on,
                'logged' => $onThisDate->isNotEmpty(),
                'finished' => $finishedOn === $this->date,
            ];
        }

        usort($books, fn (array $a, array $b) => [$b['last_read'] === null, $b['last_read']?->toDateString(), $a['book']->title]
            <=> [$a['last_read'] === null, $a['last_read']?->toDateString(), $b['book']->title]);

        return $books;
    }

    /**
     * Search matches from books you've logged before (shown first).
     *
     * @return Collection<int, Book>
     */
    #[Computed]
    public function bookHistoryResults(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return new Collection;
        }

        $term = '%'.trim($this->search).'%';

        return Book::query()
            ->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('author', 'like', $term))
            ->whereHas('entries')
            ->withMax('entries', 'read_on')
            ->withCount('entries')
            ->orderByDesc('entries_max_read_on')
            ->limit(5)
            ->get();
    }

    /**
     * Search matches from Open Library, minus books already in your history.
     *
     * @return array{results: list<array{key: string, title: string, author: ?string, year: ?int, cover_id: ?int}>, error: bool}
     */
    #[Computed]
    public function openLibraryResults(): array
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return ['results' => [], 'error' => false];
        }

        try {
            $results = app(OpenLibrary::class)->search($this->search);
        } catch (Throwable $e) {
            Log::warning('Open Library search failed', ['error' => $e->getMessage()]);

            return ['results' => [], 'error' => true];
        }

        $known = $this->bookHistoryResults->pluck('open_library_key')->filter()->all();

        return [
            'results' => array_slice(
                array_values(array_filter($results, fn (array $r) => ! in_array($r['key'], $known, true))),
                0,
                8,
            ),
            'error' => false,
        ];
    }

    /** Ticks or unticks a book in the "Recently read" list for this date. */
    public function toggleBook(int $bookId): void
    {
        $book = Book::find($bookId);

        if (! $book) {
            return;
        }

        $this->date = $this->normalisedDate($this->date);
        $logged = $book->entries()->whereDate('read_on', $this->date);

        if ($logged->exists()) {
            $logged->delete();
        } else {
            $this->logBook($book);
        }

        $this->clearBookComputed();
    }

    /**
     * Marks a book finished on this date (which also logs it as read today),
     * or undoes that if it was already finished on this date.
     */
    public function toggleFinished(int $bookId): void
    {
        $book = Book::find($bookId);

        if (! $book) {
            return;
        }

        $this->date = $this->normalisedDate($this->date);

        if ($book->finished_on?->toDateString() === $this->date) {
            $book->finished_on = null;
        } else {
            $book->finished_on = Carbon::parse($this->date);
            $this->logBook($book);
        }

        $book->save();
        $this->clearBookComputed();
    }

    /** Logs a book you've read before, from search. */
    public function chooseBook(int $bookId): void
    {
        $book = Book::find($bookId);

        if ($book) {
            $this->logBookAndReset($book);
        }
    }

    /** Logs a book picked from the Open Library results for the current search. */
    public function chooseOpenLibraryBook(string $key): void
    {
        $result = null;

        foreach ($this->openLibraryResults['results'] as $candidate) {
            if ($candidate['key'] === $key) {
                $result = $candidate;
                break;
            }
        }

        if ($result === null) {
            Flux::toast(variant: 'danger', text: __('Couldn\'t find that book. Try searching again.'));

            return;
        }

        $book = Book::updateOrCreate(['open_library_key' => $result['key']], [
            'title' => $result['title'],
            'author' => $result['author'],
            'year' => $result['year'],
            'cover_id' => $result['cover_id'],
        ]);

        $this->logBookAndReset($book);
    }

    /** Logs the search text as a book title, for anything Open Library doesn't have. */
    public function addTypedBook(): void
    {
        $title = mb_substr(trim(preg_replace('/\s+/', ' ', $this->search) ?? ''), 0, 255);

        if (mb_strlen($title) < 2) {
            return;
        }

        $book = Book::query()
            ->whereNull('open_library_key')
            ->whereRaw('lower(title) = ?', [mb_strtolower($title)])
            ->first() ?? Book::create(['title' => $title]);

        $this->logBookAndReset($book);
    }

    protected function logBookAndReset(Book $book): void
    {
        $this->date = $this->normalisedDate($this->date);
        $this->logBook($book);

        $this->search = '';
        $this->clearBookComputed();
    }

    /** Logs a book on this date, unless it's already logged. */
    protected function logBook(Book $book): void
    {
        if (! $book->entries()->whereDate('read_on', $this->date)->exists()) {
            BookEntry::create(['book_id' => $book->id, 'read_on' => $this->date]);
        }
    }

    protected function clearBookComputed(): void
    {
        unset($this->bookEntries, $this->recentBooks, $this->bookHistoryResults, $this->openLibraryResults);
    }
}
