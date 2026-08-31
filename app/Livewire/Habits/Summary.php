<?php

namespace App\Livewire\Habits;

use App\Models\HabitActivity;
use App\Models\HabitEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Growth summary')]
class Summary extends Component
{
    public int $rangeDays = 90;

    /**
     * Daily weighted score plus a 7-day rolling average, for the chart.
     *
     * @return array<int, array{date: string, score: float, average: float}>
     */
    #[Computed]
    public function points(): array
    {
        $today = Carbon::today();
        $start = $today->clone()->subDays($this->rangeDays - 1);
        $rollingStart = $start->clone()->subDays(6);

        $activities = HabitActivity::query()->get()->keyBy('id');

        $entries = HabitEntry::query()
            ->where('completed', true)
            ->whereDate('date', '>=', $rollingStart->toDateString())
            ->whereDate('date', '<=', $today->toDateString())
            ->get()
            ->groupBy(fn (HabitEntry $entry) => Carbon::parse($entry->date)->toDateString());

        $dailyScores = [];
        $cursor = $rollingStart->clone();

        while ($cursor->lte($today)) {
            $dateKey = $cursor->toDateString();

            $dailyScores[$dateKey] = $entries->get($dateKey, collect())
                ->sum(fn (HabitEntry $entry) => self::weightFor($activities, $entry->habit_activity_id));

            $cursor->addDay();
        }

        $points = [];
        $cursor = $start->clone();

        while ($cursor->lte($today)) {
            $dateKey = $cursor->toDateString();

            $window = collect(range(0, 6))
                ->map(fn (int $offset) => $dailyScores[$cursor->clone()->subDays($offset)->toDateString()] ?? 0.0);

            $points[] = [
                'date' => $dateKey,
                'score' => $dailyScores[$dateKey] ?? 0.0,
                'average' => round($window->average(), 2),
            ];

            $cursor->addDay();
        }

        return $points;
    }

    public function setRange(int $days): void
    {
        $this->rangeDays = $days;
        unset($this->points);
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
