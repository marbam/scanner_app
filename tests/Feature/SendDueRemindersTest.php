<?php

use App\Jobs\SendDueReminders;
use App\Models\Reminder;
use App\Notifications\ReminderDue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.pushover.user_key' => str_repeat('u', 30),
        'services.pushover.token' => 'fake-app-token',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-21 07:30:00', 'Europe/London'));
});

afterEach(function () {
    Carbon::setTestNow();
});

test('sends the notification when a reminder is due right now, on the right day', function () {
    Notification::fake();

    // 2026-09-21 is a Monday.
    Reminder::factory()->create([
        'time' => '07:30:00',
        'days_of_week' => [1, 2, 3, 4, 5],
        'is_active' => true,
    ]);

    (new SendDueReminders)->handle();

    Notification::assertSentOnDemand(ReminderDue::class);
});

test('skips a reminder scheduled for a different time', function () {
    Notification::fake();

    Reminder::factory()->create([
        'time' => '08:00:00',
        'days_of_week' => [1, 2, 3, 4, 5],
        'is_active' => true,
    ]);

    (new SendDueReminders)->handle();

    Notification::assertNothingSent();
});

test('skips a reminder not scheduled for today', function () {
    Notification::fake();

    // 2026-09-21 is a Monday; Saturday is 6.
    Reminder::factory()->create([
        'time' => '07:30:00',
        'days_of_week' => [6],
        'is_active' => true,
    ]);

    (new SendDueReminders)->handle();

    Notification::assertNothingSent();
});

test('skips an inactive reminder even when it matches', function () {
    Notification::fake();

    Reminder::factory()->create([
        'time' => '07:30:00',
        'days_of_week' => [1, 2, 3, 4, 5],
        'is_active' => false,
    ]);

    (new SendDueReminders)->handle();

    Notification::assertNothingSent();
});

test('a fires-once reminder deactivates itself after sending', function () {
    Notification::fake();

    $reminder = Reminder::factory()->firesOnce()->create([
        'time' => '07:30:00',
        'days_of_week' => [1, 2, 3, 4, 5],
        'is_active' => true,
    ]);

    (new SendDueReminders)->handle();

    Notification::assertSentOnDemand(ReminderDue::class);
    expect($reminder->fresh()->is_active)->toBeFalse();
});

test('a recurring reminder stays active after sending', function () {
    Notification::fake();

    $reminder = Reminder::factory()->create([
        'time' => '07:30:00',
        'days_of_week' => [1, 2, 3, 4, 5],
        'is_active' => true,
        'fires_once' => false,
    ]);

    (new SendDueReminders)->handle();

    Notification::assertSentOnDemand(ReminderDue::class);
    expect($reminder->fresh()->is_active)->toBeTrue();
});
