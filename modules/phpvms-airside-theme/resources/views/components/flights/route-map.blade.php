@props(['flight', 'height' => 360, 'fixes' => [], 'overlay' => true])
@inject('shell', 'App\Features\Shell\ShellData')

{{--
  The nav display route map island (FlightRouteMap). Props: flight (a FlightDetailData), height (px), fixes (a list of MapPlannedFixData or their arrays: the OFP's planned fixes), overlay (false hides the island's header and readout and sizes the map 16:8 instead of by `height`).
  Emits the island contract: a [data-airside-island="route-map"] wrapper with the route as JSON, plus the
  map config. Shows the empty state when the departure has no coordinates. The island script itself is loaded by
  the page, with @airsideVite('resources/js/islands/route-map.ts').
--}}
@php
    $point = fn ($airport) => $airport?->lat !== null && $airport?->lon !== null
        ? ['icao' => $airport->icao, 'lat' => $airport->lat, 'lon' => $airport->lon]
        : null;
    $from = $point($flight->departure);
    $to = $point($flight->arrival);
    $payload = [
        'from'        => $from,
        'to'          => $to,
        'cruiseLevel' => $flight->cruiseLevel,
        'height'      => $height,
        'map'         => $shell->map()->toArray(),
    ];
    if (! $overlay) {
        $payload['overlay'] = false;
    }
    if ($fixes !== []) {
        $payload['fixes'] = collect($fixes)
            ->map(fn ($fix) => is_array($fix) ? $fix : $fix->toArray())
            ->map(fn (array $fix) => ['ident' => $fix['ident'] ?? null, 'lat' => $fix['lat'], 'lon' => $fix['lon']])
            ->values()
            ->all();
    }
@endphp
@if ($from)
    <div {{ $attributes->class('min-w-0') }} data-airside-island="route-map" wire:ignore>
        <script type="application/json">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
    </div>
@else
    <div class="grid min-h-[180px] place-items-center rounded-md p-6 text-center" role="status">
        Route map unavailable because airport coordinates are missing.
    </div>
@endif
