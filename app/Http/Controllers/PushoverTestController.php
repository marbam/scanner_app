<?php

namespace App\Http\Controllers;

use App\Notifications\PushoverTestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class PushoverTestController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        Notification::route('pushover', [
            'user_key' => config('services.pushover.user_key'),
            'token' => config('services.pushover.token'),
        ])->notify(new PushoverTestNotification);

        return back()->with('status', 'Pushover test notification sent.');
    }
}
