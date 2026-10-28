<?php

use App\Jobs\CheckComedyFestivalOnSale;
use App\Jobs\CheckRyanairAvailability;
use App\Jobs\CheckShowcaseCinemaOnSale;
use App\Jobs\CheckTicketmasterEventOnSale;
use App\Jobs\FetchSteamAchievements;
use App\Jobs\FetchSteamGameDetails;
use App\Jobs\ScanBristolAdvertPlanningApplications;
use App\Jobs\SendDueReminders;
use App\Jobs\SendHabitLogReminder;
use App\Jobs\SyncSteamLibrary;
use App\Models\Alert;
use App\Models\Interest;
use App\Models\InterestCheck;
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

Schedule::job(SendDueReminders::class)->everyMinute()->timezone('Europe/London');

Schedule::job(SyncSteamLibrary::class)->dailyAt('04:00')->timezone('Europe/London');

// Picks up details/tags for newly bought games; a no-op once everything's filled in.
Schedule::job(new FetchSteamGameDetails(limit: 50))->hourlyAt(15);

// Only re-checks games played since their last fetch (plus any never fetched).
Schedule::job(new FetchSteamAchievements(limit: 100))->hourlyAt(45);

Schedule::command('model:prune', ['--model' => [Alert::class, InterestCheck::class]])
    ->dailyAt('03:00')
    ->timezone('Europe/London');
