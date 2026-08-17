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

@if (! $flight && ! $aircraft && ! $simbrief)
    <div class="panel__body panel__body--centred">
        <p class="text-ink-3 text-sm">{{ __('filament.original_flight_empty') }}</p>
    </div>
@else
    @if ($flight)
        <div class="panel__head rounded-none">
            <h2 class="panel__title">@svg('phosphor-airplane-light') Flight <em>as dispatched</em></h2>
        </div>
        <div class="readout">
            @if (filled($flight['callsign'] ?? null))
                <div><dt>Callsign</dt><dd><span class="id">{{ $flight['callsign'] }}</span></dd></div>
            @endif
            @if (filled($flight['flight_type'] ?? null))
                <div><dt>Flight type</dt><dd>{{ FlightType::tryFrom($flight['flight_type'])?->getLabel() ?? $flight['flight_type'] }}</dd></div>
            @endif
            @if (filled($flight['alt_airport_id'] ?? null))
                <div><dt>Alternate</dt><dd><span class="id">{{ $flight['alt_airport_id'] }}</span></dd></div>
            @endif
            @if (filled($flight['dpt_time'] ?? null))
                <div><dt>Departure</dt><dd><span class="id">{{ $flight['dpt_time'] }}</span></dd></div>
            @endif
            @if (filled($flight['arr_time'] ?? null))
                <div><dt>Arrival</dt><dd><span class="id">{{ $flight['arr_time'] }}</span></dd></div>
            @endif
            @if (filled($flight['flight_time'] ?? null))
                <div><dt>Flight time</dt><dd><span class="id">{{ Time::minutesToTimeString((int) $flight['flight_time']) }}</span></dd></div>
            @endif
            @if (filled($flight['load_factor'] ?? null))
                <div><dt>Load factor</dt><dd><span class="id">{{ $flight['load_factor'] }}%</span>@if (filled($flight['load_factor_variance'] ?? null)) ±{{ $flight['load_factor_variance'] }}@endif</dd></div>
            @endif
            @if (filled($flight['pilot_pay'] ?? null))
                <div><dt>Pilot pay</dt><dd><span class="id">{{ \Illuminate\Support\Number::currency((float) $flight['pilot_pay'], $currency) }}</span></dd></div>
            @endif
            @foreach (($flight['fields'] ?? []) as $slug => $value)
                <div><dt>{{ $slug }}</dt><dd>{{ filled($value) ? $value : '—' }}</dd></div>
            @endforeach
        </div>
    @endif

    @if ($aircraft)
        <div class="panel__head border-t border-line rounded-none">
            <h2 class="panel__title">@svg('phosphor-airplane-tilt-light') Aircraft</h2>
        </div>
        <div class="readout">
            @if (filled($aircraft['registration'] ?? null))
                <div><dt>Registration</dt><dd><span class="id">{{ $aircraft['registration'] }}</span></dd></div>
            @endif
            @if (filled($aircraft['name'] ?? null))
                <div><dt>Name</dt><dd>{{ $aircraft['name'] }}</dd></div>
            @endif
            @if (filled($aircraft['icao'] ?? null) || filled($aircraft['iata'] ?? null))
                <div><dt>ICAO / IATA</dt><dd><span class="id">{{ $aircraft['icao'] ?? '—' }} / {{ $aircraft['iata'] ?? '—' }}</span></dd></div>
            @endif
            @if ($subfleet = $aircraft['subfleet'] ?? null)
                @if (filled($subfleet['name'] ?? null))
                    <div><dt>Subfleet</dt><dd>{{ $subfleet['name'] }}</dd></div>
                @endif
                @if (filled($subfleet['type'] ?? null))
                    <div><dt>Subfleet type</dt><dd><span class="id">{{ $subfleet['type'] }}</span></dd></div>
                @endif
            @endif
            @if (filled($aircraft['fin'] ?? null))
                <div><dt>Fin</dt><dd><span class="id">{{ $aircraft['fin'] }}</span></dd></div>
            @endif
            @if (filled($aircraft['simbrief_type'] ?? null))
                <div><dt>SimBrief type</dt><dd><span class="id">{{ $aircraft['simbrief_type'] }}</span></dd></div>
            @endif
            @if (filled($aircraft['mtow'] ?? null))
                <div><dt>MTOW</dt><dd><span class="id">{{ number_format((float) $aircraft['mtow']) }}</span></dd></div>
            @endif
            @if (filled($aircraft['zfw'] ?? null))
                <div><dt>ZFW</dt><dd><span class="id">{{ number_format((float) $aircraft['zfw']) }}</span></dd></div>
            @endif
        </div>
    @endif

    @if ($simbrief)
        <div class="panel__head border-t border-line rounded-none">
            <h2 class="panel__title">@svg('phosphor-path-light') SimBrief plan</h2>
        </div>
        <div class="readout">
            @if (filled($simbrief['general']['route_distance'] ?? null))
                <div><dt>Route distance</dt><dd><span class="id">{{ $simbrief['general']['route_distance'] }}</span> {{ setting('units.distance') }}</dd></div>
            @endif
            @if (filled($simbrief['general']['costindex'] ?? null))
                <div><dt>Cost index</dt><dd><span class="id">{{ $simbrief['general']['costindex'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['general']['initial_altitude'] ?? null))
                <div><dt>Initial altitude</dt><dd><span class="id">{{ $simbrief['general']['initial_altitude'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['general']['passengers'] ?? null))
                <div><dt>Passengers</dt><dd><span class="id">{{ $simbrief['general']['passengers'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['general']['route'] ?? null))
                <div><dt>Route</dt><dd><span class="id">{{ $simbrief['general']['route'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['general']['climb_profile'] ?? null))
                <div><dt>Climb profile</dt><dd><span class="id">{{ $simbrief['general']['climb_profile'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['general']['cruise_profile'] ?? null))
                <div><dt>Cruise profile</dt><dd><span class="id">{{ $simbrief['general']['cruise_profile'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['general']['descent_profile'] ?? null))
                <div><dt>Descent profile</dt><dd><span class="id">{{ $simbrief['general']['descent_profile'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['general']['reserve_profile'] ?? null))
                <div><dt>Reserve profile</dt><dd><span class="id">{{ $simbrief['general']['reserve_profile'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['general']['stepclimb_string'] ?? null))
                <div><dt>Stepclimbs</dt><dd><span class="id">{{ $simbrief['general']['stepclimb_string'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['times']['est_time_enroute'] ?? null))
                <div><dt>Est. enroute</dt><dd><span class="id">{{ $simbrief['times']['est_time_enroute'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['times']['sched_time_enroute'] ?? null))
                <div><dt>Sched. enroute</dt><dd><span class="id">{{ $simbrief['times']['sched_time_enroute'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['times']['est_block'] ?? null))
                <div><dt>Est. block</dt><dd><span class="id">{{ $simbrief['times']['est_block'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['times']['sched_block'] ?? null))
                <div><dt>Sched. block</dt><dd><span class="id">{{ $simbrief['times']['sched_block'] }}</span></dd></div>
            @endif
            @if (filled($simbrief['times']['reserve_time'] ?? null))
                <div><dt>Reserve time</dt><dd><span class="id">{{ $simbrief['times']['reserve_time'] }}</span></dd></div>
            @endif
        </div>
    @endif

    <div class="panel__foot">
        <span>Snapshot taken when the flight was dispatched. It does not change if the schedule is edited later.</span>
    </div>
@endif
