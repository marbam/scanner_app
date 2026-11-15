<?php

namespace App\Livewire\Dashboard;

use App\Livewire\Watching\Today;
use App\Models\Alert;
use App\Models\DayHighlight;
use App\Models\FacebookPost;
use App\Models\PlanningApplication;
use App\Models\Tweet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Index extends Component
{
    /**
     * Highlights from a week ago, a month ago, and this day in earlier years,
     * newest first. Dates are UK dates, the same as Your Day writes them.
     *
     * `when` says which of those it is, for the view's label.
     *
     * @return list<array{when: 'week'|'month'|'years', years_ago: int, highlight: DayHighlight}>
     */
    #[Computed]
    public function highlightMemories(): array
    {
        $today = Carbon::parse(Today::today());
        $weekAgo = $today->copy()->subWeek()->toDateString();
        $monthAgo = $today->copy()->subMonthNoOverflow()->toDateString();

        $highlights = DayHighlight::query()
            ->where(fn (Builder $query) => $query
                ->whereDate('date', $weekAgo)
                ->orWhereDate('date', $monthAgo)
                ->orWhere(fn (Builder $query) => $query
                    ->whereMonth('date', $today->month)
                    ->whereDay('date', $today->day)
                    ->whereDate('date', '<', $today->toDateString())))
            ->orderByDesc('date')
            ->get();

        $memories = [];

        foreach ($highlights as $highlight) {
            $date = $highlight->date->toDateString();

            $memories[] = [
                'when' => match ($date) {
                    $weekAgo => 'week',
                    $monthAgo => 'month',
                    default => 'years',
                },
                'years_ago' => $today->year - $highlight->date->year,
                'highlight' => $highlight,
            ];
        }

        return $memories;
    }

    /**
     * @return array<int, array<int, FacebookPost>>
     */
    #[Computed]
    public function facebookMemoriesByYear(): array
    {
        $posts = FacebookPost::query()
            ->whereMonth('posted_at', now()->month)
            ->whereDay('posted_at', now()->day)
            ->orderByDesc('posted_at')
            ->get();

        $byYear = [];

        foreach ($posts as $post) {
            $byYear[$post->posted_at->year][] = $post;
        }

        krsort($byYear);

        return $byYear;
    }

    /**
     * @return array<int, array<int, Tweet>>
     */
    #[Computed]
    public function twitterMemoriesByYear(): array
    {
        $tweets = Tweet::query()
            ->whereMonth('posted_at', now()->month)
            ->whereDay('posted_at', now()->day)
            ->orderByDesc('posted_at')
            ->get();

        $byYear = [];

        foreach ($tweets as $tweet) {
            $byYear[$tweet->posted_at->year][] = $tweet;
        }

        krsort($byYear);

        return $byYear;
    }

    /**
     * @return Collection<int, PlanningApplication>
     */
    #[Computed]
    public function recentAdverts(): Collection
    {
        return PlanningApplication::query()
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Alert>
     */
    #[Computed]
    public function recentAlerts(): Collection
    {
        return Alert::query()
            ->with('interest')
            ->where('detected_at', '>=', now()->subDay())
            ->orderByDesc('detected_at')
            ->get();
    }
}
