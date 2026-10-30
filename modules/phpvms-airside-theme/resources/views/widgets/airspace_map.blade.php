@inject('shell', 'App\Features\Shell\ShellData')
{{-- Airspace map widget: the route map island with one airport (no destination). Data: config (width, height, lat, lon). --}}
<div class="pv-widget min-w-0" style="width: {{ $config['width'] }}">
    <div data-airside-island="route-map" wire:ignore>
        <script type="application/json">{!! json_encode([
            'from'        => ['icao' => '', 'lat' => (float) $config['lat'], 'lon' => (float) $config['lon']],
            'to'          => null,
            'cruiseLevel' => null,
            'height'      => (int) preg_replace('/\D/', '', (string) $config['height']) ?: 400,
            'map'         => $shell->map()->toArray(),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
    </div>
</div>

@airsideVite(['resources/js/islands/route-map.js'])
