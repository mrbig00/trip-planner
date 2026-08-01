<?php

use App\Models\Location;
use App\Models\LocationComment;
use App\Models\User;

test('a location comment belongs to a location', function () {
    $location = Location::factory()->create();
    $comment = LocationComment::factory()->create(['location_id' => $location->id]);

    expect($comment->location)->toBeInstanceOf(Location::class);
    expect($comment->location->id)->toBe($location->id);
});

test('a location comment belongs to a user', function () {
    $user = User::factory()->create();
    $comment = LocationComment::factory()->create(['user_id' => $user->id]);

    expect($comment->user)->toBeInstanceOf(User::class);
    expect($comment->user->id)->toBe($user->id);
});

test('a location persists its comment content', function () {
    $comment = LocationComment::factory()->create(['content' => 'Great spot!']);

    expect($comment->fresh()->content)->toBe('Great spot!');
});

test('a location returns comments in latest-first order', function () {
    $location = Location::factory()->create();
    $first = LocationComment::factory()->create(['location_id' => $location->id, 'created_at' => now()->subMinutes(10)]);
    $second = LocationComment::factory()->create(['location_id' => $location->id, 'created_at' => now()->subMinutes(5)]);
    $third = LocationComment::factory()->create(['location_id' => $location->id, 'created_at' => now()]);

    $ids = $location->comments()->pluck('id');

    expect($ids->all())->toBe([$third->id, $second->id, $first->id]);
});
