@inject('shell', 'App\Features\Shell\ShellData')
{{-- Live map widget: the live-map island. Data: config (width, height, table), center ([lat, lon]), pireps, positions, zoom. --}}
<div class="pv-widget grid gap-4" data-airside-island="live-map" style="width: {{ $config['width'] }}">
    <script type="application/json">{!! json_encode(['updateIntervalSeconds' => (int) setting('livemap.update_interval', 60), 'initialCenter' => ['lat' => $center[0], 'lon' => $center[1]], 'map' => $shell->map()->toArray()], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) !!}</script>

    <div class="grid grid-cols-1 gap-4 {{ $config['table'] === true ? 'min-[721px]:grid-cols-[minmax(16rem,20rem)_minmax(0,1fr)]' : '' }}">
        <aside data-live-list @class(['max-h-[40vh] min-w-0 overflow-y-auto rounded-lg border border-line bg-panel p-3', 'hidden' => $config['table'] !== true]) aria-label="Live flights"></aside>

        <div class="relative min-w-0 overflow-hidden rounded-lg border border-line">
            <div data-live-map class="bg-panel-inset" style="height: {{ $config['height'] }}"></div>
            <div data-live-overlay class="pointer-events-none absolute top-3 left-3 z-10 max-w-[min(320px,calc(100%-24px))]"></div>
        </div>
    </div>

    <span data-live-count class="sr-only">0 flights</span>

    <template data-tpl="row">
        <button type="button" role="option" aria-selected="false" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm hover:bg-panel-inset aria-selected:bg-accent-soft">
            <span data-field="ident" class="font-mono font-semibold text-accent"></span>
            <span data-field="route" class="min-w-0 flex-1 truncate font-mono text-ink-dim"></span>
            <span data-field="phase" class="rounded-md border border-line bg-panel-inset px-1.5 py-0.5 text-[11px] text-ink-dim"></span>
        </button>
    </template>

    <template data-tpl="empty">
        <p class="m-0 p-3 text-center text-[13px] text-ink-dim">@lang('widgets.live_map.noflights')</p>
    </template>

    <template data-tpl="list-error">
        <p class="m-0 text-sm text-red-600" role="alert">Live flights could not be loaded. Retrying automatically.</p>
    </template>

    <template data-tpl="detail">
        <div class="pointer-events-auto max-h-[min(50vh,22rem)] overflow-y-auto rounded-md border border-line bg-panel p-3" aria-label="Selected flight">
            <p data-field="status" class="m-0 text-sm text-ink-dim" hidden></p>
            <p data-field="error" class="m-0 text-sm text-ink-dim" role="alert" hidden>Flight details could not be loaded.</p>
            <a data-field="link" class="flex min-w-0 max-w-full items-center gap-2.5 text-ink hover:underline" hidden wire:navigate>
                <span class="grid size-8 shrink-0 place-items-center overflow-hidden rounded-md border border-line-strong bg-panel-inset text-[11px] font-semibold text-ink-dim">VA</span>
                <span class="flex min-w-0 flex-col items-start leading-tight">
                    <span data-field="callsign" class="max-w-full truncate font-mono font-bold"></span>
                    <span class="mt-0.5 inline-flex min-w-0 items-baseline gap-1.5 truncate font-mono font-medium">
                        <span data-field="dpt"></span><span aria-hidden="true">→</span><span class="sr-only">to</span><span data-field="arr"></span>
                    </span>
                </span>
            </a>
        </div>
    </template>
</div>

@airsideVite(['resources/js/islands/live-map.js'])
