<?php

use App\Support\WebMercator;

test('tileCount doubles per zoom level', function () {
    expect(WebMercator::tileCount(0))->toBe(1)
        ->and(WebMercator::tileCount(1))->toBe(2)
        ->and(WebMercator::tileCount(10))->toBe(1024);
});

test('toWorldPixel places the origin (0,0) at the center of the world at zoom 0', function () {
    $pixel = WebMercator::toWorldPixel(0.0, 0.0, 0);

    expect($pixel['x'])->toBe(WebMercator::TILE_SIZE / 2.0)
        ->and($pixel['y'])->toBe(WebMercator::TILE_SIZE / 2.0);
});

test('toWorldPixel moves east/north as longitude/latitude increase', function () {
    $center = WebMercator::toWorldPixel(0.0, 0.0, 5);
    $east = WebMercator::toWorldPixel(0.0, 10.0, 5);
    $north = WebMercator::toWorldPixel(10.0, 0.0, 5);

    // Moving east increases the x pixel coordinate...
    expect($east['x'])->toBeGreaterThan($center['x']);
    // ...moving north (up) decreases the y pixel coordinate, since Web
    // Mercator's y axis (like screen/CSS coordinates) grows downward.
    expect($north['y'])->toBeLessThan($center['y']);
});

test('a known landmark projects inside the expected tile at a common zoom', function () {
    // Eiffel Tower, zoom 15 — cross-checked against a live tile.openstreetmap.org
    // request while building the single-location map thumbnail.
    $pixel = WebMercator::toWorldPixel(48.8584, 2.2945, 15);
    $tileCount = WebMercator::tileCount(15);

    $tileX = (int) floor($pixel['x'] / WebMercator::TILE_SIZE);
    $tileY = (int) floor($pixel['y'] / WebMercator::TILE_SIZE);

    expect($tileX)->toBe(16592)
        ->and($tileY)->toBe(11272)
        ->and($tileCount)->toBe(32768);
});

test('fitBounds picks a high zoom for two nearby points', function () {
    // Eiffel Tower and Arc de Triomphe — a couple of km apart.
    $fit = WebMercator::fitBounds(
        [['lat' => 48.8584, 'lon' => 2.2945], ['lat' => 48.8738, 'lon' => 2.2950]],
        900,
        600,
    );

    expect($fit['zoom'])->toBeGreaterThan(10);
});

test('fitBounds always returns a center at the same scale as the returned zoom, even at the floor', function () {
    // Two points on opposite sides of the globe: no zoom above the floor (2)
    // can ever fit them in a 900x600 canvas, so the loop must run all the
    // way down to zoom 2 — this is exactly the case that a stale-scale bug
    // in the back-off loop would only surface under.
    $points = [['lat' => 40.0, 'lon' => -74.0], ['lat' => -33.9, 'lon' => 151.2]];
    $fit = WebMercator::fitBounds($points, 900, 600);

    expect($fit['zoom'])->toBe(2);

    // Recompute the bounding box independently, at the same zoom fitBounds()
    // settled on, and confirm the returned center matches it exactly — this
    // fails if center were ever built from a different zoom's projection.
    $projected = array_map(fn ($p) => WebMercator::toWorldPixel($p['lat'], $p['lon'], $fit['zoom']), $points);
    $minX = min(array_column($projected, 'x'));
    $maxX = max(array_column($projected, 'x'));
    $minY = min(array_column($projected, 'y'));
    $maxY = max(array_column($projected, 'y'));

    expect($fit['center']['x'])->toBe(($minX + $maxX) / 2)
        ->and($fit['center']['y'])->toBe(($minY + $maxY) / 2);
});

test('fitBounds never returns a zoom below the floor of 2', function () {
    $fit = WebMercator::fitBounds(
        [['lat' => 89.0, 'lon' => -179.0], ['lat' => -89.0, 'lon' => 179.0]],
        100,
        100,
    );

    expect($fit['zoom'])->toBeGreaterThanOrEqual(2);
});
