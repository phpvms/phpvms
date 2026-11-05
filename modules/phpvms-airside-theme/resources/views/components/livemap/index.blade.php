@props(['updateIntervalSeconds', 'initialCenter' => null, 'map', 'rail' => false])

{{--
  The live map: a full-bleed map with floating panels, the flight list on the left and the
  selected flight on the right. The live-map island (resources/js/islands/live-map.js) polls,
  fills the counts and the list, filters by stage and search, and draws the selection.
  Props: updateIntervalSeconds, initialCenter ({lat, lon} or null), map (MapConfigData array),
  rail (collapse the sidebar to its rail while the map is open; the pilot's own setting is
  put back on leaving). The size comes from the caller's classes.
--}}
@php
    $config = [
        'updateIntervalSeconds' => $updateIntervalSeconds,
        'initialCenter' => $initialCenter,
        'map' => $map,
        'stages' => \Modules\PhpvmsAirsideTheme\Support\LiveMapStages::map(),
    ];
    $filters = ['all' => 'All', 'departing' => 'Departing', 'enroute' => 'En route', 'arriving' => 'Arriving', 'ground' => 'Ground'];
    $stats = ['all' => 'Live', 'airborne' => 'Airborne', 'arriving' => 'Arriving', 'ground' => 'Ground'];
@endphp

<div {{ $attributes->class('vms-live group/live relative overflow-hidden') }} data-airside-island="live-map" @if ($rail) data-live-rail @endif>
    <script type="application/json">{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>

    <div data-live-map class="vms-live-map absolute inset-0" wire:ignore></div>

    <section class="vms-live-panel absolute top-4 bottom-4 left-4 z-10 flex w-[23rem] flex-col overflow-hidden max-md:right-4 max-md:bottom-auto max-md:max-h-[45%] max-md:w-auto max-md:group-data-selected/live:hidden" aria-labelledby="vms-live-title">
        <header class="vms-live-header grid gap-3 p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="vms-live-eyebrow m-0 text-[11px] font-bold tracking-[0.14em] uppercase">Operations</p>
                <p class="vms-live-pulse m-0 flex items-center gap-1.5 text-xs">Live</p>
            </div>
            <h1 id="vms-live-title" class="vms-live-title m-0 text-3xl leading-none font-extrabold tracking-tight">Live <span>flights.</span></h1>
            <dl class="m-0 grid grid-cols-4 gap-1.5 max-md:hidden">
                @foreach ($stats as $key => $label)
                    <div class="vms-live-stat flex min-w-0 flex-col-reverse gap-px px-2.5 py-2">
                        <dt class="truncate text-[11px]">{{ $label }}</dt>
                        <dd data-live-stat="{{ $key }}" class="m-0 text-lg font-semibold tabular-nums">0</dd>
                    </div>
                @endforeach
            </dl>
        </header>

        <div class="grid gap-2.5 px-4 pt-3.5 pb-2.5">
            <x-ts-input type="search" icon="phosphor.magnifying-glass.light" placeholder="Callsign, pilot, airport" aria-label="Search live flights" data-live-search />
            <div class="flex flex-wrap gap-1" role="group" aria-label="Filter by stage">
                @foreach ($filters as $key => $label)
                    <button type="button" data-live-filter="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}" class="vms-live-filter cursor-pointer rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div data-live-list class="min-h-0 flex-1 overflow-y-auto px-2 pb-3" aria-label="Live flights"></div>
    </section>

    <aside data-live-overlay class="vms-live-overlay absolute top-4 right-4 z-10 w-[23rem] max-md:top-auto max-md:bottom-4 max-md:left-4 max-md:w-auto" aria-label="Selected flight"></aside>

    <x-vms::livemap.templates detailed />
</div>
