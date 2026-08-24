<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;

class NewPlanningApplicationsFound extends Notification
{
    /**
     * @param  array<int, string>  $references
     */
    public function __construct(public readonly array $references) {}

    /**
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushoverChannel::class];
    }

    public function toPushover(mixed $notifiable): PushoverMessage
    {
        $count = count($this->references);
        $list = implode(', ', $this->references);

        return PushoverMessage::create("{$count} new advert application(s): {$list}")
            ->title('Bristol planning: adverts')
            ->priority(0);
    }
}
