<?php

namespace App\Notifications;

use App\Models\Interest;
use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;

class InterestReleased extends Notification
{
    public function __construct(protected Interest $interest) {}

    /**
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushoverChannel::class];
    }

    public function toPushover(mixed $notifiable): PushoverMessage
    {
        return PushoverMessage::create("It's live: {$this->interest->name}")
            ->title('Interest tracker')
            ->priority(1);
    }
}
