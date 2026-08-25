<?php

use App\Livewire\Twitter\Memories\Index;
use App\Models\Tweet;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('shows tweets from the same day across every year', function () {
    $thisYear = Tweet::factory()->create(['posted_at' => '2024-08-24 10:00:00', 'tweet_id' => '1']);
    $lastYear = Tweet::factory()->create(['posted_at' => '2019-08-24 10:00:00', 'tweet_id' => '2']);
    $differentDay = Tweet::factory()->create(['posted_at' => '2019-08-25 10:00:00', 'tweet_id' => '3']);

    Livewire::test(Index::class, ['date' => '2024-08-24'])
        ->assertSee($thisYear->body)
        ->assertSee($lastYear->body)
        ->assertDontSee($differentDay->body);
});

test('previous and next day navigation shifts the browsed date', function () {
    $target = Tweet::factory()->create(['posted_at' => '2019-08-25 10:00:00', 'tweet_id' => '1']);

    Livewire::test(Index::class, ['date' => '2024-08-24'])
        ->call('nextDay')
        ->assertSet('date', '2024-08-25')
        ->assertSee($target->body);
});
