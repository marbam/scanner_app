<?php

use App\Jobs\CheckComedyFestivalOnSale;
use App\Jobs\CheckRyanairAvailability;
use App\Jobs\CheckShowcaseCinemaOnSale;
use App\Jobs\CheckTicketmasterEventOnSale;
use App\Jobs\ScanBristolAdvertPlanningApplications;
use App\Jobs\SendHabitLogReminder;
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
})->dailyAt('09:00')->timezone('Europe/London');

Schedule::call(function () {
    Interest::where('provider', 'wells_comedy_festival')
        ->where('enabled', true)
        ->where('status', '!=', 'released')
        ->each(fn (Interest $interest) => CheckComedyFestivalOnSale::dispatch($interest));
})->dailyAt('09:00')->timezone('Europe/London');

Schedule::call(function () {
    Interest::where('provider', 'showcase_cinemas')
        ->where('enabled', true)
        ->where('status', '!=', 'released')
        ->each(fn (Interest $interest) => CheckShowcaseCinemaOnSale::dispatch($interest));
})->cron('0 9,13,17 * * *')->timezone('Europe/London');

Schedule::call(function () {
    Interest::where('provider', 'ticketmaster')
        ->where('enabled', true)
        ->where('status', '!=', 'released')
        ->each(fn (Interest $interest) => CheckTicketmasterEventOnSale::dispatch($interest));
})->cron('0 10,11 * * *')->timezone('Europe/London');

Schedule::job(ScanBristolAdvertPlanningApplications::class)->dailyAt('08:00')->timezone('Europe/London');

Schedule::job(SendHabitLogReminder::class)->dailyAt('20:00')->timezone('Europe/London');
