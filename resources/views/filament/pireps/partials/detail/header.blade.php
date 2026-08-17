{{--
    Five-cell operating-figures strip (mockup pirep.html:493-541, classes
    theme.css .overview / .overview__*, same pattern as
    resources/views/filament/widgets/dashboard/stats-strip.blade.php).
    Filament's native page header (getHeading/getSubheading) renders the
    ident/reg/route + pilot/filed line above this — no custom avatar hero.
--}}
@php
    use App\Support\Units\Time;

    /** @var \App\Models\Pirep $record */
    $unitDistance = setting('units.distance');
    $unitFuel = setting('units.fuel');

    // Block time vs plan.
    $timeDelta = ($record->planned_flight_time && $record->flight_time)
        ? (int) $record->flight_time - (int) $record->planned_flight_time
        : null;

    // Score band.
    $scoreClass = match (true) {
        $record->score === null => '',
        $record->score >= 90    => 'good',
        $record->score >= 70    => 'warn',
        default                 => 'bad',
    };

    // Landing rate band — mirrors PirepsTable::configure's landing_rate
    // ->color() thresholds so the view page matches the list page.
    $landingRate = $record->landing_rate !== null ? (float) $record->landing_rate : null;
    $rateClass = match (true) {
        $landingRate === null || (int) $landingRate === 0 => '',
        $landingRate > 0, $landingRate <= -400             => 'rate--hard',
        $landingRate <= -250                               => 'rate--firm',
        $landingRate > -150                                => 'rate--good',
        default                                             => '',
    };
    $landingNote = match ($rateClass) {
        'rate--good' => 'Normal band',
        'rate--firm' => 'Elevated rate',
        'rate--hard' => 'Hard landing',
        default      => '—',
    };

    $distancePlanned = $record->planned_distance?->local();
    $fuelRemaining = ($record->block_fuel && $record->fuel_used)
        ? (float) $record->block_fuel->local() - (float) $record->fuel_used->local()
        : null;
@endphp

<section class="overview" aria-label="Flight figures">
    <div class="overview__cell">
        <span class="overview__icon overview__icon--blue">@svg('phosphor-clock-light')</span>
        <span class="overview__label">{{ __('pireps.flight_time') }}</span>
        <span class="overview__value">{{ sprintf('%d:%02d', ...array_values(Time::minutesToTimeParts((int) ($record->flight_time ?? 0)))) }}</span>
        @if ($timeDelta !== null)
            <span class="overview__note {{ $timeDelta <= 0 ? 'overview__note--ok' : '' }}">
                @if ($timeDelta <= 0)
                    @svg('phosphor-check-light') On plan
                @else
                    +{{ $timeDelta }}m vs plan
                @endif
            </span>
        @else
            <span class="overview__note">—</span>
        @endif
    </div>

    <div class="overview__cell">
        <span class="overview__icon overview__icon--teal">@svg('phosphor-ruler-light')</span>
        <span class="overview__label">{{ __('common.distance') }}</span>
        <span class="overview__value">
            @if ($record->distance){{ number_format((float) $record->distance->local()) }}<small>{{ $unitDistance }}</small>@else —@endif
        </span>
        <span class="overview__note">{{ $distancePlanned ? number_format((float) $distancePlanned).' planned' : '—' }}</span>
    </div>

    <div class="overview__cell">
        <span class="overview__icon overview__icon--violet">@svg('phosphor-gauge-light')</span>
        <span class="overview__label">{{ __('pireps.score') }}</span>
        <span class="overview__value">{{ $record->score ?? '—' }}<small>/100</small></span>
        <span class="overview__note">out of 100</span>
    </div>

    <div class="overview__cell">
        <span class="overview__icon overview__icon--rose">@svg('phosphor-airplane-landing-light')</span>
        <span class="overview__label">{{ __('pireps.landing_rate') }}</span>
        <span class="overview__value">
            @if ($landingRate)<span class="rate {{ $rateClass }}">{{ number_format($landingRate) }}</span><small>fpm</small>@else —@endif
        </span>
        <span class="overview__note">{{ $landingNote }}</span>
    </div>

    <div class="overview__cell">
        <span class="overview__icon overview__icon--amber">@svg('phosphor-gas-pump-light')</span>
        <span class="overview__label">{{ __('pireps.fuel_used') }}</span>
        <span class="overview__value">
            @if ($record->fuel_used){{ number_format((float) $record->fuel_used->local()) }}<small>{{ $unitFuel }}</small>@else —@endif
        </span>
        <span class="overview__note">{{ $fuelRemaining !== null ? number_format($fuelRemaining).' remaining' : '—' }}</span>
    </div>
</section>
