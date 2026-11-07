<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;

class BackupProblem extends Notification
{
    public function __construct(
        public string $problem,
        public string $detail,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushoverChannel::class];
    }

    public function toPushover(mixed $notifiable): PushoverMessage
    {
        return PushoverMessage::create(mb_strimwidth($this->detail, 0, 900, '…'))
            ->title("Backup: {$this->problem}")
            ->priority(0);
    }
}
