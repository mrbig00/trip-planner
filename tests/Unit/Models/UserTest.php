<?php

use App\Models\Location;
use App\Models\User;

test('fullName joins first and last name with a space', function () {
    $user = User::factory()->make(['first_name' => 'Jane', 'last_name' => 'Doe']);

    expect($user->fullName())->toBe('Jane Doe');
});

test('fullName trims when last name is empty', function () {
    $user = User::factory()->make(['first_name' => 'Jane', 'last_name' => '']);

    expect($user->fullName())->toBe('Jane');
});

test('fullName trims when first name is empty', function () {
    $user = User::factory()->make(['first_name' => '', 'last_name' => 'Doe']);

    expect($user->fullName())->toBe('Doe');
});

test('fullName is empty when both names are empty', function () {
    $user = User::factory()->make(['first_name' => '', 'last_name' => '']);

    expect($user->fullName())->toBe('');
});

test('initials returns uppercase two-character combo', function () {
    $user = User::factory()->make(['first_name' => 'jane', 'last_name' => 'doe']);

    expect($user->initials())->toBe('JD');
});

test('initials handles a null last name', function () {
    $user = User::factory()->make(['first_name' => 'Jane', 'last_name' => null]);

    expect($user->initials())->toBe('J');
});

test('initials handles a null first name', function () {
    $user = User::factory()->make(['first_name' => null, 'last_name' => 'Doe']);

    expect($user->initials())->toBe('D');
});

test('initials is empty when both names are empty', function () {
    $user = User::factory()->make(['first_name' => '', 'last_name' => '']);

    expect($user->initials())->toBe('');
});

test('votedLocations returns locations the user has voted for', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create();

    $user->votedLocations()->attach($location->id);

    expect($user->votedLocations()->pluck('locations.id'))->toContain($location->id);
});

test('votedLocations does not include locations the user has not voted for', function () {
    $user = User::factory()->create();
    Location::factory()->create();

    expect($user->votedLocations()->count())->toBe(0);
});
