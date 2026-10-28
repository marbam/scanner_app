<?php

namespace App\Console\Commands;

use App\Models\SteamGamePlay;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SteamPlayLogCommand extends Command
{
    protected $signature = 'app:steam-play-log {--days=14 : How many recent days to show}';

    protected $description = 'Show which Steam games were played on each recent day (recorded by the daily library sync)';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now('Europe/London')->subDays($days - 1)->toDateString();

        $plays = SteamGamePlay::with('game:id,name')
            ->whereDate('played_on', '>=', $since)
            ->orderByDesc('played_on')
            ->orderByDesc('minutes')
            ->get()
            ->groupBy(fn (SteamGamePlay $play) => $play->played_on->toDateString());

        if ($plays->isEmpty()) {
            $this->components->info("No Steam games recorded as played in the last {$days} days.");

            return self::SUCCESS;
        }

        foreach ($plays as $date => $dayPlays) {
            $count = $dayPlays->count();
            $label = Carbon::parse($date)->format('D j M Y');

            $this->components->twoColumnDetail(
                "<fg=green>{$label}</>",
                $count.' '.($count === 1 ? 'game' : 'games'),
            );

            foreach ($dayPlays as $play) {
                $this->components->twoColumnDetail('  '.$play->game->name, $play->minutes.' min');
            }
        }

        return self::SUCCESS;
    }
}
