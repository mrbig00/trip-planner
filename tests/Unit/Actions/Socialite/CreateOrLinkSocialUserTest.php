<?php

use App\Actions\Socialite\CreateOrLinkSocialUser;
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
