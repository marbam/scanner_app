<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;

class HabitLogReminder extends Notification
{
    /**
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushoverChannel::class];
    }

    public function toPushover(mixed $notifiable): PushoverMessage
    {
        return PushoverMessage::create("Don't forget to log today's habits.")
            ->title('Habit tracker')
            ->url(route('habits.log'), 'Log habits')
            ->priority(0);
    }
}
