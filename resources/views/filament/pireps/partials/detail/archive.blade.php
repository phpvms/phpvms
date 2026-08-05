@php
    use App\Enums\FlightType;
    use App\Support\Units\Time;

    /** @var \App\Models\Pirep $record */
    // Read only from the archived metadata columns — no Flight/Aircraft/SimBrief
    // relation fallback. Sections whose column is null are hidden entirely.
    $archive = $record->metadata;
    $currency = setting('units.currency');

    $flight = $archive?->flight;
    $aircraft = $archive?->aircraft;
    $simbrief = $archive?->simbrief;
@endphp

<div class="fi-pirep-detail-v2-card fi-pirep-detail-v2-archive">
    <div class="fi-pirep-detail-v2-card-head">
        <h3>{{ __('filament.original_flight') }}</h3>
    </div>

    @if (! $flight && ! $aircraft && ! $simbrief)
        <div class="fi-pirep-detail-v2-card-body flush">
            <p class="fi-pirep-detail-v2-empty-note">{{ __('filament.original_flight_empty') }}</p>
        </div>
    @else
        <div class="fi-pirep-detail-v2-card-body flush">
            @if ($flight)
                <div class="fi-pirep-fin-section-title">Flight</div>
                <div class="fi-pirep-detail-v2-facts stacked">
                    @if (filled($flight['callsign'] ?? null))
                        <div class="fact"><span class="k">Callsign</span><span class="v mono">{{ $flight['callsign'] }}</span></div>
                    @endif
                    @if (filled($flight['alt_airport_id'] ?? null))
                        <div class="fact"><span class="k">Alternate</span><span class="v mono">{{ $flight['alt_airport_id'] }}</span></div>
                    @endif
                    @if (filled($flight['flight_type'] ?? null))
                        <div class="fact"><span class="k">Flight Type</span><span class="v">{{ FlightType::tryFrom($flight['flight_type'])?->getLabel() ?? $flight['flight_type'] }}</span></div>
                    @endif
                    @if (filled($flight['dpt_time'] ?? null))
                        <div class="fact"><span class="k">Departure Time</span><span class="v mono">{{ $flight['dpt_time'] }}</span></div>
                    @endif
                    @if (filled($flight['arr_time'] ?? null))
                        <div class="fact"><span class="k">Arrival Time</span><span class="v mono">{{ $flight['arr_time'] }}</span></div>
                    @endif
                    @if (filled($flight['flight_time'] ?? null))
                        <div class="fact"><span class="k">Flight Time</span><span class="v">{{ Time::minutesToTimeString((int) $flight['flight_time']) }}</span></div>
                    @endif
                    @if (filled($flight['load_factor'] ?? null))
                        <div class="fact"><span class="k">Load Factor</span><span class="v">{{ $flight['load_factor'] }}%</span></div>
                    @endif
                    @if (filled($flight['load_factor_variance'] ?? null))
                        <div class="fact"><span class="k">Load Factor Variance</span><span class="v">±{{ $flight['load_factor_variance'] }}%</span></div>
                    @endif
                    @if (filled($flight['pilot_pay'] ?? null))
                        <div class="fact"><span class="k">Pilot Pay</span><span class="v">{{ \Illuminate\Support\Number::currency((float) $flight['pilot_pay'], $currency) }}</span></div>
                    @endif
                    @foreach (($flight['fields'] ?? []) as $slug => $value)
                        <div class="fact"><span class="k">{{ $slug }}</span><span class="v">{{ filled($value) ? $value : '—' }}</span></div>
                    @endforeach
                </div>
            @endif

            @if ($aircraft)
                <div class="fi-pirep-fin-section-title">Aircraft</div>
                <div class="fi-pirep-detail-v2-facts stacked">
                    @if (filled($aircraft['registration'] ?? null))
                        <div class="fact"><span class="k">Registration</span><span class="v mono">{{ $aircraft['registration'] }}</span></div>
                    @endif
                    @if (filled($aircraft['name'] ?? null))
                        <div class="fact"><span class="k">Name</span><span class="v">{{ $aircraft['name'] }}</span></div>
                    @endif
                    @if (filled($aircraft['icao'] ?? null) || filled($aircraft['iata'] ?? null))
                        <div class="fact"><span class="k">ICAO / IATA</span><span class="v mono">{{ $aircraft['icao'] ?? '—' }} / {{ $aircraft['iata'] ?? '—' }}</span></div>
                    @endif
                    @if (filled($aircraft['fin'] ?? null))
                        <div class="fact"><span class="k">Fin</span><span class="v mono">{{ $aircraft['fin'] }}</span></div>
                    @endif
                    @if (filled($aircraft['simbrief_type'] ?? null))
                        <div class="fact"><span class="k">SimBrief Type</span><span class="v mono">{{ $aircraft['simbrief_type'] }}</span></div>
                    @endif
                    @if (filled($aircraft['mtow'] ?? null))
                        <div class="fact"><span class="k">MTOW</span><span class="v">{{ number_format((float) $aircraft['mtow']) }}</span></div>
                    @endif
                    @if (filled($aircraft['zfw'] ?? null))
                        <div class="fact"><span class="k">ZFW</span><span class="v">{{ number_format((float) $aircraft['zfw']) }}</span></div>
                    @endif
                    @if ($subfleet = $aircraft['subfleet'] ?? null)
                        @if (filled($subfleet['name'] ?? null))
                            <div class="fact"><span class="k">Subfleet</span><span class="v">{{ $subfleet['name'] }}</span></div>
                        @endif
                        @if (filled($subfleet['type'] ?? null))
                            <div class="fact"><span class="k">Subfleet Type</span><span class="v mono">{{ $subfleet['type'] }}</span></div>
                        @endif
                    @endif
                </div>
            @endif

            @if ($simbrief)
                <div class="fi-pirep-fin-section-title">SimBrief Plan</div>
                <div class="fi-pirep-detail-v2-facts stacked">
                    @if (filled($simbrief['general']['route'] ?? null))
                        <div class="fact"><span class="k">Route</span><span class="v mono">{{ $simbrief['general']['route'] }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['route_distance'] ?? null))
                        <div class="fact"><span class="k">Route Distance</span><span class="v">{{ $simbrief['general']['route_distance'] }} {{ setting('units.distance') }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['costindex'] ?? null))
                        <div class="fact"><span class="k">Cost Index</span><span class="v">{{ $simbrief['general']['costindex'] }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['initial_altitude'] ?? null))
                        <div class="fact"><span class="k">Initial Altitude</span><span class="v">{{ $simbrief['general']['initial_altitude'] }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['passengers'] ?? null))
                        <div class="fact"><span class="k">Passengers</span><span class="v">{{ $simbrief['general']['passengers'] }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['climb_profile'] ?? null))
                        <div class="fact"><span class="k">Climb Profile</span><span class="v mono">{{ $simbrief['general']['climb_profile'] }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['cruise_profile'] ?? null))
                        <div class="fact"><span class="k">Cruise Profile</span><span class="v mono">{{ $simbrief['general']['cruise_profile'] }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['descent_profile'] ?? null))
                        <div class="fact"><span class="k">Descent Profile</span><span class="v mono">{{ $simbrief['general']['descent_profile'] }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['reserve_profile'] ?? null))
                        <div class="fact"><span class="k">Reserve Profile</span><span class="v mono">{{ $simbrief['general']['reserve_profile'] }}</span></div>
                    @endif
                    @if (filled($simbrief['general']['stepclimb_string'] ?? null))
                        <div class="fact"><span class="k">Stepclimbs</span><span class="v mono">{{ $simbrief['general']['stepclimb_string'] }}</span></div>
                    @endif
                    @if (filled($simbrief['times']['est_time_enroute'] ?? null))
                        <div class="fact"><span class="k">Est. Time Enroute</span><span class="v">{{ $simbrief['times']['est_time_enroute'] }}</span></div>
                    @endif
                    @if (filled($simbrief['times']['sched_time_enroute'] ?? null))
                        <div class="fact"><span class="k">Sched. Time Enroute</span><span class="v">{{ $simbrief['times']['sched_time_enroute'] }}</span></div>
                    @endif
                    @if (filled($simbrief['times']['est_block'] ?? null))
                        <div class="fact"><span class="k">Est. Block Time</span><span class="v">{{ $simbrief['times']['est_block'] }}</span></div>
                    @endif
                    @if (filled($simbrief['times']['sched_block'] ?? null))
                        <div class="fact"><span class="k">Sched. Block Time</span><span class="v">{{ $simbrief['times']['sched_block'] }}</span></div>
                    @endif
                    @if (filled($simbrief['times']['reserve_time'] ?? null))
                        <div class="fact"><span class="k">Reserve Time</span><span class="v">{{ $simbrief['times']['reserve_time'] }}</span></div>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
