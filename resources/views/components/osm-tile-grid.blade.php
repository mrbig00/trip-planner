@props([
    'zoom',
    'topLeftX',
    'topLeftY',
    'width',
    'height',
])

{{--
    The tile-grid-with-offset technique shared by location-map-thumbnail and
    locations-map-widget: fetch just enough 256px OSM tiles to cover a
    $width x $height viewport whose top-left corner sits at ($topLeftX,
    $topLeftY) in world-pixel space, then shift the whole grid with a
    negative offset so that corner lands exactly at (0, 0). Pure server-side
    math, no JS, no third-party wrapper dependency.
--}}
@php
    $tileSize = \App\Support\WebMercator::TILE_SIZE;
    $tileCount = \App\Support\WebMercator::tileCount($zoom);
    $maxTileIndex = $tileCount - 1;

    $firstTileX = (int) floor($topLeftX / $tileSize);
    $firstTileY = (int) floor($topLeftY / $tileSize);

    $offsetX = $topLeftX - $firstTileX * $tileSize;
    $offsetY = $topLeftY - $firstTileY * $tileSize;

    $tilesAcross = (int) ceil(($offsetX + $width) / $tileSize);
    $tilesDown = (int) ceil(($offsetY + $height) / $tileSize);
@endphp

<div class="absolute" style="left: {{ -$offsetX }}px; top: {{ -$offsetY }}px;">
    @for ($row = 0; $row < $tilesDown; $row++)
        <div class="flex" style="height: {{ $tileSize }}px;" wire:key="tile-row-{{ $zoom }}-{{ $row }}">
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
                    wire:key="tile-{{ $zoom }}-{{ $row }}-{{ $col }}"
                >
            @endfor
        </div>
    @endfor
</div>
