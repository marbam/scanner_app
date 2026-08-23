<?php

use App\Jobs\CheckTicketmasterEventOnSale;
use App\Models\Interest;
use App\Notifications\InterestReleased;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.pushover.user_key' => str_repeat('u', 30),
        'services.pushover.token' => 'fake-app-token',
        'services.ticketmaster.key' => 'fake-ticketmaster-key',
    ]);

    $this->interest = Interest::factory()->create([
        'provider' => 'ticketmaster',
        'provider_params' => [
            'attraction_id' => 'K8vZ91713Y7',
            'country_code' => 'GB',
        ],
        'status' => 'watching',
        'last_response_hash' => null,
    ]);
});

it('marks interest as released, records an alert, and notifies when an event is listed', function () {
    Http::fake([
        'app.ticketmaster.com/*' => Http::response([
            'page' => ['size' => 20, 'totalElements' => 1, 'totalPages' => 1, 'number' => 0],
            '_embedded' => ['events' => [['id' => 'abc123', 'name' => 'Foo Fighters']]],
        ], 200),
    ]);
    Notification::fake();

    (new CheckTicketmasterEventOnSale($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('released');

    Notification::assertSentOnDemand(InterestReleased::class);

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'released',
    ]);

    $this->assertDatabaseHas('alerts', [
        'interest_id' => $this->interest->id,
    ]);
});

it('does not notify while no matching event is listed', function () {
    Http::fake([
        'app.ticketmaster.com/*' => Http::response([
            'page' => ['size' => 20, 'totalElements' => 0, 'totalPages' => 0, 'number' => 0],
        ], 200),
    ]);
    Notification::fake();

    (new CheckTicketmasterEventOnSale($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('watching');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'checked_no_release',
    ]);

    $this->assertDatabaseCount('alerts', 0);
});

it('skips parsing and notifying when the response is unchanged', function () {
    $body = json_encode(['page' => ['size' => 20, 'totalElements' => 0, 'totalPages' => 0, 'number' => 0]]);

    $this->interest->update(['last_response_hash' => md5($body)]);

    Http::fake([
        'app.ticketmaster.com/*' => Http::response($body, 200),
    ]);
    Notification::fake();

    (new CheckTicketmasterEventOnSale($this->interest))->handle();

    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'unchanged',
    ]);
});

it('logs an error and updates status on request failure', function () {
    Http::fake([
        'app.ticketmaster.com/*' => Http::response('Unauthorized', 401),
    ]);
    Notification::fake();

    (new CheckTicketmasterEventOnSale($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('error');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'error',
    ]);
});
