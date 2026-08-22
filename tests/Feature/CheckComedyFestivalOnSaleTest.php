<?php

use App\Jobs\CheckComedyFestivalOnSale;
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
        'provider' => 'wells_comedy_festival',
        'provider_params' => [
            'url' => 'https://www.wellscomfest.com/whats-on-by-day',
            'not_on_sale_text' => 'is now over',
        ],
        'status' => 'watching',
        'last_response_hash' => null,
    ]);
});

it('marks interest as released, records an alert, and notifies when the not-on-sale text disappears', function () {
    Http::fake([
        'www.wellscomfest.com/*' => Http::response('<html>Buy Tickets — 2027 line-up now on sale</html>', 200),
    ]);
    Notification::fake();

    (new CheckComedyFestivalOnSale($this->interest))->handle();

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

it('does not notify while the not-on-sale text is still present', function () {
    Http::fake([
        'www.wellscomfest.com/*' => Http::response('<html>The 2026 festival is now over</html>', 200),
    ]);
    Notification::fake();

    (new CheckComedyFestivalOnSale($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('watching');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'checked_no_release',
    ]);

    $this->assertDatabaseCount('alerts', 0);
});

it('skips parsing and notifying when the page is unchanged', function () {
    $body = '<html>The 2026 festival is now over</html>';

    $this->interest->update(['last_response_hash' => md5($body)]);

    Http::fake([
        'www.wellscomfest.com/*' => Http::response($body, 200),
    ]);
    Notification::fake();

    (new CheckComedyFestivalOnSale($this->interest))->handle();

    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'unchanged',
    ]);
});

it('logs an error and updates status on request failure', function () {
    Http::fake([
        'www.wellscomfest.com/*' => Http::response('Forbidden', 403),
    ]);
    Notification::fake();

    (new CheckComedyFestivalOnSale($this->interest))->handle();

    expect($this->interest->fresh())->status->toBe('error');
    Notification::assertNothingSent();

    $this->assertDatabaseHas('interest_checks', [
        'interest_id' => $this->interest->id,
        'outcome' => 'error',
    ]);
});
