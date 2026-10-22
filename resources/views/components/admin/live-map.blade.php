@php
    /** @var int $pollIntervalMs */

    $mapElementId = 'live-flights-map';
@endphp

{{-- Live-flights globe (Tier 0 — design.md D5/D13: aircraft markers are an
     ordinary GeoJSON symbol layer, no three.js). Polls GET api/map/live on
     the same cadence as `livemap.update_interval`, wholesale-replacing the
     marker source each tick, matching the existing live-map contract
     (design.md Context 4). --}}
{{-- wire:ignore: the table above polls independently (->poll('30s')) and
     re-renders its own subtree; the map manages its own polling and must not
     be torn down by that. --}}
<div
    wire:ignore
    class="live-map"
    x-data="{
        handle: null,
        pollId: null,
        onThemeChanged: null,
        onNavigating: null,
        async build(theme) {
            const phpvms = await (window.phpvmsReady ?? new Promise(resolve => {
                window.addEventListener('phpvms:ready', e => resolve(e.detail), { once: true });
            }));

            // maplibre style is fixed at construction (no setTheme()), so a
            // theme flip tears down and rebuilds rather than mutating in place.
            this.handle?.destroy();
            this.handle = await phpvms.map.render_live_map_from_api(@js($mapElementId), {
                config: window.filamentData.maps,
                theme,
            });
        },
        async poll() {
            if (!this.handle) return;

            this.handle.setFlights(await window.phpvms.map.fetch_live_flights());
        },
        async init() {
            const phpvms = await (window.phpvmsReady ?? new Promise(resolve => {
                window.addEventListener('phpvms:ready', e => resolve(e.detail), { once: true });
            }));

            await this.build(phpvms.theme.current());

            this.pollId = setInterval(() => this.poll(), @js($pollIntervalMs));

            this.onThemeChanged = (e) => this.build(e.detail);
            window.addEventListener('theme-changed', this.onThemeChanged);

            // SPA navigation away (Livewire.navigate) replaces the whole
            // document body — stop polling and free the WebGL context
            // (design.md D16 context budget) rather than leak both.
            this.onNavigating = () => {
                clearInterval(this.pollId);
                this.handle?.destroy();
            };
            document.addEventListener('livewire:navigating', this.onNavigating, { once: true });
        },
    }"
>
    <div id="{{ $mapElementId }}" style="width:100%;height:420px;"></div>
</div>
