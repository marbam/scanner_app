<?php

use App\Notifications\HabitLogReminder;
use NotificationChannels\Pushover\PushoverChannel;

test('sends via pushover', function () {
    expect((new HabitLogReminder)->via(null))->toBe([PushoverChannel::class]);
});

test('links to the habit log page', function () {
    $message = (new HabitLogReminder)->toPushover(null);

    expect($message->toArray()['url'])->toBe(route('habits.log'));
});
