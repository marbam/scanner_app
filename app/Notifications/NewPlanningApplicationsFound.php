<?php

namespace App\Notifications;

use App\Models\PlanningApplication;
use Illuminate\Notifications\Notification;
use NotificationChannels\Pushover\PushoverChannel;
use NotificationChannels\Pushover\PushoverMessage;

class NewPlanningApplicationsFound extends Notification
{
    /**
     * @param  array<int, PlanningApplication>  $applications
     */
    public function __construct(public readonly array $applications) {}

    /**
     * @return array<int, class-string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushoverChannel::class];
    }

    public function toPushover(mixed $notifiable): PushoverMessage
    {
        $count = count($this->applications);
        $list = implode(', ', array_map(fn (PlanningApplication $application) => $application->reference, $this->applications));

        $message = PushoverMessage::create("{$count} new advert application(s): {$list}")
            ->title('Bristol planning: adverts')
            ->priority(0);

        // Bristol's own planning portal has no stable per-application URL we
        // can construct from just the reference (its "keyVal" is only issued
        // via a live search session), so link back to our own dashboard
        // rather than a broken/guessed council URL.
        if ($count === 1) {
            $message->url(
                route('planning-applications.index'),
                "View {$this->applications[0]->reference} in Scanner"
            );
        }

        return $message;
    }
}
