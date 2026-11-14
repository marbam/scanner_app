<?php

namespace App\Livewire\Watching;

use App\Models\WatchEntry;
use App\Models\WatchTitle;
use App\Support\Tmdb;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * The daily "what did you watch?" flow: a TV step, then a films step, then
 * a summary. Backdate reuses all of this with a date picker on top.
 *
 * Shows are one tap: the "Recently watched" list toggles a show on or off for
 * the day, and picking one from search logs it (at most once per show per
 * day). Films open a short form to choose Home or Cinema.
 *
 * @property-read Collection<int, WatchEntry> $entries
 * @property-read Collection<int, WatchTitle> $historyResults
 * @property-read WatchTitle|null $selectedTitle
 */
#[Title('Watched today')]
class Today extends Component
{
    /** @var list<string> */
    public const STEPS = ['tv', 'films', 'done'];

    /** How far back the "Recently watched" show list looks. */
    public const RECENT_SHOW_DAYS = 15;

    /** How far back to look for films to offer again (one started earlier in the week). */
    public const FILM_PICK_DAYS = 14;

    public string $date;

    #[Url]
    public string $step = 'tv';

    public string $search = '';

    /** The film waiting for Home/Cinema to be chosen. */
    public ?int $selectedTitleId = null;

    public string $location = WatchEntry::HOME;

    public function mount(): void
    {
        $this->date = self::today();

        if (! in_array($this->step, static::STEPS, true)) {
            $this->step = static::STEPS[0];
        }
    }

    public static function today(): string
    {
        return now('Europe/London')->toDateString();
    }

    public function isBackdating(): bool
    {
        return false;
    }

    public function goTo(string $step): void
    {
        if (in_array($step, static::STEPS, true)) {
            $this->step = $step;
            $this->search = '';
            $this->cancelSelection();
        }
    }

    /** The step after the current one, or null on the last step. */
    public function nextStep(): ?string
    {
        $index = array_search($this->step, static::STEPS, true);

        return $index === false ? null : (static::STEPS[$index + 1] ?? null);
    }

    public function previousDay(): void
    {
        $this->setDate(Carbon::parse($this->date)->subDay()->toDateString());
    }

    public function nextDay(): void
    {
        $this->setDate(Carbon::parse($this->date)->addDay()->toDateString());
    }

    public function updatedDate(): void
    {
        $this->setDate($this->date);
    }

    protected function setDate(string $date): void
    {
        $this->date = $this->normalisedDate($date);
        $this->cancelSelection();
        $this->clearComputed();
    }

    /** Today's page is always today; backdating allows any valid past date. */
    protected function normalisedDate(string $date): string
    {
        $today = self::today();

        if (! $this->isBackdating() || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            return $today;
        }

        return min($date, $today); // no logging the future
    }

    /**
     * @return 'movie'|'tv'
     */
    public function mediaType(): string
    {
        return $this->step === 'films' ? WatchTitle::MOVIE : WatchTitle::TV;
    }

    /**
     * Everything logged on the current date.
     *
     * @return Collection<int, WatchEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        return WatchEntry::with('title')
            ->whereDate('watched_on', $this->date)
            ->orderBy('id')
            ->get();
    }

    /**
     * Every show watched in the last RECENT_SHOW_DAYS days (counted back from
     * this date, including it), once each, with whether it's logged on this
     * date. Tapping one toggles it.
     *
     * Ordered by when it was last watched *before* this date, so ticking a
     * show doesn't move it. Shows only watched on this date come first.
     *
     * @return list<array{title: WatchTitle, last_watched: ?CarbonInterface, logged: bool}>
     */
    #[Computed]
    public function recentShows(): array
    {
        $entries = WatchEntry::with('title')
            ->whereHas('title', fn ($q) => $q->where('media_type', WatchTitle::TV))
            ->whereDate('watched_on', '>=', Carbon::parse($this->date)->subDays(self::RECENT_SHOW_DAYS)->toDateString())
            ->get();

        $shows = [];

        foreach ($entries->groupBy('watch_title_id') as $titleEntries) {
            $onThisDate = $titleEntries->filter(fn (WatchEntry $e) => $e->watched_on->toDateString() === $this->date);
            $before = $titleEntries->diff($onThisDate)->sortByDesc(fn (WatchEntry $e) => $e->watched_on->toDateString())->first();

            $shows[] = [
                'title' => $titleEntries->first()->title,
                'last_watched' => $before?->watched_on,
                'logged' => $onThisDate->isNotEmpty(),
            ];
        }

        usort($shows, fn (array $a, array $b) => [$b['last_watched'] === null, $b['last_watched']?->toDateString(), $a['title']->title]
            <=> [$a['last_watched'] === null, $a['last_watched']?->toDateString(), $b['title']->title]);

        return $shows;
    }

    /**
     * Films watched in the last couple of weeks but not on this date, for
     * finishing one started earlier, most recent first.
     *
     * @return list<array{title: WatchTitle, last_watched: CarbonInterface}>
     */
    #[Computed]
    public function quickPicks(): array
    {
        if ($this->mediaType() === WatchTitle::TV) {
            return [];
        }

        $recent = WatchEntry::with('title')
            ->whereHas('title', fn ($q) => $q->where('media_type', WatchTitle::MOVIE))
            ->whereDate('watched_on', '>=', Carbon::parse($this->date)->subDays(self::FILM_PICK_DAYS)->toDateString())
            ->whereDate('watched_on', '<', $this->date)
            ->orderByDesc('watched_on')
            ->orderByDesc('id')
            ->get()
            ->unique('watch_title_id');

        $loggedToday = $this->entries->pluck('watch_title_id')->all();
        $picks = [];

        foreach ($recent as $entry) {
            if (in_array($entry->watch_title_id, $loggedToday, true)) {
                continue;
            }

            $picks[] = ['title' => $entry->title, 'last_watched' => $entry->watched_on];

            if (count($picks) === 8) {
                break;
            }
        }

        return $picks;
    }

    /**
     * Search matches from your own history (shown first).
     *
     * @return Collection<int, WatchTitle>
     */
    #[Computed]
    public function historyResults(): Collection
    {
        if (mb_strlen(trim($this->search)) < 2) {
            return new Collection;
        }

        return WatchTitle::query()
            ->where('media_type', $this->mediaType())
            ->where('title', 'like', '%'.trim($this->search).'%')
            ->whereHas('entries')
            ->withMax('entries', 'watched_on')
            ->withCount('entries')
            ->orderByDesc('entries_max_watched_on')
            ->limit(5)
            ->get();
    }

    /**
     * Search matches from TMDB, minus anything already in your history.
     *
     * @return array{results: list<array{tmdb_id: int, title: string, year: ?int, poster_path: ?string}>, error: bool}
     */
    #[Computed]
    public function tmdbResults(): array
    {
        if (mb_strlen(trim($this->search)) < 2 || ! Tmdb::configured()) {
            return ['results' => [], 'error' => false];
        }

        try {
            $results = app(Tmdb::class)->search($this->mediaType(), $this->search);
        } catch (Throwable $e) {
            Log::warning('TMDB search failed', ['error' => $e->getMessage()]);

            return ['results' => [], 'error' => true];
        }

        $known = $this->historyResults->pluck('tmdb_id')->all();

        return [
            'results' => array_slice(
                array_values(array_filter($results, fn (array $r) => ! in_array($r['tmdb_id'], $known, true))),
                0,
                8,
            ),
            'error' => false,
        ];
    }

    #[Computed]
    public function selectedTitle(): ?WatchTitle
    {
        return $this->selectedTitleId ? WatchTitle::find($this->selectedTitleId) : null;
    }

    public function chooseTitle(int $titleId): void
    {
        $title = WatchTitle::find($titleId);

        if ($title) {
            $this->choose($title);
        }
    }

    public function chooseTmdb(int $tmdbId): void
    {
        $title = WatchTitle::where('media_type', $this->mediaType())->where('tmdb_id', $tmdbId)->first()
            ?? $this->importFromTmdb($this->mediaType(), $tmdbId);

        if ($title) {
            $this->choose($title);
        } else {
            Flux::toast(variant: 'danger', text: __('Couldn\'t load that from TMDB. Try again in a moment.'));
        }
    }

    /** Ticks or unticks a show in the "Recently watched" list for this date. */
    public function toggleShow(int $titleId): void
    {
        $title = WatchTitle::where('media_type', WatchTitle::TV)->find($titleId);

        if (! $title) {
            return;
        }

        $this->date = $this->normalisedDate($this->date);
        $logged = $title->entries()->whereDate('watched_on', $this->date);

        if ($logged->exists()) {
            $logged->delete();
        } else {
            $this->logShow($title);
        }

        $this->clearComputed();
    }

    /** Saves the selected film with the chosen Home/Cinema location. */
    public function add(): void
    {
        $title = $this->selectedTitle;

        if (! $title || $title->isTv()) {
            return;
        }

        $this->validate([
            'location' => ['required', 'in:'.WatchEntry::HOME.','.WatchEntry::CINEMA],
        ]);

        $this->date = $this->normalisedDate($this->date);

        WatchEntry::create([
            'watch_title_id' => $title->id,
            'watched_on' => $this->date,
            'location' => $this->location,
        ]);

        $this->search = '';
        $this->cancelSelection();
        $this->clearComputed();
    }

    public function remove(int $entryId): void
    {
        WatchEntry::whereKey($entryId)->delete();
        $this->clearComputed();
    }

    public function cancelSelection(): void
    {
        $this->selectedTitleId = null;
        $this->location = WatchEntry::HOME;
        $this->resetValidation();
        unset($this->selectedTitle);
    }

    public function updatedSearch(): void
    {
        unset($this->historyResults, $this->tmdbResults);
    }

    public function render(): View
    {
        return view('livewire.watching.today');
    }

    /** Shows are logged straight away; films wait for Home/Cinema. */
    protected function choose(WatchTitle $title): void
    {
        if (! $title->isTv()) {
            $this->selectedTitleId = $title->id;
            $this->location = WatchEntry::HOME;
            unset($this->selectedTitle);

            return;
        }

        $this->date = $this->normalisedDate($this->date);
        $this->logShow($title);

        $this->search = '';
        $this->clearComputed();
    }

    /** Logs a show on this date, unless it's already logged (once per day is all that's tracked). */
    protected function logShow(WatchTitle $title): void
    {
        if (! $title->entries()->whereDate('watched_on', $this->date)->exists()) {
            WatchEntry::create([
                'watch_title_id' => $title->id,
                'watched_on' => $this->date,
                'location' => null,
            ]);
        }
    }

    protected function importFromTmdb(string $type, int $tmdbId): ?WatchTitle
    {
        if (! Tmdb::configured() || ! in_array($type, [WatchTitle::MOVIE, WatchTitle::TV], true)) {
            return null;
        }

        try {
            return app(Tmdb::class)->import($type, $tmdbId);
        } catch (Throwable $e) {
            Log::warning('TMDB import failed', ['type' => $type, 'tmdb_id' => $tmdbId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function clearComputed(): void
    {
        unset($this->entries, $this->recentShows, $this->quickPicks, $this->historyResults, $this->tmdbResults, $this->selectedTitle);
    }
}
