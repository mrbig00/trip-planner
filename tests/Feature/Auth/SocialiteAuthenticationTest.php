<?php

use App\Models\User;
use App\Models\UserProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

test('redirect and callback routes reject unsupported providers', function (string $route) {
    $this->get(route($route, 'facebook'))->assertNotFound();
})->with(['socialite.redirect', 'socialite.callback']);

test('redirect route redirects to the provider', function () {
    Socialite::fake('google');

    $response = $this->get(route('socialite.redirect', 'google'));

    $response->assertRedirect();
});

test('callback creates a new user when no account matches the email', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'provider-id-123',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'email_verified' => true,
    ]));

    $response = $this->get(route('socialite.callback', 'google'));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Jane')
        ->and($user->last_name)->toBe('Doe')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->toBeNull();

    expect($user->providers()->where('provider', 'google')->where('provider_id', 'provider-id-123')->exists())->toBeTrue();
});

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

test('callback links an existing user found by email and logs them in', function () {
    $existing = User::factory()->unverified()->create(['email' => 'jane@example.com']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'provider-id-456',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'email_verified' => true,
    ]));

    $this->get(route('socialite.callback', 'google'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($existing->fresh());

    expect($existing->fresh()->email_verified_at)->not->toBeNull();
    expect(User::count())->toBe(1);
    expect(UserProvider::query()->where('user_id', $existing->id)->where('provider', 'google')->where('provider_id', 'provider-id-456')->exists())->toBeTrue();
});

test('callback redirects back to login with an error when the provider shares no email', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'provider-id-no-email',
        'name' => 'Jane Doe',
        'email' => null,
    ]));

    $response = $this->get(route('socialite.callback', 'google'));

    $response->assertRedirect(route('login', absolute: false));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('callback leaves a new google user unverified when the provider has not confirmed the email', function () {
    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'provider-id-unverified',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'email_verified' => false,
    ]));

    $this->get(route('socialite.callback', 'google'))
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'jane@example.com')->first();

    expect($user->email_verified_at)->toBeNull();
});

test('callback does not verify an existing unverified user when google has not confirmed the email', function () {
    $existing = User::factory()->unverified()->create(['email' => 'jane@example.com']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'provider-id-unverified-2',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'email_verified' => false,
    ]));

    $this->get(route('socialite.callback', 'google'));

    expect($existing->fresh()->email_verified_at)->toBeNull();
});

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

test('callback logs in an already-linked user without creating a duplicate', function () {
    $existing = User::factory()->create(['email' => 'jane@example.com']);
    $existing->providers()->create(['provider' => 'google', 'provider_id' => 'provider-id-789']);

    Socialite::fake('google', SocialiteUser::fake([
        'id' => 'provider-id-789',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]));

    $this->get(route('socialite.callback', 'google'))
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($existing);
    expect(User::count())->toBe(1);
    expect(UserProvider::count())->toBe(1);
});
