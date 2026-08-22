<?php

namespace App\Http\Controllers;

use App\Models\Interest;
use App\Notifications\InterestReleased;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Pushover\PushoverReceiver;

class CinemaCheckWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interest_id' => ['required', 'integer', 'exists:interests,id'],
            'on_sale' => ['required', 'boolean'],
            'snapshot' => ['required', 'string'],
        ]);

        $interest = Interest::findOrFail((int) $validated['interest_id']);
        $hash = md5($validated['snapshot']);
        $unchanged = $hash === $interest->last_response_hash;
        $released = ! $unchanged && $validated['on_sale'];

        $outcome = match (true) {
            $unchanged => 'unchanged',
            $released => 'released',
            default => 'checked_no_release',
        };

        // Everything here is a DB write, so it's wrapped in a transaction to
        // avoid ending up with an Interest marked 'released' but no matching
        // InterestCheck/Alert row if the request gets killed mid-way (e.g. a
        // reverse-proxy timeout) — the actual Pushover push happens after
        // commit since it's an external call, not something to hold a
        // transaction open for.
        DB::transaction(function () use ($interest, $hash, $unchanged, $released, $outcome, $validated) {
            $interest->update($unchanged
                ? ['last_checked_at' => now()]
                : ['last_response_hash' => $hash, 'last_checked_at' => now(), 'status' => $released ? 'released' : 'watching']);

            $this->logCheck($interest, $validated, $outcome);

            if ($released) {
                $this->createAlert($interest);
            }
        });

        if ($released) {
            $this->sendNotification($interest);
        }

        return response()->json(['outcome' => $outcome]);
    }

    protected function createAlert(Interest $interest): void
    {
        $interest->alerts()->create([
            'title' => "It's live: {$interest->name}",
            'detected_at' => now(),
            'notified_at' => now(),
        ]);
    }

    protected function sendNotification(Interest $interest): void
    {
        Notification::route('pushover', PushoverReceiver::withUserKey(config('services.pushover.user_key'))
            ->withApplicationToken(config('services.pushover.token')))
            ->notify(new InterestReleased($interest));
    }

    /**
     * @param  array{interest_id: int, on_sale: bool, snapshot: string}  $validated
     */
    protected function logCheck(Interest $interest, array $validated, string $outcome): void
    {
        $interest->checks()->create([
            'http_status' => 200,
            'response_body' => ['snapshot' => $validated['snapshot'], 'on_sale' => $validated['on_sale']],
            'outcome' => $outcome,
        ]);
    }
}
