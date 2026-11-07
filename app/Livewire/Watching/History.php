<?php

namespace App\Livewire\Watching;

use App\Models\WatchEntry;
use App\Models\WatchTitle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything you've watched, one row per title with the dates you watched it.
 */
#[Title('Watch history')]
class History extends Component
{
    use WithPagination;

    public const TYPES = ['all', WatchTitle::MOVIE, WatchTitle::TV];

    #[Url]
    public string $search = '';

    #[Url]
    public string $type = 'all';

    /** A year like "2026", or "" for all time. */
    #[Url]
    public string $year = '';

    public function mount(): void
    {
        if (! in_array($this->type, self::TYPES, true)) {
            $this->type = 'all';
        }

        if ($this->year !== '' && ! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = '';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'type', 'year'], true)) {
            $this->resetPage();
            unset($this->titles, $this->stats);
        }
    }

    /**
     * @return LengthAwarePaginator<int, WatchTitle>
     */
    #[Computed]
    public function titles(): LengthAwarePaginator
    {
        // Used both as a whereHas() constraint (a Builder) and to filter the
        // eager-loaded entries (a HasMany relation), so it isn't type-hinted.
        $inYear = fn ($q) => $q->when($this->year !== '', fn ($q) => $q->whereYear('watched_on', (int) $this->year));

        return WatchTitle::query()
            ->whereHas('entries', $inYear)
            ->when($this->type !== 'all', fn (Builder $q) => $q->where('media_type', $this->type))
            ->when(trim($this->search) !== '', fn (Builder $q) => $q->where('title', 'like', '%'.trim($this->search).'%'))
            ->with(['entries' => fn ($q) => $inYear($q)->orderByDesc('watched_on')->orderByDesc('season')->orderByDesc('episode')])
            ->withMax(['entries' => $inYear], 'watched_on')
            ->orderByDesc('entries_max_watched_on')
            ->orderBy('title')
            ->paginate(20);
    }

    /**
     * Totals for the chosen year (or all time). Films count each title once,
     * however many times it was watched.
     *
     * @return array{films: int, cinema: int, shows: int, episodes: int}
     */
    #[Computed]
    public function stats(): array
    {
        $entries = fn (string $type) => WatchEntry::query()
            ->whereHas('title', fn (Builder $q) => $q->where('media_type', $type))
            ->when($this->year !== '', fn (Builder $q) => $q->whereYear('watched_on', (int) $this->year));

        return [
            'films' => $entries(WatchTitle::MOVIE)->distinct()->count('watch_title_id'),
            'cinema' => $entries(WatchTitle::MOVIE)->where('location', WatchEntry::CINEMA)->count(),
            'shows' => $entries(WatchTitle::TV)->distinct()->count('watch_title_id'),
            'episodes' => $entries(WatchTitle::TV)->count(),
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
        $first = WatchEntry::min('watched_on');

        if ($first === null) {
            return [];
        }

        $from = (int) substr((string) $first, 0, 4);
        $to = max($from, (int) now('Europe/London')->format('Y'));

        return range($to, $from);
    }
}
