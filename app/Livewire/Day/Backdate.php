<?php

namespace App\Livewire\Day;

use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Your Day for an earlier date (defaults to yesterday): the same habits, TV,
 * films, books and highlight steps, with a date picker. The date is in the
 * URL (`?date=2026-11-01`), so other pages can link to a given day. Future
 * and invalid dates are refused by Watching\Today::normalisedDate().
 */
#[Title('Backdate your day')]
class Backdate extends Index
{
    #[Url]
    public string $date;

    public function mount(): void
    {
        // #[Url] fills $date from the query string before mount() runs, and
        // the parent's mount() resets it to today, so note it first.
        $requested = isset($this->date) && $this->date !== '' ? $this->date : null;

        parent::mount();

        $this->setDate($requested ?? Carbon::parse(self::today())->subDay()->toDateString());
    }

    public function isBackdating(): bool
    {
        return true;
    }
}
