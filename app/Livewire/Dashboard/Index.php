<?php

namespace App\Livewire\Dashboard;

use App\Models\Alert;
use App\Models\FacebookPost;
use App\Models\PlanningApplication;
use App\Models\Tweet;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Index extends Component
{
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
