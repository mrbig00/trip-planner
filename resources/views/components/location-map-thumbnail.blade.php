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
    $tileSize = \App\Support\WebMercator::TILE_SIZE;
    $tileCount = \App\Support\WebMercator::tileCount($zoom);

    $world = \App\Support\WebMercator::toWorldPixel($lat, $lon, $zoom);
    $worldX = $world['x'];
    $worldY = $world['y'];

    $topLeftX = $worldX - $width / 2;
    $topLeftY = $worldY - $height / 2;

    $firstTileX = (int) floor($topLeftX / $tileSize);
    $firstTileY = (int) floor($topLeftY / $tileSize);

    $offsetX = $topLeftX - $firstTileX * $tileSize;
    $offsetY = $topLeftY - $firstTileY * $tileSize;

    $tilesAcross = (int) ceil(($offsetX + $width) / $tileSize);
    $tilesDown = (int) ceil(($offsetY + $height) / $tileSize);

    $maxTileIndex = $tileCount - 1;

    $osmUrl = "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lon}#map={$zoom}/{$lat}/{$lon}";
    $coords = number_format($lat, 6).', '.number_format($lon, 6);
@endphp

<a
    href="{{ $osmUrl }}"
    target="_blank"
    rel="noopener"
    title="{{ $coords }}"
    {{ $attributes->class(['relative block overflow-hidden rounded-md border border-neutral-200 dark:border-neutral-700 bg-neutral-800']) }}
    style="width: {{ $width }}px; height: {{ $height }}px;"
>
    <div class="absolute" style="left: {{ -$offsetX }}px; top: {{ -$offsetY }}px;">
        @for ($row = 0; $row < $tilesDown; $row++)
            <div class="flex" style="height: {{ $tileSize }}px;">
                @for ($col = 0; $col < $tilesAcross; $col++)
                    @php
                        // Wrap horizontally around the globe, clamp vertically at the poles.
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
    <flux:icon.map-pin class="absolute h-6 w-6 text-red-500" style="left: calc(50% - 12px); top: calc(50% - 24px); filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.6));" />
</a>
