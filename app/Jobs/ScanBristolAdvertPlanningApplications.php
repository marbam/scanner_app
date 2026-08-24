<?php

namespace App\Jobs;

use App\Models\PlanningApplication;
use App\Models\User;
use App\Notifications\NewPlanningApplicationsFound;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Pushover\PushoverReceiver;

class ScanBristolAdvertPlanningApplications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Bristol's own planning portal (pa.bristol.gov.uk) sits behind
     * session/anti-crawling protection and returns 500s on plain GETs, but
     * the council publishes the same planning register through this open,
     * unauthenticated ArcGIS feature service. Advertisement consent
     * applications carry a "/A" reference suffix (e.g. "26/12803/A") — a
     * far more reliable filter than text-matching "advert" in the free-text
     * PROPOSAL field, which also catches unrelated things like defibrillator
     * cabinets that merely mention an advertisement panel.
     */
    protected const API_URL = 'https://maps2.bristol.gov.uk/server2/rest/services/ext/ll_environment_and_planning/MapServer/2/query';

    protected const PAGE_SIZE = 1000;

    public function handle(): void
    {
        $owner = User::first();

        if ($owner !== null && ! $owner->bristol_adverts_scan_enabled) {
            return;
        }

        try {
            $rows = $this->fetchPendingAdvertApplications();
        } catch (\Throwable $e) {
            Log::warning("Bristol advert planning scan failed: {$e->getMessage()}");

            return;
        }

        $newReferences = [];

        foreach ($rows as $row) {
            $planningApplication = PlanningApplication::updateOrCreate(
                ['reference' => $row['REFVAL']],
                [
                    'address' => $row['ADDRESS'],
                    'proposal' => $row['PROPOSAL'],
                    'status' => $row['STATUS'],
                    'decision' => $row['DECISION'],
                    'decision_date' => $row['DEC_DATE'] ? Carbon::createFromTimestampMs($row['DEC_DATE']) : null,
                ]
            );

            if ($planningApplication->wasRecentlyCreated) {
                $newReferences[] = $row['REFVAL'];
            }
        }

        if ($newReferences !== []) {
            $this->notifyNewApplications($newReferences);
        }
    }

    /**
     * @return array<int, array{REFVAL: string, ADDRESS: string, PROPOSAL: ?string, STATUS: ?string, DECISION: ?string, DEC_DATE: ?int}>
     */
    protected function fetchPendingAdvertApplications(): array
    {
        $rows = [];
        $offset = 0;

        do {
            $response = Http::get(self::API_URL, [
                'where' => "REFVAL LIKE '%/A' AND DEC_DATE IS NULL",
                'outFields' => 'REFVAL,ADDRESS,PROPOSAL,STATUS,DECISION,DEC_DATE',
                'orderByFields' => 'REFVAL DESC',
                'resultOffset' => $offset,
                'resultRecordCount' => self::PAGE_SIZE,
                'f' => 'json',
            ]);

            $response->throw();

            $features = $response->json('features', []);

            foreach ($features as $feature) {
                $rows[] = $feature['attributes'];
            }

            $exceededLimit = (bool) $response->json('exceededTransferLimit', false);
            $offset += self::PAGE_SIZE;
        } while ($exceededLimit);

        return $rows;
    }

    /**
     * @param  array<int, string>  $references
     */
    protected function notifyNewApplications(array $references): void
    {
        Notification::route('pushover', PushoverReceiver::withUserKey(config('services.pushover.user_key'))
            ->withApplicationToken(config('services.pushover.token')))
            ->notify(new NewPlanningApplicationsFound($references));
    }
}
