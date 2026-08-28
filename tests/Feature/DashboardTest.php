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
                && (float) $stats['totalSpendByCurrency']['USD'] === 200.0
                && $stats['acceptedDestinations'] === 1
                && $stats['proposedDestinations'] === 1;
        });
});

test('dashboard only counts and lists trips with a future start date as upcoming', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Trip::factory()->for($user, 'creator')->create(['name' => 'No Date', 'start_date' => null, 'end_date' => null]);
    Trip::factory()->for($user, 'creator')->create(['name' => 'Past Trip', 'start_date' => now()->subDays(5), 'end_date' => now()->subDays(1)]);
    $soonest = Trip::factory()->for($user, 'creator')->create(['name' => 'Soonest', 'start_date' => now(), 'end_date' => now()->addDays(2)]);
    $later = Trip::factory()->for($user, 'creator')->create(['name' => 'Later', 'start_date' => now()->addDays(10), 'end_date' => now()->addDays(12)]);

    Livewire::test(Dashboard::class)
        ->assertViewHas('stats', fn (array $stats) => $stats['upcomingTrips'] === 2)
        ->assertViewHas('upcomingTrips', function ($upcomingTrips) use ($soonest, $later) {
            return $upcomingTrips->pluck('id')->all() === [$soonest->id, $later->id];
        });
});

test('dashboard ranks trips by total spend and excludes trips with no expenses', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $bigSpend = Trip::factory()->for($user, 'creator')->create(['name' => 'Big Spend']);
    Expense::factory()->for($bigSpend)->create(['unit_price' => 500, 'quantity' => 1]);

    $smallSpend = Trip::factory()->for($user, 'creator')->create(['name' => 'Small Spend']);
    Expense::factory()->for($smallSpend)->create(['unit_price' => 100, 'quantity' => 1]);

    Trip::factory()->for($user, 'creator')->create(['name' => 'No Spend']);

    Livewire::test(Dashboard::class)
        ->assertViewHas('spendByTrip', function (array $spendByTrip) {
            return $spendByTrip['labels'] === ['Big Spend (USD)', 'Small Spend (USD)']
                && $spendByTrip['data'] === [500.0, 100.0];
        });
});

test('dashboard buckets trips created per month over the last 12 months', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $recentTrip = Trip::factory()->for($user, 'creator')->create();

    $olderTrip = Trip::factory()->for($user, 'creator')->create();
    $olderTrip->created_at = now()->subMonths(2);
    $olderTrip->save();

    Livewire::test(Dashboard::class)
        ->assertViewHas('tripsPerMonth', function (array $tripsPerMonth) {
            return $tripsPerMonth['labels'][11] === now()->format('M Y')
                && $tripsPerMonth['data'][11] === 1
                && $tripsPerMonth['data'][9] === 1;
        });
});
