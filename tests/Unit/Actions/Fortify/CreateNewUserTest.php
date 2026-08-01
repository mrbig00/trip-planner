<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

test('creates a user with a hashed password for valid input', function () {
    $user = (new CreateNewUser)->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    expect($user)->toBeInstanceOf(User::class);
    expect($user->first_name)->toBe('Jane');
    expect($user->last_name)->toBe('Doe');
    expect($user->email)->toBe('jane@example.com');
    expect($user->password)->not->toBe('password123');
    expect(Hash::check('password123', $user->password))->toBeTrue();
});

test('throws a validation exception for a duplicate email', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    (new CreateNewUser)->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
})->throws(ValidationException::class);

test('throws a validation exception for a mismatched password confirmation', function () {
    (new CreateNewUser)->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'different',
    ]);
})->throws(ValidationException::class);

test('throws a validation exception for a missing first name', function () {
    (new CreateNewUser)->create([
        'first_name' => '',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
})->throws(ValidationException::class);

test('throws a validation exception for a missing last name', function () {
    (new CreateNewUser)->create([
        'first_name' => 'Jane',
        'last_name' => '',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);
})->throws(ValidationException::class);

test('throws a validation exception for a weak password', function () {
    (new CreateNewUser)->create([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);
})->throws(ValidationException::class);
