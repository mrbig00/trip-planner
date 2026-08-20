<?php

use App\Support\Analytics;

test('queue does nothing when analytics is not configured', function () {
    config(['services.google_analytics.id' => null]);

    Analytics::queue('login', ['method' => 'password']);

    expect(Analytics::pullQueued())->toBe([]);
});

test('queue stores the event for pullQueued to return once', function () {
    config(['services.google_analytics.id' => 'G-TEST123']);

    Analytics::queue('login', ['method' => 'password']);

    expect(Analytics::pullQueued())->toBe([
        ['event' => 'login', 'params' => ['method' => 'password']],
    ]);

    // pullQueued() clears what it returns, so a second call comes back empty.
    expect(Analytics::pullQueued())->toBe([]);
});

test('queue accumulates multiple events queued within the same request', function () {
    config(['services.google_analytics.id' => 'G-TEST123']);

    Analytics::queue('sign_up', ['method' => 'google']);
    Analytics::queue('login', ['method' => 'google']);

    expect(Analytics::pullQueued())->toBe([
        ['event' => 'sign_up', 'params' => ['method' => 'google']],
        ['event' => 'login', 'params' => ['method' => 'google']],
    ]);
});
