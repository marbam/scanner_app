<?php

use App\Jobs\CheckRyanairAvailability;
use App\Models\Interest;
use Illuminate\Support\Facades\Queue;

it('dispatches a check job for each enabled, watching ryanair interest', function () {
    Queue::fake();

    $watching = Interest::factory()->create(['provider' => 'ryanair', 'status' => 'watching', 'enabled' => true]);
    Interest::factory()->create(['provider' => 'ryanair', 'status' => 'released', 'enabled' => true]);
    Interest::factory()->create(['provider' => 'ryanair', 'status' => 'watching', 'enabled' => false]);

    Interest::where('provider', 'ryanair')
        ->where('enabled', true)
        ->where('status', '!=', 'released')
        ->each(fn (Interest $interest) => CheckRyanairAvailability::dispatch($interest));

    Queue::assertPushed(CheckRyanairAvailability::class, 1);
});
