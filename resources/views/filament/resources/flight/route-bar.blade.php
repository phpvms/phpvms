{{--
    Route summary band for the flight edit form's Route section.

    Reuses the .route-bar family from the PIREP detail view (theme.css) so the
    schedule and the flown report read the same way.

    Fed from live form state rather than the saved record — the airports and
    times immediately below it are `live`, so changing one has to move the band
    above it in the same round trip. See FlightForm::routeBarData().
--}}
<div class="route-bar route-bar--bleed">
    <div class="route-bar__end">
        <div class="route-bar__icao">{{ $dptIcao ?: '—' }}</div>
        <div class="route-bar__name">{{ $dptName }}</div>
        @if ($departureTime)
            <div class="route-bar__time">{{ __('flights.departuretime') }} {{ $departureTime }}L</div>
        @endif
    </div>

    <div class="route-bar__mid">
        <span class="route-bar__figures">
            {{ $blockTime }}@if ($distance) · {{ $distance }} {{ __('common.nautical_miles_short') }}@endif
        </span>
        <span class="route-bar__leg"></span>
        @if ($level)
            <span class="route-bar__figures text-(--ink-3)">FL{{ $level }}</span>
        @endif
    </div>

    <div class="route-bar__end route-bar__end--arr">
        <div class="route-bar__icao">{{ $arrIcao ?: '—' }}</div>
        <div class="route-bar__name">{{ $arrName }}</div>
        @if ($arrivalTime)
            <div class="route-bar__time">{{ __('flights.arrivaltime') }} {{ $arrivalTime }}L</div>
        @endif
    </div>
</div>
