<?php

use App\Jobs\ScanBristolAdvertPlanningApplications;
use App\Models\PlanningApplication;
use App\Models\User;
use App\Notifications\NewPlanningApplicationsFound;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.pushover.user_key' => str_repeat('u', 30),
        'services.pushover.token' => 'fake-app-token',
    ]);
});

function fakeArcGisResponse(array $features, bool $exceededTransferLimit = false): array
{
    return [
        'features' => array_map(fn (array $attributes) => ['attributes' => $attributes], $features),
        'exceededTransferLimit' => $exceededTransferLimit,
    ];
}

it('creates new planning applications and notifies once with all new references', function () {
    Http::fake([
        'maps2.bristol.gov.uk/*' => Http::response(fakeArcGisResponse([
            [
                'REFVAL' => '26/13085/A',
                'ADDRESS' => '281 Southmead Road, Bristol, BS10 5EL',
                'PROPOSAL' => 'Erection of an internally illuminated advertising display.',
                'STATUS' => 'Pending Consideration',
                'DECISION' => null,
                'DEC_DATE' => null,
            ],
            [
                'REFVAL' => '26/13087/A',
                'ADDRESS' => '111 Whiteladies Road, Bristol, BS8 2PB',
                'PROPOSAL' => 'Display of 1no. non-illuminated fascia sign.',
                'STATUS' => 'Pending Consideration',
                'DECISION' => null,
                'DEC_DATE' => null,
            ],
        ]), 200),
    ]);
    Notification::fake();

    (new ScanBristolAdvertPlanningApplications)->handle();

    $this->assertDatabaseCount('planning_applications', 2);

    $this->assertDatabaseHas('planning_applications', [
        'reference' => '26/13085/A',
        'status' => 'Pending Consideration',
        'viewed' => false,
    ]);

    Notification::assertSentOnDemand(
        NewPlanningApplicationsFound::class,
        fn (NewPlanningApplicationsFound $notification) => $notification->references === ['26/13085/A', '26/13087/A']
    );
});

it('does not notify or reset viewed state for applications already seen', function () {
    PlanningApplication::factory()->create([
        'reference' => '26/13085/A',
        'status' => 'Pending Consideration',
        'viewed' => true,
    ]);

    Http::fake([
        'maps2.bristol.gov.uk/*' => Http::response(fakeArcGisResponse([
            [
                'REFVAL' => '26/13085/A',
                'ADDRESS' => '281 Southmead Road, Bristol, BS10 5EL',
                'PROPOSAL' => 'Erection of an internally illuminated advertising display.',
                'STATUS' => 'Pending Consideration',
                'DECISION' => null,
                'DEC_DATE' => null,
            ],
        ]), 200),
    ]);
    Notification::fake();

    (new ScanBristolAdvertPlanningApplications)->handle();

    Notification::assertNothingSent();

    $this->assertDatabaseHas('planning_applications', [
        'reference' => '26/13085/A',
        'viewed' => true,
    ]);
});

it('paginates through resultOffset when the ArcGIS response is truncated', function () {
    Http::fake(function ($request) {
        $offset = (int) ($request->data()['resultOffset'] ?? 0);

        if ($offset === 0) {
            return Http::response(fakeArcGisResponse([
                ['REFVAL' => '26/13085/A', 'ADDRESS' => 'Addr 1', 'PROPOSAL' => null, 'STATUS' => 'Pending Consideration', 'DECISION' => null, 'DEC_DATE' => null],
            ], exceededTransferLimit: true), 200);
        }

        return Http::response(fakeArcGisResponse([
            ['REFVAL' => '26/13087/A', 'ADDRESS' => 'Addr 2', 'PROPOSAL' => null, 'STATUS' => 'Pending Consideration', 'DECISION' => null, 'DEC_DATE' => null],
        ]), 200);
    });
    Notification::fake();

    (new ScanBristolAdvertPlanningApplications)->handle();

    $this->assertDatabaseCount('planning_applications', 2);
});

it('does not scan at all when the owner has disabled the daily scan', function () {
    User::factory()->create(['bristol_adverts_scan_enabled' => false]);

    Http::fake([
        'maps2.bristol.gov.uk/*' => Http::response(fakeArcGisResponse([
            ['REFVAL' => '26/13085/A', 'ADDRESS' => 'Addr 1', 'PROPOSAL' => null, 'STATUS' => 'Pending Consideration', 'DECISION' => null, 'DEC_DATE' => null],
        ]), 200),
    ]);
    Notification::fake();

    (new ScanBristolAdvertPlanningApplications)->handle();

    Http::assertNothingSent();
    Notification::assertNothingSent();
    $this->assertDatabaseCount('planning_applications', 0);
});

it('does not notify and logs a warning on request failure', function () {
    Http::fake([
        'maps2.bristol.gov.uk/*' => Http::response('Server error', 500),
    ]);
    Notification::fake();

    (new ScanBristolAdvertPlanningApplications)->handle();

    Notification::assertNothingSent();
    $this->assertDatabaseCount('planning_applications', 0);
});
