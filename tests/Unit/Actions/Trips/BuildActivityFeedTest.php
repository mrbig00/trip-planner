<?php

use App\Actions\Trips\BuildActivityFeed;

test('iconFor returns the mapped icon for every known event type', function () {
    foreach (array_keys(BuildActivityFeed::ICONS) as $type) {
        expect(BuildActivityFeed::iconFor($type))->toBe(BuildActivityFeed::ICONS[$type]);
    }
});

test('iconFor throws loudly for an unmapped event type instead of returning null', function () {
    BuildActivityFeed::iconFor('some_future_event_type');
})->throws(ValueError::class);
