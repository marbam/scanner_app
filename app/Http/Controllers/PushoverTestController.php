<?php

namespace App\Http\Controllers;

use App\Notifications\PushoverTestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Pushover\PushoverReceiver;

class PushoverTestController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        Notification::route('pushover', PushoverReceiver::withUserKey(config('services.pushover.user_key'))
            ->withApplicationToken(config('services.pushover.token')))
            ->notify(new PushoverTestNotification);

        return back()->with('status', 'Pushover test notification sent.');
    }
}
