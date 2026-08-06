@props([
    'locations',
    'width' => 480,
    'height' => 200,
])

@php
    use App\Support\WebMercator;

    // Only locations with coordinates can be plotted; keep listing order as a
    // reasonable stand-in for "visit order" (there's no explicit sequence
    // field on Location).
    $points = $locations->filter(fn ($location) => $location->latitude && $location->longitude)->values();

    $padding = 24; // px kept clear around the bounding box so edge pins aren't clipped

    if ($points->isEmpty()) {
        $zoom = 15;
        $center = ['x' => 0, 'y' => 0];
    } elseif ($points->count() === 1) {
        $zoom = 15;
        $center = WebMercator::toWorldPixel((float) $points[0]->latitude, (float) $points[0]->longitude, $zoom);
    } else {
        // "Fit bounds": try the highest zoom (most detail) first, and back off
        // until every point's projected pixel position fits inside the widget.
        $zoom = 17;
        while ($zoom > 2) {
            $projected = $points->map(fn ($l) => WebMercator::toWorldPixel((float) $l->latitude, (float) $l->longitude, $zoom));
            $minX = $projected->min('x');
            $maxX = $projected->max('x');
            $minY = $projected->min('y');
            $maxY = $projected->max('y');

            if ($maxX - $minX <= $width - $padding * 2 && $maxY - $minY <= $height - $padding * 2) {
                break;
            }

            $zoom--;
        }

        $center = ['x' => ($minX + $maxX) / 2, 'y' => ($minY + $maxY) / 2];
    }

    $tileSize = WebMercator::TILE_SIZE;
    $tileCount = WebMercator::tileCount($zoom);
    $maxTileIndex = $tileCount - 1;

    $topLeftX = $center['x'] - $width / 2;
    $topLeftY = $center['y'] - $height / 2;

    $firstTileX = (int) floor($topLeftX / $tileSize);
    $firstTileY = (int) floor($topLeftY / $tileSize);

    $offsetX = $topLeftX - $firstTileX * $tileSize;
    $offsetY = $topLeftY - $firstTileY * $tileSize;

    $tilesAcross = (int) ceil(($offsetX + $width) / $tileSize);
    $tilesDown = (int) ceil(($offsetY + $height) / $tileSize);

    // Each pin's pixel position within the widget, relative to the same
    // top-left origin used for the tile grid above.
    $pins = $points->map(function ($location) use ($zoom, $topLeftX, $topLeftY) {
        $world = WebMercator::toWorldPixel((float) $location->latitude, (float) $location->longitude, $zoom);

        return [
            'name' => $location->name,
            'left' => $world['x'] - $topLeftX,
            'top' => $world['y'] - $topLeftY,
        ];
    });
@endphp

@if ($points->isNotEmpty())
    <div
        {{ $attributes->class(['relative overflow-hidden rounded-md border border-neutral-200 dark:border-neutral-700 bg-neutral-800']) }}
        style="width: {{ $width }}px; height: {{ $height }}px; max-width: 100%;"
    >
        <div class="absolute" style="left: {{ -$offsetX }}px; top: {{ -$offsetY }}px;">
            @for ($row = 0; $row < $tilesDown; $row++)
                <div class="flex" style="height: {{ $tileSize }}px;">
                    @for ($col = 0; $col < $tilesAcross; $col++)
                        @php
                            $tileX = (($firstTileX + $col) % $tileCount + $tileCount) % $tileCount;
                            $tileY = min(max($firstTileY + $row, 0), $maxTileIndex);
                        @endphp
                        <img
                            src="https://tile.openstreetmap.org/{{ $zoom }}/{{ $tileX }}/{{ $tileY }}.png"
                            width="{{ $tileSize }}"
                            height="{{ $tileSize }}"
                            loading="lazy"
                            decoding="async"
                            alt=""
                            class="block"
                            onerror="this.style.visibility='hidden'"
                        >
                    @endfor
                </div>
            @endfor
        </div>

        @if ($pins->count() > 1)
            @php $linePoints = $pins->map(fn ($p) => "{$p['left']},{$p['top']}")->implode(' '); @endphp
            <svg class="absolute inset-0 pointer-events-none" width="{{ $width }}" height="{{ $height }}">
                {{-- A dark halo behind the line keeps it visible over unpredictable
                map tile colors (streets, parks, water can all be light or dark). --}}
                <polyline points="{{ $linePoints }}" fill="none" stroke="#000000" stroke-width="5" stroke-linecap="round" opacity="0.35" />
                <polyline points="{{ $linePoints }}" fill="none" stroke="#2a78d6" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="1 5" />
            </svg>
        @endif

        @foreach ($pins as $pin)
            <flux:icon.map-pin
                class="absolute h-6 w-6 text-red-500"
                style="left: {{ $pin['left'] - 12 }}px; top: {{ $pin['top'] - 24 }}px; filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.6));"
                title="{{ $pin['name'] }}"
            />
        @endforeach
    </div>
@endif
