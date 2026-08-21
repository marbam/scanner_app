<?php

use App\Models\User;
use App\Notifications\PushoverTestNotification;
use Illuminate\Support\Facades\Notification;

it('redirects guests to login', function () {
    Notification::fake();

    $this->get(route('pushover.test'))
        ->assertRedirect(route('login'));

    Notification::assertNothingSent();
});

it('sends a pushover test notification to an authenticated, verified user', function () {
    config([
        'services.pushover.user_key' => str_repeat('u', 30),
        'services.pushover.token' => 'fake-app-token',
    ]);
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('pushover.test'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Pushover test notification sent.');

    Notification::assertSentOnDemand(PushoverTestNotification::class);
});
