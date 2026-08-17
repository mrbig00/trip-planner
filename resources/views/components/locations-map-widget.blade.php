@props([
    'locations',
    'width' => 900,
    'height' => 600,
])

@php
    use App\Support\TripPalette;
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
    // top-left origin used for the tile grid, plus which atlas color its
    // trip owns.
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
            'slot' => TripPalette::slotFor($location->trip_id),
        ];
    });

    // Tailwind only keeps a --color-* custom property that it can see
    // referenced literally somewhere in the scanned source — a dynamically
    // built "--color-trip-{$slot}" string doesn't count, so it would get
    // tree-shaken out of the compiled CSS. Spelling out all six here keeps
    // it in view of the scanner.
    $tripColorVar = fn (int $slot) => match ($slot) {
        1 => 'var(--color-trip-1)',
        2 => 'var(--color-trip-2)',
        3 => 'var(--color-trip-3)',
        4 => 'var(--color-trip-4)',
        5 => 'var(--color-trip-5)',
        6 => 'var(--color-trip-6)',
    };

    // One legend entry per trip represented on the map, so the same color
    // that marks a trip's pins also names it — this is what turns a pile of
    // pins into a readable, multi-trip atlas. Skipped entirely when only one
    // trip is in play, since a one-entry legend has nothing to disambiguate.
    $legend = $points
        ->groupBy('trip_id')
        ->map(fn ($group) => [
            'trip' => $group->first()->trip,
            'slot' => TripPalette::slotFor($group->first()->trip_id),
            'count' => $group->count(),
        ])
        ->sortBy(fn ($entry) => $entry['trip']->name)
        ->values();
@endphp

@if ($points->isNotEmpty())
    <div class="flex flex-col gap-3">
        @if ($legend->count() > 1)
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5">
                @foreach ($legend as $entry)
                    <a
                        href="{{ route('trips.show', $entry['trip']) }}"
                        wire:navigate
                        wire:key="legend-{{ $entry['trip']->id }}"
                        class="group flex items-center gap-1.5"
                    >
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $tripColorVar($entry['slot']) }};"></span>
                        <flux:text variant="strong" class="group-hover:text-accent">{{ $entry['trip']->name }}</flux:text>
                        <flux:text class="font-mono text-xs">{{ $entry['count'] }}</flux:text>
                    </a>
                @endforeach
            </div>
        @endif

        <div
            {{ $attributes->class(['map-frame rounded-xl border border-neutral-200 bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-800']) }}
            style="aspect-ratio: {{ $width }} / {{ $height }}; --map-frame-width: {{ $width }}px;"
        >
            <div class="map-frame-inner" style="width: {{ $width }}px; height: {{ $height }}px;">
                <x-osm-tile-grid :zoom="$zoom" :top-left-x="$topLeftX" :top-left-y="$topLeftY" :width="$width" :height="$height" />

                @foreach ($pins as $pin)
                    @php $location = $pin['location']; @endphp
                    <flux:tooltip :content="$location->name.' — '.$location->trip->name">
                        <a
                            href="{{ route('trips.show', $location->trip) }}"
                            wire:navigate
                            wire:key="pin-{{ $location->id }}"
                            class="group absolute block -translate-x-1/2 -translate-y-1/2 rounded-full outline-none"
                            style="left: {{ $pin['left'] }}px; top: {{ $pin['top'] }}px;"
                        >
                            <span
                                class="block h-3.5 w-3.5 rounded-full border-2 border-neutral-900 shadow-[0_1px_2px_rgb(0_0_0/0.5)] transition-transform group-hover:scale-125 group-focus-visible:scale-125 group-focus-visible:ring-2 group-focus-visible:ring-white group-focus-visible:ring-offset-2 group-focus-visible:ring-offset-neutral-900"
                                style="background-color: {{ $tripColorVar($pin['slot']) }};"
                            ></span>
                        </a>
                    </flux:tooltip>
                @endforeach
            </div>
        </div>
    </div>
@endif
