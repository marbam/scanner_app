<?php

use App\Jobs\CheckRyanairAvailability;
use App\Models\Interest;
use App\Notifications\InterestReleased;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->interest = Interest::factory()->create([
        'provider' => 'ryanair',
        'provider_params' => ['origin' => 'BRS', 'destination' => 'VLC', 'month' => '2027-03-01'],
        'status' => 'watching',
        'last_response_hash' => null,
    ]);
});

it('marks interest as released, records an alert, and notifies when fares appear', function () {
    Http::fake([
        'services-api.ryanair.com/*' => Http::response([
            'outbound' => ['fares' => [['day' => 28, 'price' => ['value' => 45.99]]]],
        ], 200),
    ]);
    Notification::fake();

    (new CheckRyanairAvailability($this->interest))->handle();

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

it('does not notify when fares array is empty', function () {
    Http::fake([
        'services-api.ryanair.com/*' => Http::response(['outbound' => ['fares' => []]], 200),
    ]);
    Notification::fake();

    (new CheckRyanairAvailability($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('watching');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'checked_no_release',
    ]);

    $this->assertDatabaseCount('alerts', 0);
});

it('skips parsing and notifying when response is unchanged', function () {
    $body = json_encode(['outbound' => ['fares' => []]]);

    $this->interest->update(['last_response_hash' => md5($body)]);

    Http::fake([
        'services-api.ryanair.com/*' => Http::response(json_decode($body, true), 200),
    ]);
    Notification::fake();

    (new CheckRyanairAvailability($this->interest))->handle();

    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'unchanged',
    ]);
});

it('logs an error and updates status on request failure', function () {
    Http::fake([
        'services-api.ryanair.com/*' => Http::response('Forbidden', 403),
    ]);
    Notification::fake();

    (new CheckRyanairAvailability($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('error');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'error',
    ]);
});
