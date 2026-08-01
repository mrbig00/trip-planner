<?php

use App\Models\Trip;
use App\Models\User;
use Livewire\Livewire;
use App\Models\Expense;
use App\Models\Location;
use App\Livewire\Dashboard;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertStatus(200);
});

test('dashboard shows an empty state when the user has no trips', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSee("You haven't created or joined any trips yet.");
});

test('dashboard shows stats for trips the user created or participates in', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $createdTrip = Trip::factory()->for($user, 'creator')->create(['start_date' => null, 'end_date' => null]);
    Location::factory()->for($createdTrip)->create(['accepted' => true]);
    Location::factory()->for($createdTrip)->create(['accepted' => false]);
    Expense::factory()->for($createdTrip)->create(['unit_price' => 100, 'quantity' => 2]);

    $otherUser = User::factory()->create();
    $participatingTrip = Trip::factory()->for($otherUser, 'creator')->create();
    $participatingTrip->participants()->attach($user);

    Livewire::test(Dashboard::class)
        ->assertViewHas('stats', function (array $stats) {
            return $stats['totalTrips'] === 2
                && (float) $stats['totalSpend'] === 200.0
                && $stats['acceptedDestinations'] === 1
                && $stats['proposedDestinations'] === 1;
        });
});
