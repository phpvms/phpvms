@php
    use App\Support\Units\Time;
    use Filament\Support\Facades\FilamentAsset;

    /** @var \App\Models\Pirep $record */
    /** @var array<string,mixed> $mapFeatures */
    /** @var array<string,mixed>|null $performance */

    $hasRouteMap = ! empty($mapFeatures);
    $mapElementId = 'pirep-route-map-'.$record->id;

    $blockOff = $record->block_off_time?->format('H:i');
    $blockOn = $record->block_on_time?->format('H:i');
    $unitDistance = setting('units.distance');

    $blockTime = $record->flight_time
        ? sprintf('%d:%02d', ...array_values(Time::minutesToTimeParts((int) $record->flight_time)))
        : null;

    $cruiseFt = $record->level ?? 0;
    $cruiseFL = $cruiseFt > 0 ? 'FL'.str_pad((string) (int) ($cruiseFt / 100), 3, '0', STR_PAD_LEFT) : null;
@endphp

{{-- Route bar --}}
<div class="route-bar">
    <div class="route-bar__end">
        <div class="route-bar__icao">{{ $record->dpt_airport_id }}</div>
        @if ($record->dpt_airport?->name)
            <div class="route-bar__name">{{ $record->dpt_airport->name }}</div>
        @endif
        @if ($blockOff)
            <div class="route-bar__time">Block off {{ $blockOff }}Z</div>
        @endif
    </div>
    <div class="route-bar__mid">
        <span class="route-bar__figures">
            {{ $blockTime ?? '—' }}
            @if ($record->distance) · {{ number_format((float) $record->distance->local()) }} {{ $unitDistance }}@endif
        </span>
        <span class="route-bar__leg"></span>
        @if ($record->aircraft?->icao || $cruiseFL)
            <span class="route-bar__figures text-ink-3">{{ $record->aircraft?->icao }}@if ($record->aircraft?->icao && $cruiseFL) · @endif{{ $cruiseFL }}</span>
        @endif
    </div>
    <div class="route-bar__end route-bar__end--arr">
        <div class="route-bar__icao">{{ $record->arr_airport_id }}</div>
        @if ($record->arr_airport?->name)
            <div class="route-bar__name">{{ $record->arr_airport->name }}</div>
        @endif
        @if ($blockOn)
            <div class="route-bar__time">Block on {{ $blockOn }}Z</div>
        @endif
    </div>
</div>

@if (filled($record->route))
    <p class="route-string"><b>{{ $record->dpt_airport_id }}</b> {{ $record->route }} <b>{{ $record->arr_airport_id }}</b></p>
@endif

{{-- Map (lazy-loaded Leaflet + phpvms admin maps) --}}
@if ($hasRouteMap)
    {{-- wire:ignore: Leaflet owns this subtree. Any Livewire re-render of the
         page (accepting/rejecting the PIREP, a header action) would otherwise
         morph the tiles away, and Alpine's init() won't re-run on the
         surviving element to rebuild it. --}}
    <div
        wire:ignore
        class="route-map"
        x-data="{
            async init() {
                // window.phpvms is set by resources/js/admin/app.js,
                // which is injected into every admin page via the
                // HEAD_END render hook in AdminPanelProvider. Vite
                // serves it as `<script type=module>`, so it runs
                // after DOM parsing — Alpine's init() can land
                // before window.phpvms exists. Await the ready
                // signal (a resolved Promise once app.js finishes;
                // a one-shot event listener while it's loading).
                const phpvms = await (window.phpvmsReady ?? new Promise(resolve => {
                    window.addEventListener('phpvms:ready', e => resolve(e.detail), { once: true });
                }));

                // map.render_route_map dynamic-imports ./maps, so
                // Leaflet only loads here, not on every admin page.
                phpvms.map.render_route_map({
                    render_elem: @js($mapElementId),
                    route_points:        @js($mapFeatures['planned_rte_points'] ?? null),
                    planned_route_line:  @js($mapFeatures['planned_rte_line'] ?? null),
                    actual_route_line:   @js($mapFeatures['actual_route_line'] ?? null),
                    actual_route_points: @js($mapFeatures['actual_route_points'] ?? null),
                    archived_route_line: @js($mapFeatures['archived_rte_line'] ?? null),
                    flown_route_color: '#067ec1',
                    circle_color: '#056093',
                    flightplan_route_color: '#8B008B',
                    archived_route_color: '#9ca3af',
                    leafletOptions: { scrollWheelZoom: false },
                });
            }
        }"
        x-load-css="[@js(FilamentAsset::getStyleHref('leaflet'))]"
    >
        <div id="{{ $mapElementId }}" style="width:100%;height:320px;"></div>
    </div>
@endif

{{-- Performance: empty stub when no ACARS --}}
@if ($performance === null)
    <div class="panel__body panel__body--centred">
        <p class="text-ink-3 text-sm">No ACARS data for this PIREP. Performance charts (altitude, speed, fuel,
            vertical speed) appear here once the pilot's ACARS client uploads flight samples.</p>
    </div>
@else
    @php
        $summary = $performance['summary'] ?? null;
        $fmtDuration = function (int $seconds): string {
            if ($seconds <= 0) {
                return '—';
            }

            $h = intdiv($seconds, 3600);
            $m = intdiv($seconds % 3600, 60);
            $s = $seconds % 60;

            if ($h > 0) {
                return sprintf('%dh %02dm', $h, $m);
            }

            return sprintf('%dm %02ds', $m, $s);
        };
    @endphp

    {{-- Chart container. The segmented series-picker buttons live inside the
         same Alpine component (pirepPerformanceChart) as the canvas so
         `select()` can swap the dataset without a second x-data scope. --}}
    <div wire:ignore>
        <div
            class="perf-chart"
            x-load
            x-load-src="{{ FilamentAsset::getAlpineComponentSrc('pirep-performance-chart') }}"
            x-data="pirepPerformanceChart(@js($performance))"
        >
            <div class="panel__head rounded-none">
                <h2 class="panel__title">@svg('tabler-chart-line') Vertical profile <em>{{ number_format($performance['sample_count']) }} samples</em></h2>
                <div class="panel__tools">
                    <div class="segmented" role="group" aria-label="Profile series">
                        <button type="button" :aria-pressed="active === 'altitude'" @click="select('altitude')">Altitude</button>
                        <button type="button" :aria-pressed="active === 'speed'" @click="select('speed')">Speed</button>
                        <button type="button" :aria-pressed="active === 'fuel'" @click="select('fuel')">Fuel</button>
                        <button type="button" :aria-pressed="active === 'vs'" @click="select('vs')">V/S</button>
                    </div>
                </div>
            </div>
            <div class="panel__body panel__body--centred">
                <canvas x-ref="canvas" style="width:100%;height:260px;"></canvas>
            </div>
        </div>
    </div>

    {{-- Phase legend: climb / cruise / descent durations + cruise altitude,
         derived from PerformanceChartService->buildSummary(). --}}
    @if ($summary)
        <div class="phase-legend">
            <div class="phase-legend__item">
                <span class="phase-legend__label"><i class="phase-legend__swatch bg-info-line"></i>Climb</span>
                <span class="phase-legend__value">{{ $fmtDuration((int) $summary['climb_seconds']) }}</span>
            </div>
            <div class="phase-legend__item">
                <span class="phase-legend__label"><i class="phase-legend__swatch bg-accent-line"></i>Cruise</span>
                <span class="phase-legend__value">{{ $fmtDuration((int) $summary['cruise_seconds']) }}</span>
            </div>
            <div class="phase-legend__item">
                <span class="phase-legend__label"><i class="phase-legend__swatch bg-warn-line"></i>Descent</span>
                <span class="phase-legend__value">{{ $fmtDuration((int) $summary['descent_seconds']) }}</span>
            </div>
            <div class="phase-legend__item">
                <span class="phase-legend__label">Cruise alt</span>
                <span class="phase-legend__value">
                    @if (filled($summary['cruise_altitude']))
                        FL{{ str_pad((string) (int) ($summary['cruise_altitude'] / 100), 3, '0', STR_PAD_LEFT) }}
                    @else
                        —
                    @endif
                </span>
            </div>
        </div>
    @endif
@endif

{{-- Touchdown readout: landing rate + scorecard raw values. Ground speed /
     IAS / exact touchdown sim-time aren't captured per-landing today (only
     the discrete PirepEvent log has timestamps, not a touchdown marker) so
     this reads landing_rate + the scorecard's g-force/pitch/roll instead. --}}
{{-- Section always renders — empty charts still get their header, the
     readout shows dashes until data exists. --}}
@php $landing = $performance['landing'] ?? null; @endphp
@php
        $landingRate = $record->landing_rate !== null ? (float) $record->landing_rate : null;
        $rateClass = match (true) {
            $landingRate === null || (int) $landingRate === 0 => '',
            $landingRate > 0, $landingRate <= -400             => 'rate--hard',
            $landingRate <= -250                               => 'rate--firm',
            $landingRate > -150                                => 'rate--good',
            default                                             => '',
        };
        $bandLabel = match ($rateClass) {
            'rate--good' => 'Normal',
            'rate--firm' => 'Elevated',
            'rate--hard' => 'Hard',
            default      => '—',
        };
        $scorecard = $landing['scorecard'] ?? null;
        $arrivalRunway = $landing['arrival']['runway'] ?? null;
    @endphp
    <div class="panel__head border-t border-line rounded-none">
        <h2 class="panel__title">@svg('tabler-plane-arrival') Touchdown</h2>
    </div>
    <div class="readout">
        <div>
            <dt>Rate</dt>
            <dd>@if ($landingRate)<span class="rate {{ $rateClass }}">{{ number_format($landingRate) }} fpm</span>@else —@endif</dd>
        </div>
        <div>
            <dt>G-force</dt>
            <dd>@if (filled($scorecard['g_force']['value'] ?? null)){{ number_format((float) $scorecard['g_force']['value'], 2) }}g @else — @endif</dd>
        </div>
        <div>
            <dt>Pitch</dt>
            <dd>@if (filled($scorecard['pitch']['value'] ?? null)){{ number_format((float) $scorecard['pitch']['value'], 1) }}° @else — @endif</dd>
        </div>
        <div>
            <dt>Roll</dt>
            <dd>@if (filled($scorecard['roll']['value'] ?? null)){{ number_format((float) $scorecard['roll']['value'], 1) }}° @else — @endif</dd>
        </div>
        <div>
            <dt>Runway</dt>
            <dd>@if ($arrivalRunway)<span class="id">{{ $arrivalRunway }}</span> · {{ $record->arr_airport_id }}@else {{ $record->arr_airport_id }} @endif</dd>
        </div>
        <div>
            <dt>Band</dt>
            <dd><span class="chip chip--{{ $rateClass === 'rate--hard' ? 'bad' : ($rateClass === 'rate--firm' ? 'warn' : 'ok') }} chip--plain">{{ $bandLabel }}</span></dd>
        </div>
    </div>

{{-- Landing analysis: runway plan-views + scorecard polar. Renders whenever
     buildLandingBlock() produced a payload — tiles inside show their empty
     states when the payload is missing pieces. --}}
@if ($landing)
    <div class="panel__head border-t border-line rounded-none">
        <h2 class="panel__title">@svg('tabler-target-arrow') Landing analysis</h2>
    </div>
    <div class="panel__body"
         x-load
         x-load-src="{{ FilamentAsset::getAlpineComponentSrc('pirep-landing-analysis') }}"
         x-data="pirepLandingAnalysis(@js($landing))">
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

            {{-- Landing scorecard polar --}}
            <div class="rw-panel rw-panel-scorecard">
                <div class="rw-panel-head">
                    <span class="rw-side">Landing</span>
                    <span class="rw-id">Scorecard</span>
                </div>
                <div class="scorecard-chart">
                    <canvas x-ref="scorecard"></canvas>
                </div>
            </div>

            {{-- Touchdown attitude indicator. --}}
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
                                <g :transform="attitude ? `rotate(${-attitude.rollRotation} 100 50) translate(0 ${attitude.pitchOffset})` : ''">
                                    <rect x="-200" y="-200" width="600" height="250" fill="#3b82f6"/>
                                    <rect x="-200" y="50" width="600" height="250" fill="#92400e"/>
                                    <line x1="-200" y1="50" x2="400" y2="50" stroke="#ffffff" stroke-width="1.5"/>
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
@endif

{{-- Notes --}}
@php
    $noteCount = ($record->comments?->count() ?? 0) + (filled($record->notes) ? 1 : 0);
@endphp
<div class="panel__head border-t border-line rounded-none">
    <h2 class="panel__title">@svg('tabler-notes') Notes @if ($noteCount > 0)<em>{{ $noteCount }}</em>@endif</h2>
</div>
@livewire(
    \App\Livewire\Filament\PirepCommentThread::class,
    ['record' => $record],
    key('pirep-notes-comments-'.$record->id)
)
