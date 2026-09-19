<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;

class ReminderDue extends Notification
{
    public function __construct(public readonly Reminder $reminder) {}

    /**
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushoverChannel::class];
    }

    public function toPushover(mixed $notifiable): PushoverMessage
    {
        return PushoverMessage::create($this->reminder->description ?: $this->reminder->name)
            ->title($this->reminder->name)
            ->url(route('reminders.index'), 'View reminders')
            ->priority(0);
    }
}
