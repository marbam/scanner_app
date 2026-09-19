<?php

namespace App\Livewire\Reminders;

use App\Models\Reminder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Reminders')]
class Index extends Component
{
    public string $name = '';

    public string $description = '';

    public string $time = '07:30';

    /** @var array<int, int> */
    public array $days = [];

    /**
     * Keyed by reminder id: ['name' => ..., 'description' => ..., 'time' => ..., 'days' => [...]].
     * Bound directly to each row's inline-edit inputs.
     *
     * @var array<int, array{name: string, description: string, time: string, days: array<int, int>}>
     */
    public array $edits = [];

    /**
     * @return array<int, string>
     */
    public function dayOptions(): array
    {
        return [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
    }

    /**
     * @return Collection<int, Reminder>
     */
    #[Computed]
    public function reminders(): Collection
    {
        $reminders = Reminder::query()->orderBy('time')->orderBy('name')->get();

        foreach ($reminders as $reminder) {
            $this->edits[$reminder->id] ??= [
                'name' => $reminder->name,
                'description' => (string) $reminder->description,
                'time' => substr((string) $reminder->time, 0, 5),
                'days' => $reminder->days_of_week,
            ];
        }

        return $reminders;
    }

    public function addReminder(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'time' => 'required|date_format:H:i',
            'days' => 'required|array|min:1',
            'days.*' => 'integer|between:0,6',
        ]);

        Reminder::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'time' => $validated['time'],
            'days_of_week' => array_values(array_unique(array_map('intval', $validated['days']))),
        ]);

        $this->reset('name', 'description', 'time', 'days');
        $this->time = '07:30';

        unset($this->reminders);
    }

    public function updateReminder(int $reminderId): void
    {
        $reminder = Reminder::findOrFail($reminderId);

        $validated = validator(
            $this->edits[$reminderId] ?? [],
            [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'time' => 'required|date_format:H:i',
                'days' => 'required|array|min:1',
                'days.*' => 'integer|between:0,6',
            ]
        )->validate();

        $reminder->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'time' => $validated['time'],
            'days_of_week' => array_values(array_unique(array_map('intval', $validated['days']))),
        ]);

        unset($this->reminders);
    }

    public function toggleActive(int $reminderId): void
    {
        $reminder = Reminder::findOrFail($reminderId);

        $reminder->update(['is_active' => ! $reminder->is_active]);

        unset($this->reminders);
    }

    public function deleteReminder(int $reminderId): void
    {
        Reminder::whereKey($reminderId)->delete();

        unset($this->edits[$reminderId]);
        unset($this->reminders);
    }
}
