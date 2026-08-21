<?php

namespace App\Livewire\Interests;

use App\Models\Interest;
use App\Models\InterestCheck;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Scans')]
class Index extends Component
{
    /**
     * @return Collection<int, Interest>
     */
    #[Computed]
    public function interests(): Collection
    {
        return Interest::query()
            ->withCount('checks')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, InterestCheck>
     */
    #[Computed]
    public function recentChecks(): Collection
    {
        return InterestCheck::query()
            ->with('interest')
            ->latest()
            ->limit(25)
            ->get();
    }

    public function toggleEnabled(int $interestId): void
    {
        $interest = Interest::findOrFail($interestId);

        $interest->update(['enabled' => ! $interest->enabled]);

        unset($this->interests);
    }
}
