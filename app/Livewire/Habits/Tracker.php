<?php

namespace App\Livewire\Habits;

use App\Models\HabitActivity;
use App\Models\HabitEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Habit tracker')]
class Tracker extends Component
{
    private const WEEKS = 26;

    /**
     * One GitHub-style grid per active activity, shaded in that activity's
     * own color wherever it was completed.
     *
     * @return array<int, array{name: string, color: string, weeks: array<int, array<int, array{date: string, filled: bool}>>}>
     */
    #[Computed]
    public function activityGrids(): array
    {
        $start = $this->start();

        $activities = HabitActivity::query()->active()->orderBy('sort_order')->orderBy('name')->get();

        $completedDatesByActivity = HabitEntry::query()
            ->where('completed', true)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', Carbon::today()->toDateString())
            ->get()
            ->groupBy('habit_activity_id')
            ->map(fn (Collection $entries) => $entries
                ->map(fn (HabitEntry $entry) => Carbon::parse($entry->date)->toDateString())
                ->flip());

        return $activities->map(function (HabitActivity $activity) use ($start, $completedDatesByActivity) {
            $completedDates = $completedDatesByActivity->get($activity->id, collect());

            return [
                'name' => $activity->name,
                'color' => $activity->color,
                'weeks' => $this->buildWeeks($start, fn (string $dateKey) => $completedDates->has($dateKey)),
            ];
        })->all();
    }

    /**
     * A combined GitHub-style grid across every activity, shaded green by
     * that day's total weighted score (lighter for a lower score, darker
     * for a higher one).
     *
     * @return array<int, array<int, array{date: string, intensity: int, score: float}>>
     */
    #[Computed]
    public function summaryGrid(): array
    {
        $start = $this->start();

        $activities = HabitActivity::query()->get()->keyBy('id');

        $scoresByDate = HabitEntry::query()
            ->where('completed', true)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', Carbon::today()->toDateString())
            ->get()
            ->groupBy(fn (HabitEntry $entry) => Carbon::parse($entry->date)->toDateString())
            ->map(fn (Collection $entries) => (float) $entries->sum(
                fn (HabitEntry $entry) => self::weightFor($activities, $entry->habit_activity_id)
            ));

        $maxScore = $scoresByDate->max() ?: 1.0;

        $weeks = [];
        $cursor = $start->clone();

        for ($week = 0; $week < self::WEEKS; $week++) {
            $days = [];

            for ($day = 0; $day < 7; $day++) {
                $dateKey = $cursor->toDateString();
                $score = $scoresByDate->get($dateKey, 0.0);

                $days[] = [
                    'date' => $dateKey,
                    'score' => $score,
                    'intensity' => $score === 0.0 ? 0 : max(1, (int) ceil(($score / $maxScore) * 4)),
                ];

                $cursor->addDay();
            }

            $weeks[] = $days;
        }

        return $weeks;
    }

    private function start(): Carbon
    {
        return Carbon::today()->startOfWeek(Carbon::MONDAY)->subWeeks(self::WEEKS - 1);
    }

    /**
     * @param  callable(string): bool  $filledFor
     * @return array<int, array<int, array{date: string, filled: bool}>>
     */
    private function buildWeeks(Carbon $start, callable $filledFor): array
    {
        $weeks = [];
        $cursor = $start->clone();

        for ($week = 0; $week < self::WEEKS; $week++) {
            $days = [];

            for ($day = 0; $day < 7; $day++) {
                $dateKey = $cursor->toDateString();

                $days[] = [
                    'date' => $dateKey,
                    'filled' => $filledFor($dateKey),
                ];

                $cursor->addDay();
            }

            $weeks[] = $days;
        }

        return $weeks;
    }

    /**
     * @param  Collection<int, HabitActivity>  $activities
     */
    private static function weightFor(Collection $activities, int $activityId): float
    {
        $activity = $activities->get($activityId);

        return $activity !== null ? (float) $activity->weight : 0.0;
    }
}
