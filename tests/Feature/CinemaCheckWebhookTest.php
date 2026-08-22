<?php

use App\Models\Interest;
use App\Notifications\InterestReleased;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.pushover.user_key' => str_repeat('u', 30),
        'services.pushover.token' => 'fake-app-token',
        'services.cinema_webhook.token' => 'test-webhook-token',
    ]);

    $this->interest = Interest::factory()->create([
        'provider' => 'odeon',
        'provider_params' => [
            'cinema_url' => 'https://www.odeon.co.uk/cinemas/bristol/',
            'film_title' => 'Avengers: Doomsday',
        ],
        'status' => 'watching',
        'last_response_hash' => null,
    ]);
});

it('rejects requests without a valid webhook token', function () {
    $response = $this->postJson('/api/cinema-checks', [
        'interest_id' => $this->interest->id,
        'on_sale' => true,
        'snapshot' => 'Avengers: Doomsday Book Now',
    ]);

    $response->assertUnauthorized();
});

it('marks interest as released, records an alert, and notifies when on_sale is true', function () {
    Notification::fake();

    $response = $this->postJson('/api/cinema-checks', [
        'interest_id' => $this->interest->id,
        'on_sale' => true,
        'snapshot' => 'Avengers: Doomsday Book Now',
    ], ['X-Webhook-Token' => 'test-webhook-token']);

    $response->assertOk();

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

it('does not notify when on_sale is false', function () {
    Notification::fake();

    $response = $this->postJson('/api/cinema-checks', [
        'interest_id' => $this->interest->id,
        'on_sale' => false,
        'snapshot' => 'Sorry, there are no showtimes available for this film',
    ], ['X-Webhook-Token' => 'test-webhook-token']);

    $response->assertOk();

    expect($this->interest->fresh())->status->toBe('watching');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'checked_no_release',
    ]);

    $this->assertDatabaseCount('alerts', 0);
});

it('skips notifying when the snapshot is unchanged', function () {
    $snapshot = 'Sorry, there are no showtimes available for this film';

    $this->interest->update(['last_response_hash' => md5($snapshot)]);

    Notification::fake();

    $response = $this->postJson('/api/cinema-checks', [
        'interest_id' => $this->interest->id,
        'on_sale' => false,
        'snapshot' => $snapshot,
    ], ['X-Webhook-Token' => 'test-webhook-token']);

    $response->assertOk();
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'unchanged',
    ]);
});
