<?php

namespace App\Console\Commands;

use App\Jobs\FetchSteamAchievements;
use App\Models\SteamGame;
use Illuminate\Console\Command;

class SyncSteamAchievementsCommand extends Command
{
    protected $signature = 'app:sync-steam-achievements
                            {--limit=0 : Stop after this many games (0 = all pending)}';

    protected $description = 'Fetch achievement progress for Steam games never fetched, or played since the last fetch';

    public function handle(): int
    {
        $pending = FetchSteamAchievements::pendingQuery()->count();
        $limit = (int) $this->option('limit');
        $toProcess = $limit > 0 ? min($limit, $pending) : $pending;

        if ($toProcess === 0) {
            $this->components->info('Achievements are already up to date.');

            return self::SUCCESS;
        }

        $this->components->info("Fetching achievements for {$toProcess} games...");

        $bar = $this->output->createProgressBar($toProcess);

        try {
            $processed = (new FetchSteamAchievements($toProcess))->handle(fn () => $bar->advance());
        } catch (\Throwable $e) {
            $this->newLine();
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $remaining = FetchSteamAchievements::pendingQuery()->count();
        $completed = SteamGame::where('achievements_total', '>', 0)->whereColumn('achievements_unlocked', '>=', 'achievements_total')->count();

        if ($processed < $toProcess) {
            $this->components->warn("Stopped early after {$processed} games (Steam rate limit or error - see the log). {$remaining} still pending; re-run later to continue.");

            return self::FAILURE;
        }

        $this->components->info("Done: {$processed} games updated. {$completed} games at 100%.");

        return self::SUCCESS;
    }
}
