@props([
    'locations',
    'width' => 900,
    'height' => 600,
])

@php
    use App\Support\WebMercator;

    // Only locations with coordinates can be plotted. A null check, not a
    // truthy check — a location can legitimately sit at latitude/longitude
    // exactly 0.0 (the equator or the prime meridian), which is falsy in PHP.
    $points = $locations->filter(fn ($location) => $location->latitude !== null && $location->longitude !== null)->values();

    if ($points->isEmpty()) {
        $zoom = 15;
        $center = ['x' => 0, 'y' => 0];
    } elseif ($points->count() === 1) {
        $zoom = 15;
        $center = WebMercator::toWorldPixel((float) $points[0]->latitude, (float) $points[0]->longitude, $zoom);
    } else {
        $fit = WebMercator::fitBounds(
            $points->map(fn ($l) => ['lat' => (float) $l->latitude, 'lon' => (float) $l->longitude])->all(),
            $width,
            $height,
        );
        $zoom = $fit['zoom'];
        $center = $fit['center'];
    }

    $topLeftX = $center['x'] - $width / 2;
    $topLeftY = $center['y'] - $height / 2;

    $worldWidth = WebMercator::tileCount($zoom) * WebMercator::TILE_SIZE;

    // Each pin's pixel position within the widget, relative to the same
    // top-left origin used for the tile grid.
    $pins = $points->map(function ($location) use ($zoom, $topLeftX, $topLeftY, $center, $worldWidth) {
        $world = WebMercator::toWorldPixel((float) $location->latitude, (float) $location->longitude, $zoom);

        // X wraps at the antimeridian, so a pin can project a whole world
        // away from $center even when it's really right next door (e.g.
        // longitude 179.9° vs -179.9°). Shift it by whichever multiple of
        // $worldWidth lands it closest to $center before placing it.
        $x = $world['x'] + round(($center['x'] - $world['x']) / $worldWidth) * $worldWidth;

        return [
            'location' => $location,
            'left' => $x - $topLeftX,
            'top' => $world['y'] - $topLeftY,
        ];
    });
@endphp

@if ($points->isNotEmpty())
    <div
        {{ $attributes->class(['relative overflow-hidden rounded-xl border border-neutral-700 bg-neutral-800']) }}
        style="width: {{ $width }}px; height: {{ $height }}px;"
    >
        <x-osm-tile-grid :zoom="$zoom" :top-left-x="$topLeftX" :top-left-y="$topLeftY" :width="$width" :height="$height" />

        @foreach ($pins as $pin)
            @php $location = $pin['location']; @endphp
            <a
                href="{{ route('trips.show', $location->trip) }}"
                wire:navigate
                class="absolute"
                style="left: {{ $pin['left'] - 12 }}px; top: {{ $pin['top'] - 24 }}px;"
                title="{{ $location->name }} — {{ $location->trip->name }}"
            >
                <flux:icon.map-pin
                    class="h-6 w-6 text-red-500 hover:text-red-400"
                    style="filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.6));"
                />
            </a>
        @endforeach
    </div>
@endif
