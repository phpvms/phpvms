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
                x-load
                x-load-src="{{ FilamentAsset::getAlpineComponentSrc('pirep-performance-chart') }}"
                x-data="pirepPerformanceChart(@js($performance))"
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

{{-- Landing analysis: runway plan-views + scorecard polar.
     Only rendered when buildLandingBlock() produced a payload. --}}
@php $landing = $performance['landing'] ?? null; @endphp
@if ($landing && (filled($landing['departure']['runway'] ?? null) || filled($landing['arrival']['runway'] ?? null)))
    <div class="fi-pirep-detail-v2-card fi-pirep-detail-v2-landing"
         x-load
         x-load-src="{{ FilamentAsset::getAlpineComponentSrc('pirep-landing-analysis') }}"
         x-data="pirepLandingAnalysis(@js($landing))">

        <div class="fi-pirep-detail-v2-card-head">
            <h3>Landing analysis</h3>
        </div>

        <div class="fi-pirep-detail-v2-card-body">
            <div class="landing-grid">
                {{-- Departure runway plan-view --}}
                @if (filled($landing['departure']['runway'] ?? null))
                    <div class="rw-panel">
                        <div class="rw-panel-head">
                            <span class="rw-side">Departure</span>
                            <span class="rw-id">RWY {{ $landing['departure']['runway'] }}</span>
                        </div>
                        <div class="rw-diagram">
                            <svg viewBox="0 0 200 100" preserveAspectRatio="none" aria-hidden="true">
                                {{-- Runway strip --}}
                                <rect x="0" y="34" width="200" height="32" fill="#374151" rx="2"/>
                                {{-- Threshold piano keys (white bars on left edge) --}}
                                <g fill="#ffffff" opacity="0.95">
                                    <rect x="6"  y="36" width="2" height="28"/>
                                    <rect x="10" y="36" width="2" height="28"/>
                                    <rect x="14" y="36" width="2" height="28"/>
                                    <rect x="18" y="36" width="2" height="28"/>
                                    <rect x="22" y="36" width="2" height="28"/>
                                </g>
                                {{-- Dashed centerline (starts past threshold marks) --}}
                                <line x1="30" y1="50" x2="200" y2="50"
                                      stroke="#fbbf24" stroke-width="1"
                                      stroke-dasharray="6 6" opacity="0.85"/>
                                {{-- Aircraft glyph. Arrow points right (down runway) at 0° dev;
                                     rotates clockwise as heading deviation increases. --}}
                                <g x-show="departureMarker"
                                   :transform="departureMarker ? `translate(${departureMarker.x},${departureMarker.y}) rotate(${departureMarker.rotation})` : ''">
                                    <path d="M -8 -6 L 10 0 L -8 6 L -4 0 Z"
                                          :fill="departureMarker?.color || '#ef4444'"
                                          stroke="#fff" stroke-width="1.25" stroke-linejoin="round"/>
                                </g>
                            </svg>
                        </div>
                        <div class="rw-facts">
                            @if (filled($landing['departure']['centerline_offset'] ?? null))
                                <div class="fact-inline">
                                    <span class="k">Centerline</span>
                                    <span class="v">{{ number_format((float) $landing['departure']['centerline_offset'], 2) }}</span>
                                </div>
                            @endif
                            @if (filled($landing['departure']['heading_deviation'] ?? null))
                                <div class="fact-inline">
                                    <span class="k">Heading dev</span>
                                    <span class="v">{{ number_format((float) $landing['departure']['heading_deviation'], 2) }}°</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Arrival runway plan-view --}}
                @if (filled($landing['arrival']['runway'] ?? null))
                    <div class="rw-panel">
                        <div class="rw-panel-head">
                            <span class="rw-side">Arrival</span>
                            <span class="rw-id">RWY {{ $landing['arrival']['runway'] }}</span>
                        </div>
                        <div class="rw-diagram">
                            <svg viewBox="0 0 200 100" preserveAspectRatio="none" aria-hidden="true">
                                <rect x="0" y="34" width="200" height="32" fill="#374151" rx="2"/>
                                <g fill="#ffffff" opacity="0.95">
                                    <rect x="6"  y="36" width="2" height="28"/>
                                    <rect x="10" y="36" width="2" height="28"/>
                                    <rect x="14" y="36" width="2" height="28"/>
                                    <rect x="18" y="36" width="2" height="28"/>
                                    <rect x="22" y="36" width="2" height="28"/>
                                </g>
                                <line x1="30" y1="50" x2="200" y2="50"
                                      stroke="#fbbf24" stroke-width="1"
                                      stroke-dasharray="6 6" opacity="0.85"/>
                                <g x-show="arrivalMarker"
                                   :transform="arrivalMarker ? `translate(${arrivalMarker.x},${arrivalMarker.y}) rotate(${arrivalMarker.rotation})` : ''">
                                    <path d="M -8 -6 L 10 0 L -8 6 L -4 0 Z"
                                          :fill="arrivalMarker?.color || '#ef4444'"
                                          stroke="#fff" stroke-width="1.25" stroke-linejoin="round"/>
                                </g>
                            </svg>
                        </div>
                        <div class="rw-facts">
                            @if (filled($landing['arrival']['centerline_offset'] ?? null))
                                <div class="fact-inline">
                                    <span class="k">Centerline</span>
                                    <span class="v">{{ number_format((float) $landing['arrival']['centerline_offset'], 2) }}</span>
                                </div>
                            @endif
                            @if (filled($landing['arrival']['heading_deviation'] ?? null))
                                <div class="fact-inline">
                                    <span class="k">Heading dev</span>
                                    <span class="v">{{ number_format((float) $landing['arrival']['heading_deviation'], 2) }}°</span>
                                </div>
                            @endif
                            @if (filled($landing['arrival']['threshold_distance'] ?? null))
                                <div class="fact-inline">
                                    <span class="k">Threshold dist</span>
                                    <span class="v">{{ number_format((float) $landing['arrival']['threshold_distance'], 0) }}</span>
                                </div>
                            @endif
                            @if (filled($landing['arrival']['threshold_crossing_alt'] ?? null))
                                <div class="fact-inline">
                                    <span class="k">TCH</span>
                                    <span class="v">{{ number_format((float) $landing['arrival']['threshold_crossing_alt'], 1) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Landing scorecard polar (3rd panel in row 1, beside runways) --}}
                <div class="rw-panel rw-panel-scorecard">
                    <div class="rw-panel-head">
                        <span class="rw-side">Landing</span>
                        <span class="rw-id">Scorecard</span>
                    </div>
                    <div class="scorecard-chart">
                        <canvas x-ref="scorecard"></canvas>
                    </div>
                </div>

                {{-- Row 2: paired attitude indicators (takeoff + touchdown).
                     Takeoff falls back to a "no data" graphic since current
                     ACARS clients don't record pitch/roll at takeoff. --}}
                <div class="landing-attitude-row">
                    {{-- Takeoff AI --}}
                    <div class="rw-panel rw-panel-attitude">
                        <div class="rw-panel-head">
                            <span class="rw-side">Takeoff</span>
                            <span class="rw-id">Attitude</span>
                        </div>
                        <div class="rw-diagram attitude-diagram attitude-no-data">
                            <svg viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                                <defs>
                                    <clipPath id="ai-clip-to-{{ $record->id }}">
                                        <rect x="0" y="0" width="200" height="100" rx="6"/>
                                    </clipPath>
                                    <pattern id="ai-no-data-{{ $record->id }}" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                                        <line x1="0" y1="0" x2="0" y2="8" stroke="#374151" stroke-width="1" opacity="0.5"/>
                                    </pattern>
                                </defs>
                                <g clip-path="url(#ai-clip-to-{{ $record->id }})">
                                    <rect x="0" y="0" width="200" height="100" fill="#1f2937"/>
                                    <rect x="0" y="0" width="200" height="100" fill="url(#ai-no-data-{{ $record->id }})"/>
                                    <text x="100" y="54" text-anchor="middle"
                                          font-family="var(--font-mono-display), monospace"
                                          font-size="9" font-weight="500"
                                          letter-spacing="0.15em"
                                          fill="#9ca3af">NO ATTITUDE DATA</text>
                                </g>
                            </svg>
                        </div>
                        <div class="rw-facts">
                            <div class="fact-inline">
                                <span class="k">Pitch</span>
                                <span class="v">—</span>
                            </div>
                            <div class="fact-inline">
                                <span class="k">Roll</span>
                                <span class="v">—</span>
                            </div>
                        </div>
                    </div>

                    {{-- Touchdown AI --}}
                    <div class="rw-panel rw-panel-attitude">
                        <div class="rw-panel-head">
                            <span class="rw-side">Touchdown</span>
                            <span class="rw-id">Attitude</span>
                        </div>
                        <div class="rw-diagram attitude-diagram" x-show="attitude">
                            <svg viewBox="0 0 200 100" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                                <defs>
                                    <clipPath id="ai-clip-td-{{ $record->id }}">
                                        <rect x="0" y="0" width="200" height="100" rx="6"/>
                                    </clipPath>
                                </defs>
                                <g clip-path="url(#ai-clip-td-{{ $record->id }})">
                                    {{-- Horizon group rotates with roll, then shifts vertically with pitch. --}}
                                    <g :transform="attitude ? `rotate(${-attitude.rollRotation} 100 50) translate(0 ${attitude.pitchOffset})` : ''">
                                        <rect x="-200" y="-200" width="600" height="250" fill="#3b82f6"/>
                                        <rect x="-200" y="50" width="600" height="250" fill="#92400e"/>
                                        <line x1="-200" y1="50" x2="400" y2="50" stroke="#ffffff" stroke-width="1.5"/>
                                        {{-- Pitch ladder: 5° marks. Scale = 3px / deg, so ±5° = 15px, ±10° = 30px from horizon. --}}
                                        <g stroke="#ffffff" stroke-width="0.75" opacity="0.85" font-family="var(--font-mono)" font-size="6" fill="#ffffff">
                                            <line x1="88" y1="35" x2="112" y2="35"/>
                                            <text x="116" y="37" opacity="0.85">5</text>
                                            <line x1="84" y1="20" x2="116" y2="20"/>
                                            <text x="120" y="22" opacity="0.85">10</text>
                                            <line x1="88" y1="65" x2="112" y2="65"/>
                                            <text x="116" y="67" opacity="0.85">-5</text>
                                            <line x1="84" y1="80" x2="116" y2="80"/>
                                            <text x="120" y="82" opacity="0.85">-10</text>
                                        </g>
                                    </g>
                                    {{-- Static aircraft glyph + bank pointer --}}
                                    <g stroke="#facc15" stroke-width="2.5" stroke-linecap="round" fill="none">
                                        <line x1="80" y1="50" x2="92" y2="50"/>
                                        <line x1="108" y1="50" x2="120" y2="50"/>
                                    </g>
                                    <circle cx="100" cy="50" r="2" fill="#facc15"/>
                                    <g fill="#ffffff">
                                        <path d="M 100 12 L 96 18 L 104 18 Z"/>
                                    </g>
                                </g>
                            </svg>
                        </div>
                        <div class="rw-facts">
                            @if (filled($landing['scorecard']['pitch']['value'] ?? null))
                                <div class="fact-inline">
                                    <span class="k">Pitch</span>
                                    <span class="v">{{ number_format((float) $landing['scorecard']['pitch']['value'], 2) }}°</span>
                                </div>
                            @endif
                            @if (filled($landing['scorecard']['roll']['value'] ?? null))
                                <div class="fact-inline">
                                    <span class="k">Roll</span>
                                    <span class="v">{{ number_format((float) $landing['scorecard']['roll']['value'], 2) }}°</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
