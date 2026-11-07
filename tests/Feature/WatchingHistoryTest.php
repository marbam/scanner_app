<?php

use App\Livewire\Watching\History;
use App\Models\User;
use App\Models\WatchEntry;
use App\Models\WatchTitle;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('counts each film once however many times it was watched, plus cinema trips', function () {
    $dune = WatchTitle::factory()->create(['title' => 'Dune']);
    $alien = WatchTitle::factory()->create(['title' => 'Alien']);
    $show = WatchTitle::factory()->tv()->create(['title' => 'Severance']);

    WatchEntry::factory()->for($dune, 'title')->create(['watched_on' => '2026-03-01', 'location' => WatchEntry::CINEMA]);
    WatchEntry::factory()->for($dune, 'title')->create(['watched_on' => '2026-03-02']);
    WatchEntry::factory()->for($alien, 'title')->create(['watched_on' => '2025-12-31']);
    WatchEntry::factory()->for($show, 'title')->count(3)->create(['watched_on' => '2026-02-01', 'season' => 1, 'location' => null]);

    Livewire::test(History::class)
        ->set('year', '2026')
        ->assertSet('stats', ['films' => 1, 'cinema' => 1, 'shows' => 1, 'episodes' => 3])
        ->assertSee('Dune')
        ->assertDontSee('Alien')
        ->set('year', '')
        ->assertSet('stats', ['films' => 2, 'cinema' => 1, 'shows' => 1, 'episodes' => 3]);
});

test('search and type filters narrow the list', function () {
    WatchEntry::factory()->for(WatchTitle::factory()->create(['title' => 'Dune']), 'title')->create();
    WatchEntry::factory()->for(WatchTitle::factory()->tv()->create(['title' => 'Severance']), 'title')->create(['location' => null]);

    Livewire::test(History::class)
        ->set('search', 'dun')
        ->assertSee('Dune')
        ->assertDontSee('Severance')
        ->set('search', '')
        ->set('type', WatchTitle::TV)
        ->assertSee('Severance')
        ->assertDontSee('Dune');
});
