<?php

use App\Livewire\Facebook\Memories\Index;
use App\Models\FacebookPost;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('shows posts from the same day across every year', function () {
    $thisYear = FacebookPost::factory()->create(['posted_at' => '2024-08-24 10:00:00', 'source_index' => 1]);
    $lastYear = FacebookPost::factory()->create(['posted_at' => '2019-08-24 10:00:00', 'source_index' => 2]);
    $differentDay = FacebookPost::factory()->create(['posted_at' => '2019-08-25 10:00:00', 'source_index' => 3]);

    Livewire::test(Index::class, ['date' => '2024-08-24'])
        ->assertSee($thisYear->title)
        ->assertSee($lastYear->title)
        ->assertDontSee($differentDay->title);
});

test('previous and next day navigation shifts the browsed date', function () {
    $target = FacebookPost::factory()->create(['posted_at' => '2019-08-25 10:00:00', 'source_index' => 1]);

    Livewire::test(Index::class, ['date' => '2024-08-24'])
        ->call('nextDay')
        ->assertSet('date', '2024-08-25')
        ->assertSee($target->title);
});
