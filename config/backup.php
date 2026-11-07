<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/*
 * Nightly off-site database backups (see `app:backup-database`).
 *
 * The package's own SQLite support shells out to the `sqlite3` CLI, which
 * isn't installed on the droplet. Instead, `app:backup-database` writes a
 * consistent snapshot with SQLite's `VACUUM INTO` to `backup-snapshot/`, and
 * this config backs up just that folder as an encrypted zip. Restoring is
 * unzipping `database.sqlite` and putting it back in `database/`.
 */

return [

    'backup' => [
        // Also the folder name on the backup disk. Fixed rather than APP_NAME so
        // renaming the app doesn't orphan the existing backups.
        'name' => 'scanner_app',

        'source' => [
            'files' => [
                'include' => [
                    storage_path('app/backup-snapshot'),
                ],

                'exclude' => [],

                'follow_links' => false,

                'ignore_unreadable_directories' => false,

                // Store the snapshot at the root of the zip, not under its full server path.
                'relative_path' => storage_path('app/backup-snapshot'),
            ],

            // Deliberately empty: the snapshot above replaces the package's database dump.
            'databases' => [],
        ],

        'database_dump_compressor' => null,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => '',

            'disks' => [
                env('BACKUP_DISK', 'r2'),
            ],

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        // AES-256 zip encryption. Keep this password in a password manager:
        // without it the backups can't be opened.
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        'verify_backup' => true,

        // One retry after a minute, for a brief network blip on upload.
        'tries' => 2,

        'retry_delay' => 60,
    ],

    /*
     * The package's notifications are switched off (no channels). Problems are
     * sent through Pushover instead by App\Listeners\SendBackupProblemAlert,
     * the same way as every other alert in this app.
     */
    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => [],
            UnhealthyBackupWasFoundNotification::class => [],
            CleanupHasFailedNotification::class => [],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => 'scanner_app',
            'disks' => [env('BACKUP_DISK', 'r2')],
            'health_checks' => [
                // The backup runs nightly, so anything older than a day means a missed run.
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 1000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,

            'keep_daily_backups_for_days' => 30,

            'keep_weekly_backups_for_weeks' => 8,

            'keep_monthly_backups_for_months' => 12,

            'keep_yearly_backups_for_years' => 2,

            // Well inside R2's free 10 GB.
            'delete_oldest_backups_when_using_more_megabytes_than' => 1000,
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

];
