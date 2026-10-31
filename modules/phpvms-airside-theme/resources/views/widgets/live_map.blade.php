@inject('shell', 'App\Features\Shell\ShellData')
{{-- Live map widget: the live-map island. Data: config (width, height, table), center ([lat, lon]), pireps, positions, zoom. --}}
<div class="vms-widget grid gap-4" data-airside-island="live-map" style="width: {{ $config['width'] }}">
    <script type="application/json">{!! json_encode(['updateIntervalSeconds' => (int) setting('livemap.update_interval', 60), 'initialCenter' => ['lat' => $center[0], 'lon' => $center[1]], 'map' => $shell->map()->toArray()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>

    <div class="grid grid-cols-1 gap-4 {{ $config['table'] === true ? 'min-[721px]:grid-cols-[minmax(16rem,20rem)_minmax(0,1fr)]' : '' }}">
        <aside data-live-list @class(['max-h-[40vh] min-w-0 overflow-y-auto rounded-lg p-3', 'hidden' => $config['table'] !== true]) aria-label="Live flights"></aside>

        <div class="relative min-w-0 overflow-hidden rounded-lg">
            <div data-live-map style="height: {{ $config['height'] }}"></div>
            <div data-live-overlay class="pointer-events-none absolute top-3 left-3 z-10 max-w-[min(320px,calc(100%-24px))]"></div>
        </div>
    </div>

    <span data-live-count class="sr-only">0 flights</span>

    <x-vms::livemap.templates>
        <x-slot:empty>
            <p class="m-0 p-3 text-center text-[13px]">@lang('widgets.live_map.noflights')</p>
        </x-slot:empty>
    </x-vms::livemap.templates>
</div>

@airsideVite(['resources/js/islands/live-map.js'])
