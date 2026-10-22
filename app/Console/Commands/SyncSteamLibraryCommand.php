<?php

namespace App\Console\Commands;

use App\Jobs\SyncSteamLibrary;
use App\Models\SteamGame;
use Illuminate\Console\Command;

class SyncSteamLibraryCommand extends Command
{
    protected $signature = 'app:sync-steam-library';

    protected $description = 'Import/refresh the owner\'s Steam library (games and playtime) into steam_games';

    public function handle(): int
    {
        try {
            $count = (new SyncSteamLibrary)->handle();
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $neverPlayed = SteamGame::where('playtime_forever', 0)->count();

        $this->components->info("Synced {$count} games ({$neverPlayed} never played).");

        return self::SUCCESS;
    }
}
