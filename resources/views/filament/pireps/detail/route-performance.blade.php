@php
    use Filament\Support\Facades\FilamentAsset;

    /** @var \App\Models\Pirep $record */
    /** @var array<string,mixed> $mapFeatures */
    /** @var array<string,mixed>|null $performance */

    $hasRouteMap = ! empty($mapFeatures);
    $mapElementId = 'pirep-route-map-'.$record->id;

    $blockOff = $record->block_off_time?->format('H:i');
    $blockOn = $record->block_on_time?->format('H:i');

    // Duration label (e.g. "07h 42m")
    $minutes = (int) ($record->flight_time ?? 0);
    $duration = $minutes > 0
        ? sprintf('%02dh %02dm', intdiv($minutes, 60), $minutes % 60)
        : null;
    $unitDistance = setting('units.distance');
@endphp

<div class="fi-pirep-detail-v2-card fi-pirep-detail-v2-route-perf">
    <div class="fi-pirep-detail-v2-card-head">
        <h3>Route &amp; Performance</h3>
        @if ($hasRouteMap)
            <div class="actions">
                <a class="link" href="#">Full screen ↗</a>
            </div>
        @endif
    </div>

    <div class="fi-pirep-detail-v2-card-body flush">
        {{-- Map (lazy-loaded Leaflet + phpvms admin maps) --}}
        @if ($hasRouteMap)
            <div
                class="fi-pirep-detail-v2-map"
                x-data="{
                    init() {
                        const tryInit = () => {
                            if (window.phpvms?.map?.render_route_map) {
                                window.phpvms.map.render_route_map({
                                    render_elem: @js($mapElementId),
                                    route_points:        @js($mapFeatures['planned_rte_points'] ?? null),
                                    planned_route_line:  @js($mapFeatures['planned_rte_line'] ?? null),
                                    actual_route_line:   @js($mapFeatures['actual_route_line'] ?? null),
                                    actual_route_points: @js($mapFeatures['actual_route_points'] ?? null),
                                    flown_route_color: '#067ec1',
                                    circle_color: '#056093',
                                    flightplan_route_color: '#8B008B',
                                    leafletOptions: { scrollWheelZoom: false },
                                });
                                return;
                            }
                            setTimeout(tryInit, 50);
                        };
                        tryInit();
                    }
                }"
                x-load-css="[@js(FilamentAsset::getStyleHref('leaflet'))]"
                x-load-js="[@js(FilamentAsset::getScriptSrc('phpvms-admin-maps'))]"
            >
                <div id="{{ $mapElementId }}" style="width:100%;height:360px;"></div>
            </div>
        @endif

        {{-- Route bar --}}
        <div class="fi-pirep-detail-v2-route-bar">
            <div class="end left">
                <div class="icao">{{ $record->dpt_airport_id }}</div>
                @if ($record->dpt_airport?->name)
                    <div class="name">{{ $record->dpt_airport->name }}</div>
                @endif
                @if ($blockOff)
                    <div class="time">{{ $blockOff }}Z · Block off</div>
                @endif
            </div>
            <div class="mid">
                @if ($duration)
                    <div class="duration">{{ $duration }}</div>
                @endif
                <div class="line"></div>
                @if ($record->distance)
                    <div class="distance">{{ number_format((float) $record->distance->local()) }} {{ $unitDistance }}</div>
                @endif
            </div>
            <div class="end right">
                <div class="icao">{{ $record->arr_airport_id }}</div>
                @if ($record->arr_airport?->name)
                    <div class="name">{{ $record->arr_airport->name }}</div>
                @endif
                @if ($blockOn)
                    <div class="time">{{ $blockOn }}Z · Block on</div>
                @endif
            </div>
        </div>

        {{-- Performance: empty stub when no ACARS --}}
        @if ($performance === null)
            <div class="fi-pirep-detail-v2-perf-empty">
                <div class="icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 3v18h18"/>
                        <path d="M7 14l4-4 4 4 5-5"/>
                        <circle cx="18" cy="6" r="2" fill="currentColor" stroke="none" opacity=".3"/>
                    </svg>
                </div>
                <h4>No ACARS data for this PIREP</h4>
                <p>Performance charts (altitude, speed, fuel, vertical speed) appear here once the pilot's ACARS client uploads flight samples.</p>
            </div>
        @else
            {{-- Performance: chart container --}}
            <div
                class="fi-pirep-detail-v2-perf"
                x-data="pirepPerformanceChart(@js($performance))"
                x-load-js="[@js(FilamentAsset::getScriptSrc('pirep-performance-chart'))]"
            >
                <div class="perf-tabs">
                    <button type="button" class="perf-tab" :class="active==='altitude' && 'active'" @click="select('altitude')">
                        <span class="swatch" style="background:#067ec1"></span>Altitude
                    </button>
                    <button type="button" class="perf-tab" :class="active==='speed' && 'active'" @click="select('speed')">
                        <span class="swatch" style="background:#14b8a6"></span>Speed
                    </button>
                    <button type="button" class="perf-tab" :class="active==='fuel' && 'active'" @click="select('fuel')">
                        <span class="swatch" style="background:#f59e0b"></span>Fuel
                    </button>
                    <button type="button" class="perf-tab" :class="active==='vs' && 'active'" @click="select('vs')">
                        <span class="swatch" style="background:#8b5cf6"></span>Vertical speed
                    </button>
                    <div class="extra"><span class="lbl">{{ number_format($performance['sample_count']) }} samples</span></div>
                </div>

                <div class="chart-wrap">
                    <canvas x-ref="canvas" style="width:100%;height:240px;"></canvas>
                </div>
            </div>
        @endif
    </div>
</div>
