<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the owner user from config', function () {
    config([
        'owner.name' => 'Martin Bampton',
        'owner.email' => 'owner@example.com',
        'owner.password' => 'correct-horse-battery-staple',
    ]);

    $this->artisan('app:create-owner-user')->assertSuccessful();

    $user = User::where('email', 'owner@example.com')->first();

    expect($user)->not->toBeNull()
        ->name->toBe('Martin Bampton')
        ->email_verified_at->not->toBeNull();

    expect(Hash::check('correct-horse-battery-staple', $user->password))->toBeTrue();
});

it('updates the existing owner user rather than duplicating it', function () {
    $existing = User::factory()->create(['email' => 'owner@example.com']);

    config([
        'owner.name' => 'Updated Name',
        'owner.email' => 'owner@example.com',
        'owner.password' => 'a-new-password-value',
    ]);

    $this->artisan('app:create-owner-user')->assertSuccessful();

    expect(User::count())->toBe(1);

    $existing->refresh();
    expect($existing->name)->toBe('Updated Name');
});

it('fails validation when required env values are missing', function () {
    config([
        'owner.name' => null,
        'owner.email' => null,
        'owner.password' => null,
    ]);

    $this->artisan('app:create-owner-user')->assertFailed();

    expect(User::count())->toBe(0);
});
