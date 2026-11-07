<?php

namespace App\Listeners;

use App\Notifications\BackupProblem;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Pushover\PushoverReceiver;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\CleanupHasFailed;
use Spatie\Backup\Events\UnhealthyBackupWasFound;

/**
 * Sends a Pushover alert when a backup, cleanup or the morning health check
 * goes wrong. Successes stay quiet. Picked up by Laravel's event discovery,
 * so production needs `php artisan event:cache` after deploying changes here.
 */
class SendBackupProblemAlert
{
    public function handleBackupFailed(BackupHasFailed $event): void
    {
        $this->send('failed', $event->exception->getMessage());
    }

    public function handleCleanupFailed(CleanupHasFailed $event): void
    {
        $this->send('cleanup failed', $event->exception->getMessage());
    }

    public function handleUnhealthyBackup(UnhealthyBackupWasFound $event): void
    {
        $this->send(
            'needs attention',
            $event->failureMessages->pluck('message')->implode("\n") ?: 'The backup health check failed.',
        );
    }

    private function send(string $problem, string $detail): void
    {
        Notification::route('pushover', PushoverReceiver::withUserKey((string) config('services.pushover.user_key'))
            ->withApplicationToken((string) config('services.pushover.token')))
            ->notify(new BackupProblem($problem, $detail));
    }
}
