<?php

namespace App\Livewire\Twitter\Memories;

use App\Models\Tweet;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Twitter: Memories')]
class Index extends Component
{
    #[Url]
    public string $date = '';

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = now()->toDateString();
        }
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();

        unset($this->tweetsByYear);
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();

        unset($this->tweetsByYear);
    }

    public function today(): void
    {
        $this->date = now()->toDateString();

        unset($this->tweetsByYear);
    }

    /**
     * @return array<int, array<int, Tweet>>
     */
    #[Computed]
    public function tweetsByYear(): array
    {
        $day = Carbon::parse($this->date);

        $tweets = Tweet::query()
            ->whereMonth('posted_at', $day->month)
            ->whereDay('posted_at', $day->day)
            ->orderByDesc('posted_at')
            ->get();

        $byYear = [];

        foreach ($tweets as $tweet) {
            $byYear[$tweet->posted_at->year][] = $tweet;
        }

        krsort($byYear);

        return $byYear;
    }
}
