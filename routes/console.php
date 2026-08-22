<?php

use App\Jobs\CheckComedyFestivalOnSale;
use App\Jobs\CheckRyanairAvailability;
use App\Models\Interest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    Interest::where('provider', 'ryanair')
        ->where('enabled', true)
        ->where('status', '!=', 'released')
        ->each(fn (Interest $interest) => CheckRyanairAvailability::dispatch($interest));
})->twiceDaily(8, 18);

Schedule::call(function () {
    Interest::where('provider', 'wells_comedy_festival')
        ->where('enabled', true)
        ->where('status', '!=', 'released')
        ->each(fn (Interest $interest) => CheckComedyFestivalOnSale::dispatch($interest));
})->twiceDaily(8, 18);
