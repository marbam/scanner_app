<?php

namespace App\Jobs;

use App\Models\HabitEntry;
use App\Notifications\HabitLogReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Pushover\PushoverReceiver;

class SendHabitLogReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        if (HabitEntry::whereDate('date', today())->exists()) {
            return;
        }

        Notification::route('pushover', PushoverReceiver::withUserKey(config('services.pushover.user_key'))
            ->withApplicationToken(config('services.pushover.token')))
            ->notify(new HabitLogReminder);
    }
}
