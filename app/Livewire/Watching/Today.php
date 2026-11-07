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
 * @property-read Collection<int, WatchEntry> $entries
 * @property-read Collection<int, WatchTitle> $historyResults
 * @property-read WatchTitle|null $selectedTitle
 */
#[Title('Watched today')]
class Today extends Component
{
    public const STEPS = ['tv', 'films', 'done'];

    /** How far back to look for shows/films to offer as one-tap picks. */
    public const TV_PICK_DAYS = 30;

    public const FILM_PICK_DAYS = 14;

    public string $date;

    #[Url]
    public string $step = 'tv';

    public string $search = '';

    public ?int $selectedTitleId = null;

    public int|string|null $season = null;

    public int|string|null $episode = null;

    public string $location = WatchEntry::HOME;

    public function mount(): void
    {
        $this->date = self::today();

        if (! in_array($this->step, self::STEPS, true)) {
            $this->step = 'tv';
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
        if (in_array($step, self::STEPS, true)) {
            $this->step = $step;
            $this->search = '';
            $this->cancelSelection();
        }
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
     * One-tap suggestions. For TV: shows watched recently, each with the
     * next episode filled in. For films: films watched in the last couple of
     * weeks but not yet on this date, for picking up one started earlier.
     *
     * @return list<array{title: WatchTitle, season: ?int, episode: ?int, last_watched: CarbonInterface}>
     */
    #[Computed]
    public function quickPicks(): array
    {
        $isTv = $this->mediaType() === WatchTitle::TV;
        $since = Carbon::parse($this->date)->subDays($isTv ? self::TV_PICK_DAYS : self::FILM_PICK_DAYS)->toDateString();

        $recent = WatchEntry::with('title')
            ->whereHas('title', fn ($q) => $q->where('media_type', $this->mediaType()))
            ->whereDate('watched_on', '>=', $since)
            ->whereDate('watched_on', $isTv ? '<=' : '<', $this->date)
            ->orderByDesc('watched_on')
            ->orderByDesc('season')
            ->orderByDesc('episode')
            ->orderByDesc('id')
            ->get()
            ->unique('watch_title_id');

        $loggedToday = $this->entries->pluck('watch_title_id')->all();
        $picks = [];

        foreach ($recent as $entry) {
            $title = $entry->title;

            if (! $isTv && in_array($title->id, $loggedToday, true)) {
                continue;
            }

            [$season, $episode] = $isTv ? $title->nextEpisodeAfter($entry->season, $entry->episode) : [null, null];

            $picks[] = [
                'title' => $title,
                'season' => $season,
                'episode' => $episode,
                'last_watched' => $entry->watched_on,
            ];

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

    public function chooseTitle(int $titleId, ?int $season = null, ?int $episode = null): void
    {
        $title = WatchTitle::find($titleId);

        if (! $title) {
            return;
        }

        // Shows gain new seasons, so refresh episode counts once a week.
        if ($title->isTv() && ($title->seasons === null || $title->updated_at?->lt(now()->subWeek()))) {
            $title = $this->importFromTmdb($title->media_type, $title->tmdb_id) ?? $title;
        }

        $this->select($title, $season, $episode);
    }

    public function chooseTmdb(int $tmdbId): void
    {
        $title = WatchTitle::where('media_type', $this->mediaType())->where('tmdb_id', $tmdbId)->first()
            ?? $this->importFromTmdb($this->mediaType(), $tmdbId);

        if ($title) {
            $this->select($title);
        } else {
            Flux::toast(variant: 'danger', text: __('Couldn\'t load that from TMDB. Try again in a moment.'));
        }
    }

    public function add(): void
    {
        $title = $this->selectedTitle;

        if (! $title) {
            return;
        }

        $this->validate([
            'season' => ['nullable', 'integer', 'min:0', 'max:999'],
            'episode' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'location' => ['required', 'in:'.WatchEntry::HOME.','.WatchEntry::CINEMA],
        ]);

        $this->date = $this->normalisedDate($this->date);

        WatchEntry::create([
            'watch_title_id' => $title->id,
            'watched_on' => $this->date,
            'season' => $title->isTv() ? $this->asInt($this->season) : null,
            'episode' => $title->isTv() ? $this->asInt($this->episode) : null,
            'location' => $title->isTv() ? null : $this->location,
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
        $this->season = null;
        $this->episode = null;
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

    protected function select(WatchTitle $title, ?int $season = null, ?int $episode = null): void
    {
        $this->selectedTitleId = $title->id;
        $this->location = WatchEntry::HOME;

        if ($title->isTv() && $season === null) {
            $last = $title->entries()
                ->whereDate('watched_on', '<=', $this->date)
                ->orderByDesc('watched_on')
                ->orderByDesc('season')
                ->orderByDesc('episode')
                ->orderByDesc('id')
                ->first();

            [$season, $episode] = $title->nextEpisodeAfter($last?->season, $last?->episode);
        }

        $this->season = $title->isTv() ? $season : null;
        $this->episode = $title->isTv() ? $episode : null;
        unset($this->selectedTitle);
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
        unset($this->entries, $this->quickPicks, $this->historyResults, $this->tmdbResults, $this->selectedTitle);
    }

    private function asInt(int|string|null $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
