<?php

namespace App\Jobs;

use App\Models\Reminder;
use App\Notifications\ReminderDue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Pushover\PushoverReceiver;

class SendDueReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = Carbon::now('Europe/London');

        Reminder::active()
            ->get()
            ->filter(fn (Reminder $reminder) => $reminder->isDueAt($now))
            ->each(function (Reminder $reminder) {
                Notification::route('pushover', PushoverReceiver::withUserKey(config('services.pushover.user_key'))
                    ->withApplicationToken(config('services.pushover.token')))
                    ->notify(new ReminderDue($reminder));

                if ($reminder->fires_once) {
                    $reminder->update(['is_active' => false]);
                }
            });
    }
}
