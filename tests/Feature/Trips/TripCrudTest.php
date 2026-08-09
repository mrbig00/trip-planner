<?php

use App\Models\Trip;
use App\Models\User;
use Livewire\Volt\Volt;

test('guests cannot access trips', function () {
    $response = $this->get(route('trips.index'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view their trips', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $myTrip = Trip::factory()->create(['user_id' => $user->id]);
    $otherTrip = Trip::factory()->create();

    $response = $this->get(route('trips.index'));
    $response->assertSuccessful();
    $response->assertSee($myTrip->name);
});

test('users can create a trip', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Summer Vacation')
        ->set('description', 'A fun summer trip')
        ->call('store')
        ->assertRedirect(route('trips.show', Trip::where('name', 'Summer Vacation')->first()));

    expect(Trip::where('name', 'Summer Vacation')->exists())->toBeTrue();
});

test('trip creation requires name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', '')
        ->set('description', 'A fun summer trip')
        ->call('store')
        ->assertHasErrors(['name']);
});

test('users can view trip details', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    $response = $this->get(route('trips.show', $trip));
    $response->assertSuccessful();
    $response->assertSee($trip->name);
});

test('users can edit their own trips', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->set('name', 'Updated Trip Name')
        ->set('description', 'Updated description')
        ->call('update')
        ->assertRedirect(route('trips.show', $trip));

    expect($trip->fresh()->name)->toBe('Updated Trip Name');
    expect($trip->fresh()->description)->toBe('Updated description');
});

test('users cannot edit other users trips', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $otherUser->id]);
    $this->actingAs($user);

    $response = $this->get(route('trips.edit', $trip));
    $response->assertForbidden();
});

test('users can delete their own trips', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id]);
    $this->actingAs($user);

    Volt::test('trips.show', ['trip' => $trip])
        ->call('delete')
        ->assertRedirect(route('trips.index'));

    expect(Trip::find($trip->id))->toBeNull();
});

test('users cannot delete other users trips', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $otherUser->id]);
    $this->actingAs($user);

    $response = Volt::test('trips.show', ['trip' => $trip])
        ->call('delete');

    expect(Trip::find($trip->id))->not->toBeNull();
});

test('users can see trips they participate in', function () {
    $user = User::factory()->create();
    $creator = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $creator->id]);
    $trip->participants()->attach($user->id);
    $this->actingAs($user);

    $response = $this->get(route('trips.index'));
    $response->assertSuccessful();
    $response->assertSee($trip->name);
});

test('trip description is optional', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Trip Without Description')
        ->set('description', '')
        ->call('store')
        ->assertRedirect(route('trips.show', Trip::where('name', 'Trip Without Description')->first()));

    $trip = Trip::where('name', 'Trip Without Description')->first();
    expect($trip->description ?? '')->toBe('');
});

test('trip dates are optional and default to null on creation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Undated Trip')
        ->call('store')
        ->assertHasNoErrors();

    $trip = Trip::where('name', 'Undated Trip')->first();
    expect($trip->start_date)->toBeNull();
    expect($trip->end_date)->toBeNull();
});

test('users can create a trip with a start and end date', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Dated Trip')
        ->set('start_date', '2026-01-10')
        ->set('end_date', '2026-01-20')
        ->call('store')
        ->assertHasNoErrors();

    $trip = Trip::where('name', 'Dated Trip')->first();
    expect($trip->start_date->toDateString())->toBe('2026-01-10');
    expect($trip->end_date->toDateString())->toBe('2026-01-20');
});

test('trip creation requires the end date to be on or after the start date', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Backwards Trip')
        ->set('start_date', '2026-01-20')
        ->set('end_date', '2026-01-10')
        ->call('store')
        ->assertHasErrors(['end_date']);

    expect(Trip::where('name', 'Backwards Trip')->exists())->toBeFalse();
});

test('editing a trip hydrates its existing start and end dates', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create([
        'user_id' => $user->id,
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-10',
    ]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->assertSet('start_date', '2026-02-01')
        ->assertSet('end_date', '2026-02-10');
});

test('editing a trip preserves null dates when none are set', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create([
        'user_id' => $user->id,
        'start_date' => null,
        'end_date' => null,
    ]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->assertSet('start_date', null)
        ->assertSet('end_date', null)
        ->call('update')
        ->assertHasNoErrors();

    expect($trip->fresh()->start_date)->toBeNull();
    expect($trip->fresh()->end_date)->toBeNull();
});

test('users can update a trip\'s start and end dates', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'start_date' => null, 'end_date' => null]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->set('start_date', '2026-03-05')
        ->set('end_date', '2026-03-15')
        ->call('update')
        ->assertRedirect(route('trips.show', $trip));

    expect($trip->fresh()->start_date->toDateString())->toBe('2026-03-05');
    expect($trip->fresh()->end_date->toDateString())->toBe('2026-03-15');
});

test('trip update requires the end date to be on or after the start date', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'start_date' => null, 'end_date' => null]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->set('start_date', '2026-03-15')
        ->set('end_date', '2026-03-05')
        ->call('update')
        ->assertHasErrors(['end_date']);
});

test('trip budget is optional and defaults to null on creation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Budgetless Trip')
        ->call('store')
        ->assertHasNoErrors();

    $trip = Trip::where('name', 'Budgetless Trip')->first();
    expect($trip->budget)->toBeNull();
});

test('users can create a trip with a budget', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Budgeted Trip')
        ->set('budget', '1500.50')
        ->call('store')
        ->assertHasNoErrors();

    $trip = Trip::where('name', 'Budgeted Trip')->first();
    expect((string) $trip->budget)->toBe('1500.50');
});

test('trip creation rejects a negative budget', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('trips.create')
        ->set('name', 'Negative Budget Trip')
        ->set('budget', '-10')
        ->call('store')
        ->assertHasErrors(['budget']);

    expect(Trip::where('name', 'Negative Budget Trip')->exists())->toBeFalse();
});

test('editing a trip hydrates its existing budget', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'budget' => 2000]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->assertSet('budget', '2000.00');
});

test('editing a trip preserves a null budget when none is set', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'budget' => null]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->assertSet('budget', null)
        ->call('update')
        ->assertHasNoErrors();

    expect($trip->fresh()->budget)->toBeNull();
});

test('users can update a trip\'s budget', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'budget' => null]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->set('budget', '999.99')
        ->call('update')
        ->assertRedirect(route('trips.show', $trip));

    expect((string) $trip->fresh()->budget)->toBe('999.99');
});

test('clearing an existing budget on edit sets it to null', function () {
    $user = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $user->id, 'budget' => 2000]);
    $this->actingAs($user);

    Volt::test('trips.edit', ['trip' => $trip])
        ->set('budget', '')
        ->call('update')
        ->assertHasNoErrors()
        ->assertRedirect(route('trips.show', $trip));

    expect($trip->fresh()->budget)->toBeNull();
});
