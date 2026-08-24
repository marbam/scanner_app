<?php

namespace App\Livewire\Facebook\Memories;

use App\Models\FacebookPost;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Facebook: Memories')]
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

        unset($this->postsByYear);
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();

        unset($this->postsByYear);
    }

    public function today(): void
    {
        $this->date = now()->toDateString();

        unset($this->postsByYear);
    }

    /**
     * @return array<int, array<int, FacebookPost>>
     */
    #[Computed]
    public function postsByYear(): array
    {
        $day = Carbon::parse($this->date);

        $posts = FacebookPost::query()
            ->whereMonth('posted_at', $day->month)
            ->whereDay('posted_at', $day->day)
            ->orderByDesc('posted_at')
            ->get();

        $byYear = [];

        foreach ($posts as $post) {
            $byYear[$post->posted_at->year][] = $post;
        }

        krsort($byYear);

        return $byYear;
    }
}
