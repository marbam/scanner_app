<?php

use App\Livewire\Interests\Index;
use App\Models\Interest;
use App\Models\InterestCheck;
use App\Models\User;
use Livewire\Livewire;

it('redirects guests to login', function () {
    $this->get(route('scans.index'))->assertRedirect(route('login'));
});

it('lists interests and their recent checks for an authenticated user', function () {
    $interest = Interest::factory()->create(['name' => 'BRS to VLC, March 2027']);
    $check = InterestCheck::factory()->for($interest)->create(['outcome' => 'checked_no_release']);

    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->assertSee('BRS to VLC, March 2027')
        ->assertSee('checked_no_release');
});

it('toggles an interest enabled state', function () {
    $interest = Interest::factory()->create(['enabled' => true]);

    Livewire::actingAs(User::factory()->create())
        ->test(Index::class)
        ->call('toggleEnabled', $interest->id);

    expect($interest->fresh()->enabled)->toBeFalse();
});
