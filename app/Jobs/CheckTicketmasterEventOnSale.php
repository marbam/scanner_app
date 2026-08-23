<?php

namespace App\Jobs;

use App\Models\Interest;
use App\Notifications\InterestReleased;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Pushover\PushoverReceiver;

class CheckTicketmasterEventOnSale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Interest $interest) {}

    public function handle(): void
    {
        /** @var array{attraction_id: string, country_code: string} $params */
        $params = $this->interest->provider_params;

        try {
            $response = Http::get('https://app.ticketmaster.com/discovery/v2/events.json', [
                'apikey' => config('services.ticketmaster.key'),
                'attractionId' => $params['attraction_id'],
                'countryCode' => $params['country_code'],
            ]);

            $response->throw();
            $hash = md5($response->body());

            if ($hash === $this->interest->last_response_hash) {
                $this->interest->update(['last_checked_at' => now()]);
                $this->logCheck($response, 'unchanged');

                return;
            }

            // Matching by attractionId, not a keyword — a free-text search
            // for e.g. "Foo Fighters" returns dozens of unrelated tribute
            // acts that are also "on sale", which would false-positive
            // immediately. Nothing is announced until Ticketmaster lists at
            // least one event for this exact attraction in the country, so
            // any result at all is the announcement/on-sale signal — there's
            // no "day" or "venue" to narrow to before a show even exists.
            $released = $response->json('page.totalElements', 0) > 0;

            $this->interest->update([
                'last_response_hash' => $hash,
                'last_checked_at' => now(),
                'status' => $released ? 'released' : 'watching',
            ]);

            $this->logCheck($response, $released ? 'released' : 'checked_no_release');

            if ($released) {
                $this->notifyReleased();
            }
        } catch (\Throwable $e) {
            Log::warning("Interest check failed for {$this->interest->id}: {$e->getMessage()}");
            $this->interest->update(['status' => 'error', 'last_checked_at' => now()]);
            $this->logCheck(null, 'error', $e->getMessage());
        }
    }

    protected function notifyReleased(): void
    {
        $this->interest->alerts()->create([
            'title' => "It's live: {$this->interest->name}",
            'detected_at' => now(),
            'notified_at' => now(),
        ]);

        Notification::route('pushover', PushoverReceiver::withUserKey(config('services.pushover.user_key'))
            ->withApplicationToken(config('services.pushover.token')))
            ->notify(new InterestReleased($this->interest));
    }

    protected function logCheck(?Response $response, string $outcome, ?string $error = null): void
    {
        $this->interest->checks()->create([
            'http_status' => $response?->status(),
            'response_body' => $response?->json(),
            'outcome' => $outcome,
            'error_message' => $error,
        ]);
    }
}
