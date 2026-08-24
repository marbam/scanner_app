<?php

use App\Livewire\Facebook\Posts\Index;
use App\Models\FacebookPost;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('lists posts newest first', function () {
    $older = FacebookPost::factory()->create(['posted_at' => now()->subYear(), 'source_index' => 1]);
    $newer = FacebookPost::factory()->create(['posted_at' => now(), 'source_index' => 2]);

    Livewire::test(Index::class)
        ->assertSeeInOrder([$newer->title, $older->title]);
});

test('search filters posts by title and body', function () {
    FacebookPost::factory()->create(['title' => 'Holiday photos', 'body' => null, 'source_index' => 1]);
    FacebookPost::factory()->create(['title' => 'Random update', 'body' => 'Just a normal day', 'source_index' => 2]);

    Livewire::test(Index::class)
        ->set('search', 'Holiday')
        ->assertSee('Holiday photos')
        ->assertDontSee('Random update');
});

test('deleting a post soft deletes it and hides it from the list', function () {
    $post = FacebookPost::factory()->create(['source_index' => 1]);

    Livewire::test(Index::class)
        ->call('delete', $post->id)
        ->assertDontSee($post->title);

    expect(FacebookPost::find($post->id))->toBeNull();
    expect(FacebookPost::withTrashed()->find($post->id))->not->toBeNull();
});
