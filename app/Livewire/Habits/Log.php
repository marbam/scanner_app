<?php

namespace App\Livewire\Habits;

use App\Models\HabitActivity;
use App\Models\HabitEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Log habits')]
class Log extends Component
{
    public string $date;

    public function mount(?string $date = null): void
    {
        $this->date = $date ?? now()->toDateString();
    }

    /**
     * @return Collection<int, HabitActivity>
     */
    #[Computed]
    public function activities(): Collection
    {
        return HabitActivity::query()->active()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, HabitEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        return HabitEntry::query()->whereDate('date', $this->date)->get()->keyBy('habit_activity_id');
    }

    public function toggle(int $activityId): void
    {
        $entry = HabitEntry::query()
            ->where('habit_activity_id', $activityId)
            ->whereDate('date', $this->date)
            ->first() ?? new HabitEntry([
                'habit_activity_id' => $activityId,
                'date' => $this->date,
            ]);

        $entry->completed = ! $entry->completed;
        $entry->save();

        unset($this->entries);
    }

    public function previousDay(): void
    {
        $this->date = Carbon::parse($this->date)->subDay()->toDateString();
        unset($this->entries);
    }

    public function nextDay(): void
    {
        $this->date = Carbon::parse($this->date)->addDay()->toDateString();
        unset($this->entries);
    }

    public function updatedDate(): void
    {
        unset($this->entries);
    }
}
