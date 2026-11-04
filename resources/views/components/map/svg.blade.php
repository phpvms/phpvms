@props([
    'map',
    'pins' => [],
    'preserveAspectRatio' => 'xMidYMid meet',
    'pinRadius' => 6,
    'pattern' => null,
])

@php
    /** @var \App\Features\Maps\MapData $map */
    $paths = app(\App\Features\Maps\MapSvgLoader::class)->inner($map);
    $dots = $pattern === 'dots';
    $patternId = $dots ? 'map-dots-'.\Illuminate\Support\Str::random(8) : null;
    $focusPatternId = $dots ? $patternId.'-focus' : null;
    // Dot grid in viewBox units (the longer side is 1000).
    $dotGap = 14;
    $dotRadius = 3.6;
    $points = [];
    foreach ($pins as $pin) {
        if (($pin['lat'] ?? null) === null || ($pin['lon'] ?? null) === null) {
            continue;
        }
        $points[] = [
            ...\App\Features\Maps\MapProjection::point($map, (float) $pin['lat'], (float) $pin['lon']),
            'label' => $pin['label'] ?? null,
        ];
    }
@endphp

{{-- Output of resources/maps/*.svg, inline so CSS can reach .land and .land--focus. The fallback look is set with
     presentation attributes, which any stylesheet rule overrides, layered or not. --}}
<svg {{ $attributes->class(['map-svg', 'map-svg--dots' => $dots])->merge($dots ? ['data-map-pattern' => $patternId] : []) }}
     xmlns="http://www.w3.org/2000/svg"
     viewBox="0 0 {{ $map->width }} {{ $map->height }}"
     preserveAspectRatio="{{ $preserveAspectRatio }}"
     role="img"
     aria-label="{{ $map->name }}"
     @if ($dots) fill="url(#{{ $patternId }})" stroke="none" @else fill="currentColor" fill-opacity=".2" stroke="currentColor" stroke-opacity=".35" stroke-width=".5" @endif>
    @if ($dots)
        {{-- A pattern takes its dot colour from where it is defined, so land and the focus country get one pattern each.
             The circles carry classes for CSS and a neutral presentation-attribute fallback. --}}
        <defs>
            <pattern id="{{ $patternId }}" width="{{ $dotGap }}" height="{{ $dotGap }}" patternUnits="userSpaceOnUse">
                <circle class="map-dot" cx="{{ $dotGap / 2 }}" cy="{{ $dotGap / 2 }}" r="{{ $dotRadius }}" fill="currentColor" fill-opacity=".3" />
            </pattern>
            <pattern id="{{ $focusPatternId }}" width="{{ $dotGap }}" height="{{ $dotGap }}" patternUnits="userSpaceOnUse">
                <circle class="map-dot map-dot--focus" cx="{{ $dotGap / 2 }}" cy="{{ $dotGap / 2 }}" r="{{ $dotRadius }}" fill="currentColor" fill-opacity=".8" />
            </pattern>
        </defs>
    @endif
    {!! str_replace(
        'class="land land--focus"',
        $dots ? 'class="land land--focus" fill="url(#'.$focusPatternId.')"' : 'class="land land--focus" fill-opacity=".5"',
        $paths,
    ) !!}
    @foreach ($points as $point)
        <circle class="map-pin" fill="currentColor" fill-opacity="1" stroke="none" cx="{{ round($point['x'], 1) }}" cy="{{ round($point['y'], 1) }}" r="{{ $pinRadius }}">
            @if (filled($point['label']))
                <title>{{ $point['label'] }}</title>
            @endif
        </circle>
    @endforeach
</svg>
