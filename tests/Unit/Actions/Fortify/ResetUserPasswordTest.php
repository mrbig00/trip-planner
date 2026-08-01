<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

test('resets and persists a hashed new password', function () {
    $user = User::factory()->create();
    $oldHash = $user->password;

    (new ResetUserPassword)->reset($user, [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $user->refresh();

    expect($user->password)->not->toBe($oldHash);
    expect(Hash::check('new-password-123', $user->password))->toBeTrue();
});

test('throws a validation exception for a mismatched confirmation', function () {
    $user = User::factory()->create();

    (new ResetUserPassword)->reset($user, [
        'password' => 'new-password-123',
        'password_confirmation' => 'different',
    ]);
})->throws(ValidationException::class);

test('throws a validation exception for a weak password', function () {
    $user = User::factory()->create();

    (new ResetUserPassword)->reset($user, [
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);
})->throws(ValidationException::class);
