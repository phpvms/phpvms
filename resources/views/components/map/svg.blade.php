@props([
    'map',
    'pins' => [],
    'routes' => [],
    'preserveAspectRatio' => 'xMidYMid meet',
    'pinRadius' => 6,
    'pattern' => null,
    'fit' => false,
    'fitAspect' => 4 / 3,
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
    // routes: each a great circle between two points ([[lat, lon], [lat, lon]]), as polyline runs.
    $routeRuns = [];
    foreach ($routes as [$from, $to]) {
        if (in_array(null, [$from[0] ?? null, $from[1] ?? null, $to[0] ?? null, $to[1] ?? null], true)) {
            continue;
        }
        array_push($routeRuns, ...\App\Features\Maps\MapProjection::greatCircle($map, (float) $from[0], (float) $from[1], (float) $to[0], (float) $to[1]));
    }
    // fit: crop the viewBox to the pins and routes (with room around them) at the fitAspect ratio. The dots and pins are
    // scaled with the crop, so they keep their size on screen; --map-scale carries the factor to CSS.
    $viewBox = [0, 0, $map->width, $map->height];
    $scale = 1;
    if ($fit && $points !== []) {
        $framed = array_merge($points, ...$routeRuns);
        $xs = array_column($framed, 'x');
        $ys = array_column($framed, 'y');
        $span = max($map->width, $map->height);
        $width = max((max($xs) - min($xs)) * 1.6, $span * 0.12);
        $height = max((max($ys) - min($ys)) * 1.6, $span * 0.12);
        $width = max($width, $height * $fitAspect);
        $height = $width / $fitAspect;
        $x = (min($xs) + max($xs) - $width) / 2;
        $y = (min($ys) + max($ys) - $height) / 2;
        // Keep the crop on the map where it fits.
        $x = $width >= $map->width ? ($map->width - $width) / 2 : min(max($x, 0), $map->width - $width);
        $y = $height >= $map->height ? ($map->height - $height) / 2 : min(max($y, 0), $map->height - $height);
        $scale = $width / $map->width;
        $viewBox = [$x, $y, $width, $height];
        $dotGap *= $scale;
        $dotRadius *= $scale;
        $pinRadius *= $scale;
    }
@endphp

{{-- Output of resources/maps/*.svg, inline so CSS can reach .land and .land--focus. The fallback look is set with
     presentation attributes, which any stylesheet rule overrides, layered or not. --}}
<svg {{ $attributes->class(['map-svg', 'map-svg--dots' => $dots])->merge($dots ? ['data-map-pattern' => $patternId] : [])->merge($scale !== 1 ? ['style' => '--map-scale: '.round($scale, 4)] : []) }}
     xmlns="http://www.w3.org/2000/svg"
     viewBox="{{ implode(' ', array_map(fn (float|int $value): float => round($value, 2), $viewBox)) }}"
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
    @foreach ($routeRuns as $run)
        <polyline class="map-route" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"
                  points="{{ implode(' ', array_map(fn (array $p): string => round($p['x'], 1).','.round($p['y'], 1), $run)) }}" />
    @endforeach
    @foreach ($points as $point)
        <circle class="map-pin" fill="currentColor" fill-opacity="1" stroke="none" cx="{{ round($point['x'], 1) }}" cy="{{ round($point['y'], 1) }}" r="{{ $pinRadius }}">
            @if (filled($point['label']))
                <title>{{ $point['label'] }}</title>
            @endif
        </circle>
    @endforeach
</svg>
