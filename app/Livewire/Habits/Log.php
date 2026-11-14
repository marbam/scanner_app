<?php

namespace App\Livewire\Habits;

use App\Livewire\Concerns\LogsHabits;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Log habits')]
class Log extends Component
{
    use LogsHabits;

    public string $date;

    public function mount(?string $date = null): void
    {
        $this->date = $date ?? now()->toDateString();
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
        unset($this->habitEntries);
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
        unset($this->habitEntries);
    }

    public function updatedDate(): void
    {
        unset($this->habitEntries);
    }
}
