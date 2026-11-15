<?php

namespace App\Livewire\Review;

use App\Livewire\Watching\Today;
use App\Models\Book;
use App\Models\BookEntry;
use App\Models\HabitActivity;
use App\Models\HabitEntry;
use App\Models\SteamGamePlay;
use App\Models\WatchEntry;
use App\Models\WatchTitle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Year in review: TV, films, games, books and habits over either the year to
 * date or the last 365 days, all in UK dates.
 *
 * Each section says how many days of the period it has data for: from the
 * later of the period start and that tracker's first-ever record, to today.
 * A tracker started mid-year only covers part of the period, and averages
 * are worked out over the days it covers.
 *
 * @property-read array{start: string, end: string, days: int} $range
 * @property-read array{coverage: array{days: int, from: string|null}, shows: Collection<int, WatchTitle>, films: Collection<int, WatchTitle>, tv_days: int, cinema: int} $watching
 * @property-read array{coverage: array{days: int, from: string|null}, minutes: int, per_day: float, games: list<array{name: string, minutes: int, days: int, per_day: float}>} $games
 * @property-read array{coverage: array{days: int, from: string|null}, books: Collection<int, Book>, finished: Collection<int, Book>, days: int} $books
 * @property-read array{coverage: array{days: int, from: string|null}, logged_days: int, habits: list<array{activity: HabitActivity, days: int}>} $habits
 */
#[Title('Year in review')]
class Index extends Component
{
    public const PERIODS = ['ytd', '365'];

    #[Url]
    public string $period = 'ytd';

    public function mount(): void
    {
        if (! in_array($this->period, self::PERIODS, true)) {
            $this->period = 'ytd';
        }
    }

    public function setPeriod(string $period): void
    {
        if (in_array($period, self::PERIODS, true)) {
            $this->period = $period;
            unset($this->range, $this->watching, $this->games, $this->books, $this->habits);
        }
    }

    /**
     * The date range being reviewed, inclusive.
     *
     * @return array{start: string, end: string, days: int}
     */
    #[Computed]
    public function range(): array
    {
        $end = Carbon::parse(Today::today());
        $start = $this->period === '365' ? $end->copy()->subDays(364) : $end->copy()->startOfYear();

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'days' => (int) $start->diffInDays($end) + 1,
        ];
    }

    /**
     * @return array{coverage: array{days: int, from: string|null}, shows: Collection<int, WatchTitle>, films: Collection<int, WatchTitle>, tv_days: int, cinema: int}
     */
    #[Computed]
    public function watching(): array
    {
        $inPeriod = fn (Builder $q) => $this->whereInPeriod($q, 'watched_on');

        $titles = fn (string $type) => WatchTitle::query()
            ->where('media_type', $type)
            ->whereHas('entries', $inPeriod)
            ->withCount(['entries' => $inPeriod])
            ->orderByDesc('entries_count')
            ->orderBy('title')
            ->get();

        $entries = fn (string $type) => $this->whereInPeriod(WatchEntry::query(), 'watched_on')
            ->whereHas('title', fn (Builder $q) => $q->where('media_type', $type));

        return [
            'coverage' => $this->coverage(WatchEntry::min('watched_on')),
            'shows' => $titles(WatchTitle::TV),
            'films' => $titles(WatchTitle::MOVIE),
            'tv_days' => $entries(WatchTitle::TV)->distinct()->count('watched_on'),
            'cinema' => $entries(WatchTitle::MOVIE)->where('location', WatchEntry::CINEMA)->count(),
        ];
    }

    /**
     * @return array{coverage: array{days: int, from: string|null}, minutes: int, per_day: float, games: list<array{name: string, minutes: int, days: int, per_day: float}>}
     */
    #[Computed]
    public function games(): array
    {
        $coverage = $this->coverage(SteamGamePlay::min('played_on'));

        $plays = $this->whereInPeriod(SteamGamePlay::query(), 'played_on')
            ->with('game:id,name')
            ->get()
            ->groupBy('steam_game_id');

        $games = [];

        foreach ($plays as $gamePlays) {
            $minutes = (int) $gamePlays->sum('minutes');
            $days = $gamePlays->count();

            $games[] = [
                'name' => $gamePlays->first()?->game->name ?? __('Unknown game'),
                'minutes' => $minutes,
                'days' => $days,
                'per_day' => round($minutes / $days, 1),
            ];
        }

        usort($games, fn (array $a, array $b) => $b['minutes'] <=> $a['minutes'] ?: strcmp($a['name'], $b['name']));

        $total = array_sum(array_column($games, 'minutes'));

        return [
            'coverage' => $coverage,
            'minutes' => $total,
            'per_day' => $coverage['days'] > 0 ? round($total / $coverage['days'], 1) : 0.0,
            'games' => $games,
        ];
    }

    /**
     * @return array{coverage: array{days: int, from: string|null}, books: Collection<int, Book>, finished: Collection<int, Book>, days: int}
     */
    #[Computed]
    public function books(): array
    {
        $inPeriod = fn (Builder $q) => $this->whereInPeriod($q, 'read_on');

        return [
            'coverage' => $this->coverage(BookEntry::min('read_on')),
            'books' => Book::query()
                ->whereHas('entries', $inPeriod)
                ->withCount(['entries' => $inPeriod])
                ->withMax(['entries' => $inPeriod], 'read_on')
                ->orderByDesc('entries_max_read_on')
                ->orderBy('title')
                ->get(),
            'finished' => $this->whereInPeriod(Book::query()->finished(), 'finished_on')
                ->orderByDesc('finished_on')
                ->get(),
            'days' => $this->whereInPeriod(BookEntry::query(), 'read_on')->distinct()->count('read_on'),
        ];
    }

    /**
     * Days each habit was done. Active habits are always listed; archived
     * ones only if they were done in the period.
     *
     * @return array{coverage: array{days: int, from: string|null}, logged_days: int, habits: list<array{activity: HabitActivity, days: int}>}
     */
    #[Computed]
    public function habits(): array
    {
        $done = fn (Builder $q) => $this->whereInPeriod($q, 'date')->where('completed', true);

        $activities = HabitActivity::query()
            ->where(fn (Builder $q) => $q->whereNull('archived_at')->orWhereHas('entries', $done))
            ->withCount(['entries' => $done])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $habits = [];

        foreach ($activities as $activity) {
            $habits[] = ['activity' => $activity, 'days' => (int) $activity->getAttribute('entries_count')];
        }

        return [
            'coverage' => $this->coverage(HabitEntry::min('date')),
            // Days with anything logged, ticked or not.
            'logged_days' => $this->whereInPeriod(HabitEntry::query(), 'date')->distinct()->count('date'),
            'habits' => $habits,
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function whereInPeriod(Builder $query, string $column): Builder
    {
        return $query->whereDate($column, '>=', $this->range['start'])->whereDate($column, '<=', $this->range['end']);
    }

    /**
     * How many days of the period a tracker has data for, given its first-ever
     * record: from the later of that and the period start, to today.
     *
     * @return array{days: int, from: string|null}
     */
    protected function coverage(mixed $firstEver): array
    {
        if ($firstEver === null) {
            return ['days' => 0, 'from' => null];
        }

        $from = max(substr((string) $firstEver, 0, 10), $this->range['start']);

        if ($from > $this->range['end']) {
            return ['days' => 0, 'from' => null];
        }

        return [
            'days' => (int) Carbon::parse($from)->diffInDays(Carbon::parse($this->range['end'])) + 1,
            'from' => $from,
        ];
    }
}
