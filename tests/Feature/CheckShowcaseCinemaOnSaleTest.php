<?php

use App\Jobs\CheckShowcaseCinemaOnSale;
use App\Models\Interest;
use App\Notifications\InterestReleased;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config([
        'services.pushover.user_key' => str_repeat('u', 30),
        'services.pushover.token' => 'fake-app-token',
    ]);

    $this->interest = Interest::factory()->create([
        'provider' => 'showcase_cinemas',
        'provider_params' => [
            'movie_id' => '269068',
            'theater_code' => 'X06JH',
        ],
        'status' => 'watching',
        'last_response_hash' => null,
    ]);
});

it('marks interest as released, records an alert, and notifies when our theatre code appears', function () {
    Http::fake([
        'www.showcasecinemas.co.uk/*' => Http::response(['X06JS', 'X06JH', 'X06JI'], 200),
    ]);
    Notification::fake();

    (new CheckShowcaseCinemaOnSale($this->interest))->handle();

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

it('does not notify when other theatres are on sale but ours is not', function () {
    Http::fake([
        'www.showcasecinemas.co.uk/*' => Http::response(['X06JS', 'X06JI'], 200),
    ]);
    Notification::fake();

    (new CheckShowcaseCinemaOnSale($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('watching');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'checked_no_release',
    ]);

    $this->assertDatabaseCount('alerts', 0);
});

it('skips parsing and notifying when the response is unchanged', function () {
    $body = json_encode(['X06JS', 'X06JI']);

    $this->interest->update(['last_response_hash' => md5($body)]);

    Http::fake([
        'www.showcasecinemas.co.uk/*' => Http::response($body, 200),
    ]);
    Notification::fake();

    (new CheckShowcaseCinemaOnSale($this->interest))->handle();

    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'unchanged',
    ]);
});

it('logs an error and updates status on request failure', function () {
    Http::fake([
        'www.showcasecinemas.co.uk/*' => Http::response('Forbidden', 403),
    ]);
    Notification::fake();

    (new CheckShowcaseCinemaOnSale($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('error');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'error',
    ]);
});
