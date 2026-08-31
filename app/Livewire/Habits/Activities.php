<?php

namespace App\Livewire\Habits;

use App\Models\HabitActivity;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Habit activities')]
class Activities extends Component
{
    public string $name = '';

    public string $color = '#22c55e';

    public float $weight = 1;

    /**
     * Keyed by activity id: ['name' => ..., 'color' => ..., 'weight' => ...].
     * Bound directly to each row's inline-edit inputs.
     *
     * @var array<int, array{name: string, color: string, weight: float}>
     */
    public array $edits = [];

    /**
     * @return Collection<int, HabitActivity>
     */
    #[Computed]
    public function activities(): Collection
    {
        $activities = HabitActivity::query()->orderBy('sort_order')->orderBy('name')->get();

        foreach ($activities as $activity) {
            $this->edits[$activity->id] ??= [
                'name' => $activity->name,
                'color' => $activity->color,
                'weight' => (float) $activity->weight,
            ];
        }

        return $activities;
    }

    public function addActivity(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255|unique:habit_activities,name',
            'color' => 'required|string|max:7',
            'weight' => 'required|numeric|min:0.1|max:99.99',
        ]);

        HabitActivity::create([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'weight' => $validated['weight'],
            'sort_order' => (int) HabitActivity::query()->max('sort_order') + 1,
        ]);

        $this->reset('name', 'weight');
        $this->color = '#22c55e';

        unset($this->activities);
    }

    public function updateActivity(int $activityId): void
    {
        $activity = HabitActivity::findOrFail($activityId);

        $validated = validator(
            $this->edits[$activityId] ?? [],
            [
                'name' => 'required|string|max:255|unique:habit_activities,name,'.$activity->id,
                'color' => 'required|string|max:7',
                'weight' => 'required|numeric|min:0.1|max:99.99',
            ]
        )->validate();

        $activity->update($validated);

        unset($this->activities);
    }

    public function toggleArchived(int $activityId): void
    {
        $activity = HabitActivity::findOrFail($activityId);

        $activity->update(['archived_at' => $activity->archived_at ? null : now()]);

        unset($this->activities);
    }
}
