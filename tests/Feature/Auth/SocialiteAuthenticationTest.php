<?php

use App\Models\User;
use App\Models\UserProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('redirect route redirects to the provider', function (string $provider) {
    Socialite::fake($provider);

    $response = $this->get(route('socialite.redirect', $provider));

    $response->assertRedirect();
})->with(['google', 'facebook']);

test('callback creates a new user when no account matches the email', function (string $provider) {
    Socialite::fake($provider, SocialiteUser::fake([
        'id' => 'provider-id-123',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]));

    $response = $this->get(route('socialite.callback', $provider));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Jane')
        ->and($user->last_name)->toBe('Doe')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();

    expect($user->providers()->where('provider', $provider)->where('provider_id', 'provider-id-123')->exists())->toBeTrue();
})->with(['google', 'facebook']);

test('callback splits a single-word name and falls back to email when no name is given', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'provider-id-999',
        'name' => '',
        'nickname' => '',
        'email' => 'solo@example.com',
    ]));

    $this->get(route('socialite.callback', 'google'))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'solo@example.com')->first();

    expect($user->first_name)->toBe('solo@example.com')
        ->and($user->last_name)->toBe('');
});

test('callback links an existing user found by email and logs them in', function (string $provider) {
    $existing = User::factory()->unverified()->create(['email' => 'jane@example.com']);

    Socialite::fake($provider, SocialiteUser::fake([
        'id' => 'provider-id-456',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]));

    $this->get(route('socialite.callback', $provider))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($existing->fresh());

    expect($existing->fresh()->email_verified_at)->not->toBeNull();
    expect(User::count())->toBe(1);
    expect(UserProvider::query()->where('user_id', $existing->id)->where('provider', $provider)->where('provider_id', 'provider-id-456')->exists())->toBeTrue();
})->with(['google', 'facebook']);

test('callback does not overwrite an already verified email when linking', function () {
    $verifiedAt = now()->subDay();
    $existing = User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => $verifiedAt]);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'provider-id-456',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]));

    $this->get(route('socialite.callback', 'google'));

    expect($existing->fresh()->email_verified_at->timestamp)->toBe($verifiedAt->timestamp);
});

test('callback logs in an already-linked user without creating a duplicate', function (string $provider) {
    $existing = User::factory()->create(['email' => 'jane@example.com']);
    $existing->providers()->create(['provider' => $provider, 'provider_id' => 'provider-id-789']);

    Socialite::fake($provider, SocialiteUser::fake([
        'id' => 'provider-id-789',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]));

    $this->get(route('socialite.callback', $provider))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($existing);
    expect(User::count())->toBe(1);
    expect(UserProvider::count())->toBe(1);
})->with(['google', 'facebook']);

test('a user can link both google and facebook and log in via either', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);

    Socialite::fake('google', SocialiteUser::fake(['id' => 'google-id', 'email' => 'jane@example.com', 'name' => 'Jane Doe']));
    $this->get(route('socialite.callback', 'google'));
    auth()->logout();

    Socialite::fake('facebook', SocialiteUser::fake(['id' => 'facebook-id', 'email' => 'jane@example.com', 'name' => 'Jane Doe']));
    $this->get(route('socialite.callback', 'facebook'));

    expect(User::count())->toBe(1)
        ->and($user->providers()->count())->toBe(2);

    $this->assertAuthenticatedAs($user->fresh());
});
