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

class CheckShowcaseCinemaOnSale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Interest $interest) {}

    public function handle(): void
    {
        /** @var array{movie_id: string, theater_code: string} $params */
        $params = $this->interest->provider_params;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
            ])->get('https://www.showcasecinemas.co.uk/api/gatsby-source-boxofficeapi/participatingTheaters', [
                'movieIds' => $params['movie_id'],
            ]);

            $response->throw();
            $hash = md5($response->body());

            if ($hash === $this->interest->last_response_hash) {
                $this->interest->update(['last_checked_at' => now()]);
                $this->logCheck($response, 'unchanged');

                return;
            }

            // Other Showcase venues can go on sale for a film before ours
            // does, so we match on our specific theatre code, not just
            // "is the film on sale anywhere".
            /** @var array<int, string> $theaterCodes */
            $theaterCodes = $response->json() ?? [];
            $released = in_array($params['theater_code'], $theaterCodes, true);

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
