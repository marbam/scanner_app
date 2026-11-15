<?php

use App\Livewire\Dashboard\Index as Dashboard;
use App\Models\DayHighlight;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows highlights from a week ago, a month ago and this day in past years', function () {
    $this->actingAs(User::factory()->create());
    $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));

    DayHighlight::create(['date' => '2026-11-08', 'body' => 'Week-old highlight']);
    DayHighlight::create(['date' => '2026-10-15', 'body' => 'Month-old highlight']);
    DayHighlight::create(['date' => '2025-11-15', 'body' => 'Year-old highlight']);
    DayHighlight::create(['date' => '2026-11-15', 'body' => 'Today highlight']);
    DayHighlight::create(['date' => '2026-11-10', 'body' => 'Unrelated highlight']);

    Livewire::test(Dashboard::class)
        ->assertSeeInOrder(['Your highlights', 'A week ago', 'Week-old highlight', 'A month ago', 'Month-old highlight', '1 year ago', 'Year-old highlight'])
        ->assertDontSee('Today highlight')
        ->assertDontSee('Unrelated highlight');
});

test('the highlights card says when there is nothing to show', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSee('No highlights from a week ago, a month ago or this day in past years yet.');
});
