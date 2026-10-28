<?php

use App\Livewire\Games\Played;
use App\Models\SteamGame;
use App\Models\SteamGamePlay;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(Carbon::parse('2026-10-29 09:00', 'Europe/London'));
});

function logPlay(SteamGame $game, string $date, int $minutes): void
{
    SteamGamePlay::create(['steam_game_id' => $game->id, 'played_on' => $date, 'minutes' => $minutes]);
}

test('the page loads from its route', function () {
    $this->get(route('games.played'))->assertOk()->assertSee('Played');
});

test('lists each day newest first with every game played that day', function () {
    $hades = SteamGame::factory()->create(['name' => 'Hades']);
    $balatro = SteamGame::factory()->create(['name' => 'Balatro']);
    $zomboid = SteamGame::factory()->create(['name' => 'Project Zomboid']);

    logPlay($hades, '2026-10-28', 47);
    logPlay($balatro, '2026-10-28', 12);
    logPlay($zomboid, '2026-10-28', 95);
    logPlay($hades, '2026-10-25', 30);

    Livewire::test(Played::class)
        ->assertSeeInOrder([
            'Wed 28 Oct 2026', '3 games', 'Project Zomboid', '1h 35m', 'Hades', '47m', 'Balatro', '12m',
            'Sun 25 Oct 2026', '1 game', 'Hades', '30m',
        ]);
});

test('only shows days inside the chosen range', function () {
    $game = SteamGame::factory()->create(['name' => 'Hades']);
    logPlay($game, '2026-10-28', 10);
    logPlay($game, '2026-09-01', 10);

    Livewire::test(Played::class)
        ->assertSee('Wed 28 Oct 2026')
        ->assertDontSee('1 Sep 2026')
        ->call('setDays', 90)
        ->assertSet('days', 90)
        ->assertSee('Tue 1 Sep 2026');
});

test('ignores ranges that are not on offer', function () {
    Livewire::test(Played::class)
        ->call('setDays', 5000)
        ->assertSet('days', 30);
});

test('explains where the data comes from when nothing is recorded', function () {
    Livewire::test(Played::class)->assertSee('Nothing recorded in this period yet');
});
