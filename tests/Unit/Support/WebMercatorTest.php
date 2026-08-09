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
