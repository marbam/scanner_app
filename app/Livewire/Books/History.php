<?php

namespace App\Livewire\Books;

use App\Models\Book;
use App\Models\BookEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every book you've logged, most recently read first, with how many days you
 * read it, over what span, and whether it's finished.
 */
#[Title('Reading history')]
class History extends Component
{
    use WithPagination;

    public const STATUSES = ['all', 'reading', 'finished'];

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = 'all';

    /** A year like "2026", or "" for all time. */
    #[Url]
    public string $year = '';

    public function mount(): void
    {
        if (! in_array($this->status, self::STATUSES, true)) {
            $this->status = 'all';
        }

        if ($this->year !== '' && ! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = '';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'year'], true)) {
            $this->resetPage();
            unset($this->books, $this->stats);
        }
    }

    /**
     * @return LengthAwarePaginator<int, Book>
     */
    #[Computed]
    public function books(): LengthAwarePaginator
    {
        // Used both as a whereHas() constraint and on the aggregate subqueries, so not type-hinted.
        $inYear = fn ($q) => $q->when($this->year !== '', fn ($q) => $q->whereYear('read_on', (int) $this->year));
        $term = '%'.trim($this->search).'%';

        return Book::query()
            ->whereHas('entries', $inYear)
            ->when($this->status === 'reading', fn (Builder $q) => $q->reading())
            ->when($this->status === 'finished', fn (Builder $q) => $q->finished())
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('title', 'like', $term)->orWhere('author', 'like', $term)))
            ->withCount(['entries' => $inYear])
            ->withMin(['entries' => $inYear], 'read_on')
            ->withMax(['entries' => $inYear], 'read_on')
            ->withMax('entries as last_read_on', 'read_on') // all time, for finished/reading
            ->orderByDesc('entries_max_read_on')
            ->orderBy('title')
            ->paginate(20);
    }

    /**
     * Totals for the chosen year (or all time).
     *
     * @return array{books: int, finished: int, days: int}
     */
    #[Computed]
    public function stats(): array
    {
        $entries = BookEntry::query()->when($this->year !== '', fn (Builder $q) => $q->whereYear('read_on', (int) $this->year));

        return [
            'books' => (clone $entries)->distinct()->count('book_id'),
            'finished' => Book::query()->finished()
                ->when($this->year !== '', fn (Builder $q) => $q->whereYear('finished_on', (int) $this->year))
                ->count(),
            'days' => (clone $entries)->distinct()->count('read_on'),
        ];
    }

    /**
     * Every year that has at least one entry, newest first.
     *
     * @return list<int>
     */
    #[Computed]
    public function years(): array
    {
        $first = BookEntry::min('read_on');

        if ($first === null) {
            return [];
        }

        $from = (int) substr((string) $first, 0, 4);
        $to = max($from, (int) now('Europe/London')->format('Y'));

        return range($to, $from);
    }
}
