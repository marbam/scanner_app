<?php

namespace App\Livewire\Concerns;

use App\Models\HabitActivity;
use App\Models\HabitEntry;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

/**
 * Ticking habits off for `$this->date`. Shared by the habit log and the
 * Your Day page, which both render `livewire.habits.partials.list`.
 *
 * @property-read Collection<int, HabitActivity> $habitActivities
 * @property-read Collection<int, HabitEntry> $habitEntries
 */
trait LogsHabits
{
    /**
     * @return Collection<int, HabitActivity>
     */
    #[Computed]
    public function habitActivities(): Collection
    {
        return HabitActivity::query()->active()->orderBy('sort_order')->orderBy('name')->get();
    }

    /**
     * Entries for this date, keyed by activity id.
     *
     * @return Collection<int, HabitEntry>
     */
    #[Computed]
    public function habitEntries(): Collection
    {
        return HabitEntry::query()->whereDate('date', $this->date)->get()->keyBy('habit_activity_id');
    }

    public function habitsCompletedCount(): int
    {
        return $this->habitActivities
            ->filter(fn (HabitActivity $activity) => $this->habitEntries->get($activity->id)?->completed === true)
            ->count();
    }

    public function toggleHabit(int $activityId): void
    {
        if (! HabitActivity::query()->active()->whereKey($activityId)->exists()) {
            return;
        }

        $entry = HabitEntry::query()
            ->where('habit_activity_id', $activityId)
            ->whereDate('date', $this->date)
            ->first() ?? new HabitEntry([
                'habit_activity_id' => $activityId,
                'date' => $this->date,
            ]);

        $entry->completed = ! $entry->completed;
        $entry->save();

        unset($this->habitEntries);
    }
}
