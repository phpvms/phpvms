@props([
    'map' => null,
    'mapView' => null,
    'pins' => [],
    'routes' => [],
    'preserveAspectRatio' => 'xMidYMid meet',
    'pinRadius' => 6,
    'pattern' => null,
    'grid' => 'square',
    'fit' => false,
    'fitAspect' => 4 / 3,
])

@php
    // Either a ready App\Features\Maps\MapView (`map-view`), or a map with pins, routes and fit to build one from.
    // fit crops the viewBox to the pins and routes; the dots and pins scale with the crop, and --map-scale carries
    // the factor to CSS.
    /** @var \App\Features\Maps\MapView $mapView */
    $mapView ??= \App\Features\Maps\MapView::make($map, $pins, $routes, (bool) $fit, (float) $fitAspect);
    $map = $mapView->map;
    $viewBox = $mapView->viewBox;
    $scale = $mapView->scale;
    $points = $mapView->points;
    $routeRuns = $mapView->routeRuns;
    $pinRadius *= $scale;
    $dots = $pattern === 'dots';
    $paths = app(\App\Features\Maps\MapSvgLoader::class)->inner($map);
@endphp

{{-- Output of resources/maps/*.svg, inline so CSS can reach .land and .land--focus. The fallback look is set with
     presentation attributes, which any stylesheet rule overrides, layered or not. --}}
<svg {{ $attributes->class(['map-svg', 'map-svg--dots' => $dots])->merge($scale !== 1.0 ? ['style' => '--map-scale: '.round($scale, 4)] : [])->merge($dots ? ['x-data' => 'mapDots('.\Illuminate\Support\Js::from(['gap' => round($mapView->dotGap(), 4), 'grid' => $grid === 'diagonal' ? 'diagonal' : 'square', 'wrap' => $mapView->wraps ? $map->width : null]).')'] : []) }}
     xmlns="http://www.w3.org/2000/svg"
     viewBox="{{ implode(' ', array_map(fn (float|int $value): float => round($value, 2), $viewBox)) }}"
     preserveAspectRatio="{{ $preserveAspectRatio }}"
     role="img"
     aria-label="{{ $map->name }}"
     @unless ($dots) fill="currentColor" fill-opacity=".2" stroke="currentColor" stroke-opacity=".35" stroke-width=".5" @endunless>
    @if ($dots)
        {{-- The dots are drawn in the browser (airside's mapDots, resources/js/airside/map-dots.ts) from the land paths,
             which stay hidden. Each set is one path of zero-length segments; the round caps draw the dots, so the stroke
             width is the dot's diameter. The paths carry classes for CSS and a neutral presentation-attribute fallback. --}}
        <g data-map-land display="none">{!! $paths !!}</g>
        <path class="map-dot" data-map-dots="land" d="" fill="none" stroke="currentColor" stroke-opacity=".3" stroke-width="{{ round($mapView->dotRadius() * 2, 3) }}" stroke-linecap="round" />
        <path class="map-dot map-dot--focus" data-map-dots="focus" d="" fill="none" stroke="currentColor" stroke-opacity=".8" stroke-width="{{ round($mapView->dotRadius() * 2, 3) }}" stroke-linecap="round" />
    @else
        {!! str_replace('class="land land--focus"', 'class="land land--focus" fill-opacity=".5"', $paths) !!}
        {{-- A route across the antimeridian runs past the map's edge; the land is drawn again there. --}}
        @foreach ($mapView->landCopies() as $offset)
            <g transform="translate({{ $offset }} 0)">{!! str_replace('class="land land--focus"', 'class="land land--focus" fill-opacity=".5"', $paths) !!}</g>
        @endforeach
    @endif
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
