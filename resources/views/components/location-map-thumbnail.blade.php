@props([
    'latitude',
    'longitude',
    'zoom' => 15,
    'width' => 140,
    'height' => 100,
])

@php
    // staticmap.openstreetmap.de (the classic keyless OSM static-map wrapper) has been
    // shut down, so this composes a small thumbnail directly from OSM's raw tile server
    // (https://tile.openstreetmap.org) instead: fetch just enough 256px tiles to cover the
    // thumbnail, shift them with a negative offset so the location sits at the center, and
    // draw a pin on top. Pure server-side math, no JS, no third-party wrapper dependency.
    $lat = (float) $latitude;
    $lon = (float) $longitude;
    $zoom = (int) $zoom;

    $world = \App\Support\WebMercator::toWorldPixel($lat, $lon, $zoom);
    $topLeftX = $world['x'] - $width / 2;
    $topLeftY = $world['y'] - $height / 2;

    $mapUrl = "https://www.google.com/maps/search/?api=1&query={$lat},{$lon}";
    $coords = number_format($lat, 6).', '.number_format($lon, 6);
@endphp

<a
    href="{{ $mapUrl }}"
    target="_blank"
    rel="noopener"
    title="{{ $coords }}"
    {{ $attributes->class(['relative block overflow-hidden rounded-md border border-neutral-200 dark:border-neutral-700 bg-neutral-800']) }}
    style="width: {{ $width }}px; height: {{ $height }}px;"
>
    <x-osm-tile-grid :zoom="$zoom" :top-left-x="$topLeftX" :top-left-y="$topLeftY" :width="$width" :height="$height" />
    <flux:icon.map-pin class="absolute h-6 w-6 text-red-500" style="left: calc(50% - 12px); top: calc(50% - 24px); filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.6));" />
</a>
