<?php

use App\Jobs\SendHabitLogReminder;
use App\Models\HabitActivity;
use App\Models\HabitEntry;
use App\Notifications\HabitLogReminder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.pushover.user_key' => str_repeat('u', 30),
        'services.pushover.token' => 'fake-app-token',
    ]);
});

test('sends the reminder when nothing has been logged today', function () {
    Notification::fake();

    (new SendHabitLogReminder)->handle();

    Notification::assertSentOnDemand(HabitLogReminder::class);
});

test('skips the reminder when today already has an entry', function () {
    Notification::fake();

    $activity = HabitActivity::factory()->create();
    HabitEntry::factory()->for($activity, 'activity')->create(['date' => today(), 'completed' => false]);

    (new SendHabitLogReminder)->handle();

    Notification::assertNothingSent();
});
