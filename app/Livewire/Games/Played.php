<?php

namespace App\Livewire\Games;

use App\Models\SteamGamePlay;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Played')]
class Played extends Component
{
    /** How many days back to show. */
    public const RANGES = [7, 30, 90, 365];

    #[Url]
    public int $days = 30;

    public function mount(): void
    {
        if (! in_array($this->days, self::RANGES, true)) {
            $this->days = 30;
        }
    }

    public function setDays(int $days): void
    {
        if (in_array($days, self::RANGES, true)) {
            $this->days = $days;
            unset($this->playsByDay);
        }
    }

    /**
     * Every recorded play in range, grouped by date (newest first), with each
     * day's games ordered by most minutes played.
     *
     * @return Collection<int|string, EloquentCollection<int, SteamGamePlay>>
     */
    #[Computed]
    public function playsByDay(): Collection
    {
        $since = now('Europe/London')->subDays($this->days - 1)->toDateString();

        return SteamGamePlay::with('game:id,appid,name')
            ->whereDate('played_on', '>=', $since)
            ->orderByDesc('played_on')
            ->orderByDesc('minutes')
            ->get()
            ->groupBy(fn (SteamGamePlay $play) => $play->played_on->toDateString())
            ->toBase();
    }

    public static function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? "{$hours}h" : "{$hours}h {$rest}m";
    }
}
