<?php

use App\Actions\Socialite\CreateOrLinkSocialUser;
use App\Exceptions\SocialiteAuthenticationException;
use App\Models\User;
use Laravel\Socialite\Two\User as SocialiteUser;

test('creates a new user splitting a multi-word name on the first space', function () {
    $user = (new CreateOrLinkSocialUser)->handle('google', SocialiteUser::fake([
        'id' => 'id-1',
        'name' => 'Jane Middle Doe',
        'email' => 'jane@example.com',
    ]));

    expect($user->first_name)->toBe('Jane')
        ->and($user->last_name)->toBe('Middle Doe');
});

test('falls back to the nickname when no name is given', function () {
    $user = (new CreateOrLinkSocialUser)->handle('google', SocialiteUser::fake([
        'id' => 'id-2',
        'name' => '',
        'nickname' => 'janedoe',
        'email' => 'jane2@example.com',
    ]));

    expect($user->first_name)->toBe('janedoe')
        ->and($user->last_name)->toBe('');
});

test('throws when the provider does not share an email address', function () {
    $socialiteUser = SocialiteUser::fake([
        'id' => 'id-no-email',
        'name' => 'Jane Doe',
        'email' => null,
    ]);

    (new CreateOrLinkSocialUser)->handle('google', $socialiteUser);
})->throws(SocialiteAuthenticationException::class);

test('does not create a user when the provider shares no email address', function () {
    try {
        (new CreateOrLinkSocialUser)->handle('google', SocialiteUser::fake([
            'id' => 'id-no-email-2',
            'email' => null,
        ]));
    } catch (SocialiteAuthenticationException) {
        // expected
    }

    expect(User::count())->toBe(0);
});

test('marks a new google user verified only when the provider confirms the email', function () {
    $verified = (new CreateOrLinkSocialUser)->handle('google', SocialiteUser::fake([
        'id' => 'id-google-verified',
        'email' => 'verified@example.com',
        'email_verified' => true,
    ]));

    $unverified = (new CreateOrLinkSocialUser)->handle('google', SocialiteUser::fake([
        'id' => 'id-google-unverified',
        'email' => 'unverified@example.com',
        'email_verified' => false,
    ]));

    expect($verified->email_verified_at)->not->toBeNull()
        ->and($unverified->email_verified_at)->toBeNull();
});

test('always marks a new facebook user verified', function () {
    $user = (new CreateOrLinkSocialUser)->handle('facebook', SocialiteUser::fake([
        'id' => 'id-facebook',
        'email' => 'facebook-user@example.com',
    ]));

    expect($user->email_verified_at)->not->toBeNull();
});

test('returns the existing user without creating a duplicate for a repeat call', function () {
    $socialiteUser = SocialiteUser::fake([
        'id' => 'id-3',
        'name' => 'Jane Doe',
        'email' => 'jane3@example.com',
    ]);

    $action = new CreateOrLinkSocialUser;
    $first = $action->handle('google', $socialiteUser);
    $second = $action->handle('google', $socialiteUser);

    expect($second->is($first))->toBeTrue();
    expect(User::count())->toBe(1);
});
