<?php

namespace App\Console\Commands;

use App\Jobs\FetchSteamGameDetails;
use App\Models\SteamGame;
use Illuminate\Console\Command;

class SyncSteamGameDetailsCommand extends Command
{
    protected $signature = 'app:sync-steam-game-details
                            {--limit=0 : Stop after this many games (0 = all pending)}';

    protected $description = 'Fetch store details and tags for Steam games that don\'t have them yet (throttled, roughly 1.5s per game)';

    public function handle(): int
    {
        $pending = SteamGame::whereNull('details_synced_at')->count();
        $limit = (int) $this->option('limit');
        $toProcess = $limit > 0 ? min($limit, $pending) : $pending;

        if ($toProcess === 0) {
            $this->components->info('Every game already has its details.');

            return self::SUCCESS;
        }

        $this->components->info("Fetching details for {$toProcess} games (about ".max(1, (int) ceil($toProcess * 2 / 60)).' minutes)...');

        $bar = $this->output->createProgressBar($toProcess);
        $processed = (new FetchSteamGameDetails($toProcess))->handle(fn () => $bar->advance());
        $bar->finish();
        $this->newLine(2);

        $remaining = SteamGame::whereNull('details_synced_at')->count();

        if ($processed < $toProcess) {
            $this->components->warn("Stopped early after {$processed} games (Steam rate limit or error - see the log). {$remaining} still pending; re-run later to continue.");

            return self::FAILURE;
        }

        $this->components->info("Done: {$processed} games updated, {$remaining} still pending.");

        return self::SUCCESS;
    }
}
