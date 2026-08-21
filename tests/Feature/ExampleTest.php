<?php

use App\Models\User;

test('redirects guests to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('redirects authenticated users to the dashboard', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
