<?php

namespace App\Livewire\Watching;

use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;

/**
 * Same flow as Today, but for an earlier date (defaults to yesterday).
 */
#[Title('Backdate watching')]
class Backdate extends Today
{
    public function mount(): void
    {
        parent::mount();

        $this->date = Carbon::parse(self::today())->subDay()->toDateString();
    }

    public function isBackdating(): bool
    {
        return true;
    }
}
