<?php

use App\Models\Trip;
use App\Models\User;

test('the trip creator always resolves to color slot 1', function () {
    $owner = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);

    expect($trip->colorSlotFor($owner))->toBe(1);
});

test('a participant resolves to their stored pivot color slot', function () {
    $owner = User::factory()->create();
    $participant = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);
    $trip->participants()->attach($participant->id, ['color_slot' => 5]);

    expect($trip->fresh(['participants'])->colorSlotFor($participant))->toBe(5);
});

test('a non-member resolves to no color slot', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $trip = Trip::factory()->create(['user_id' => $owner->id]);

    expect($trip->colorSlotFor($stranger))->toBeNull();
});
