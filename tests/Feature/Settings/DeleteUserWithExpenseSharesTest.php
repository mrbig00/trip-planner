<?php

use App\Models\Expense;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('a user with outstanding expense shares cannot delete their account', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create(['password' => Hash::make('password')]);
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id);

    $expense = Expense::factory()->create([
        'trip_id' => $trip->id,
        'user_id' => $owner->id,
        'unit_price' => 20.00,
        'quantity' => 1,
    ]);
    $expense->shares()->createMany([
        ['user_id' => $owner->id, 'amount' => 10.00],
        ['user_id' => $participant->id, 'amount' => 10.00],
    ]);

    $this->actingAs($participant);

    Volt::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['password']);

    expect(User::find($participant->id))->not->toBeNull();
    expect($expense->shares()->where('user_id', $participant->id)->exists())->toBeTrue();
});

test('a user with no expense shares can still delete their account', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);
    $this->actingAs($user);

    Volt::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors();

    expect(User::find($user->id))->toBeNull();
});
