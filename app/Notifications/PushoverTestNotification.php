<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;

class PushoverTestNotification extends Notification
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
        return PushoverMessage::create('Pushover notifications are wired up correctly.')
            ->title('Interest tracker test')
            ->priority(0);
    }
}
