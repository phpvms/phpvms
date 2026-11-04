{{--
  Nav display: the route-map island and the TRK/DIST readout. Data: route (from/to with icao, lat, lon).
  The island (resources/js/islands/route-map.js) draws the map from the JSON block.
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
    <div class="flex flex-col">
        <div wire:ignore data-airside-island="route-map" class="vms-route-map h-[300px] overflow-hidden rounded-(--vms-radius-md) bg-primary-50 dark:bg-dark-700">
            <script type="application/json">{!! json_encode(['from' => $point($from), 'to' => $point($to), 'cruiseLevel' => null, 'height' => 300], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>
        </div>
        <div class="vms-route-readout mt-3 flex items-center justify-between gap-2 font-mono text-[12.5px] text-dark-600 dark:text-dark-300">
            <span class="font-semibold text-(--vms-brand)">{{ $from['icao'].($to ? ' → '.$to['icao'] : '') }}</span>
            <span class="tabular-nums"><span class="text-dark-400">TRK</span> {{ $readout ? $readout['trk'].'°' : '—' }} · <span class="text-dark-400">DIST</span> {{ $readout ? $readout['dist'].' nm' : '—' }}</span>
        </div>
    </div>
@else
    <div class="py-10 text-center text-xs text-dark-500 dark:text-dark-400">No position — set a home airport</div>
@endif
