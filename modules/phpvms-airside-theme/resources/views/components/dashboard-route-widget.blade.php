@props(['route' => []])

{{--
  Nav display widget (RouteWidget). Prop: route (RouteData as an array: from/to with icao, lat, lon).
  Emits the route-map island (the island draws the map) and the TRK/DIST readout; with no origin it shows
  the "No position" note.
--}}
@php
    $from = $route['from'] ?? null;
    $to = $route['to'] ?? null;
    $readout = $from && $to
        ? [
            'trk' => \App\Features\Dashboard\Support\RouteReadout::bearing((float) $from['lat'], (float) $from['lon'], (float) $to['lat'], (float) $to['lon']),
            'dist' => \App\Features\Dashboard\Support\RouteReadout::distanceNm((float) $from['lat'], (float) $from['lon'], (float) $to['lat'], (float) $to['lon']),
        ]
        : null;
    $point = fn (?array $airport): ?array => $airport ? ['icao' => $airport['icao'], 'lat' => (float) $airport['lat'], 'lon' => (float) $airport['lon']] : null;
@endphp
@if ($from)
    <div class="pv-route-widget flex flex-col">
        <div wire:ignore data-airside-island="route-map" class="h-[300px] overflow-hidden rounded-md bg-panel-inset">
            <script type="application/json">{!! json_encode(['from' => $point($from), 'to' => $point($to), 'cruiseLevel' => null, 'height' => 300], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
        </div>
        <div class="mt-2 flex items-center justify-between gap-2 font-mono text-xs text-ink-dim">
            <span class="font-medium text-accent">{{ $from['icao'].($to ? ' → '.$to['icao'] : '') }}</span>
            <span class="tnum"><span class="text-ink-faint">TRK</span> {{ $readout ? $readout['trk'].'°' : '—' }} · <span class="text-ink-faint">DIST</span> {{ $readout ? $readout['dist'].' nm' : '—' }}</span>
        </div>
    </div>
@else
    <div class="py-10 text-center text-xs text-ink-dim">No position — set a home airport</div>
@endif
