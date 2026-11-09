@props([
    'icao' => null,
    'country' => null,
    'lat' => null,
    'lon' => null,
    'scope' => 'country',
    'variant' => 'dotted',
    'size' => 'compact',
])

@php
    $hasPlace = filled($icao);
    $hasPin = $hasPlace && $lat !== null && $lon !== null;
    $variant = in_array($variant, ['outline', 'dotted'], true) ? $variant : 'dotted';
    $size = in_array($size, ['compact', 'hero'], true) ? $size : 'compact';

    // An unknown airport gets the world map and no pin.
    $map = app(\App\Features\Maps\MapResolver::class)->forPoint(
        $hasPlace ? $country : null,
        $hasPin ? (float) $lat : null,
        $hasPin ? (float) $lon : null,
        $hasPlace ? $scope : \App\Features\Maps\MapResolver::SCOPE_WORLD,
    );
@endphp

{{-- A place (ICAO and country) beside a map with one pin. Documented in docs/theme-hooks.md. --}}
<div {{ $attributes->class(['vms-location-card', "vms-location-card--{$variant}", "vms-location-card--{$size}", 'grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.6fr)] sm:items-stretch']) }}>
    <div class="vms-location-card-place min-w-0 self-start p-5.5 sm:order-1">
        <h2 class="vms-location-card-icao m-0 text-3xl font-bold tracking-tight tabular-nums">{{ $hasPlace ? $icao : '—' }}</h2>
        @if ($hasPlace && filled($country))
            <p class="vms-location-card-country m-0 mt-1 text-sm text-dark-400">{{ $country }}</p>
        @endif
        @isset($aside)
            <div class="mt-3">{{ $aside }}</div>
        @endisset
        @if (isset($details) && $details->isNotEmpty())
            <div class="mt-3">{{ $details }}</div>
        @endif
    </div>

    <div @class([
        'vms-location-card-map relative order-first min-w-0 sm:order-2',
        'h-48 sm:h-56' => $size === 'compact',
        'h-60 sm:h-80' => $size === 'hero',
    ])>
        <x-map.svg
            :map="$map"
            :pins="$hasPin ? [['lat' => (float) $lat, 'lon' => (float) $lon, 'label' => $icao]] : []"
            :pattern="$variant === 'dotted' ? 'dots' : null"
            :pin-radius="round(max($map->width, $map->height) / 90, 1)"
            preserve-aspect-ratio="xMidYMid meet"
            class="size-full"
        />
        @if (isset($slot) && $slot->isNotEmpty())
            <div class="vms-location-card-actions absolute bottom-4 right-4 flex flex-wrap items-center justify-end gap-2">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
