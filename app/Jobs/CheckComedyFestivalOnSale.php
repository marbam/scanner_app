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

class CheckComedyFestivalOnSale implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected Interest $interest) {}

    public function handle(): void
    {
        /** @var array{url: string, not_on_sale_text: string} $params */
        $params = $this->interest->provider_params;

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->get($params['url']);

            $response->throw();
            $hash = md5($response->body());

            if ($hash === $this->interest->last_response_hash) {
                $this->interest->update(['last_checked_at' => now()]);
                $this->logCheck($response, 'unchanged');

                return;
            }

            // The page shows a fixed "not on sale yet" message (e.g. "is now
            // over" between festivals) until tickets go live, at which point
            // that text is replaced by the actual line-up/booking links.
            $released = ! str_contains($response->body(), $params['not_on_sale_text']);

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
            'response_body' => $response !== null ? ['html' => $response->body()] : null,
            'outcome' => $outcome,
            'error_message' => $error,
        ]);
    }
}
