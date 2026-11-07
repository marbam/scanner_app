<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Backup\Events\BackupHasFailed;

/**
 * Snapshots the SQLite database with `VACUUM INTO`, then hands the snapshot to
 * spatie/laravel-backup (`backup:run`) to zip, encrypt and upload. See
 * config/backup.php for why the package's own SQLite dump isn't used.
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Snapshot the database and upload an encrypted copy to the backup disk';

    public function handle(): int
    {
        $directory = self::snapshotDirectory();
        $snapshot = $directory.'/database.sqlite';

        try {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                throw new Exception('app:backup-database only supports SQLite.');
            }

            File::ensureDirectoryExists($directory);
            File::delete($snapshot); // VACUUM INTO refuses to overwrite an existing file

            // A consistent copy even while the app is writing, unlike copying the file directly.
            DB::statement('VACUUM INTO ?', [$snapshot]);
        } catch (Exception $e) {
            report($e);
            $this->error("Couldn't snapshot the database: {$e->getMessage()}");
            event(new BackupHasFailed($e));
            File::delete($snapshot);

            return self::FAILURE;
        }

        try {
            return $this->call('backup:run', ['--only-files' => true]);
        } finally {
            // The snapshot is unencrypted, so don't leave it lying around.
            File::delete($snapshot);
        }
    }

    public static function snapshotDirectory(): string
    {
        return storage_path('app/backup-snapshot');
    }
}
