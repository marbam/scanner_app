<?php

namespace App\Livewire\Day;

use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;

/**
 * Your Day for an earlier date (defaults to yesterday): the same habits, TV,
 * films and books steps, with a date picker. Future dates are refused by
 * Watching\Today::normalisedDate().
 */
#[Title('Backdate your day')]
class Backdate extends Index
{
    public function mount(): void
    {
        parent::mount();

        $this->setDate(Carbon::parse(self::today())->subDay()->toDateString());
    }

    public function isBackdating(): bool
    {
        return true;
    }
}
