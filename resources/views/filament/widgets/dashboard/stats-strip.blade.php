{{--
    Five-cell operating-figures strip (mockup index.html:471-514, classes
    theme.css:overview__*). No <x-filament::section> wrapper — the strip is its
    own bordered container.
--}}
<x-filament-widgets::widget class="fi-widget">
    <section class="overview" aria-label="{{ __('filament.dashboard.key_figures') }}">
        <div class="overview__cell">
            <span class="overview__icon overview__icon--violet">@svg('tabler-file-text')</span>
            <span class="overview__label">{{ __('filament.dashboard.reports_filed') }}</span>
            <span class="overview__value">{{ number_format($reportsTotal) }}</span>
            <span class="overview__note">{{ $reportsAccepted }} accepted · {{ $reportsPending }} pending</span>
        </div>

        <div class="overview__cell">
            <span class="overview__icon overview__icon--blue">@svg('tabler-clock')</span>
            <span class="overview__label">{{ __('filament.dashboard.block_hours') }}</span>
            <span class="overview__value">{{ number_format($blockHours, 1) }}<small>h</small></span>
            <span class="overview__note">{{ $legsCount }} legs · {{ number_format($avgLegHours, 1) }} h average</span>
        </div>

        <div class="overview__cell">
            <span class="overview__icon overview__icon--teal">@svg('tabler-ruler-measure')</span>
            <span class="overview__label">{{ __('filament.dashboard.distance') }}</span>
            <span class="overview__value">{{ number_format($distanceTotal) }}<small>nm</small></span>
            <span class="overview__note">{{ number_format($avgLegDistance) }} nm average leg</span>
        </div>

        <div class="overview__cell">
            <span class="overview__icon overview__icon--rose">@svg('tabler-users')</span>
            <span class="overview__label">{{ __('filament.dashboard.pilots_flying') }}</span>
            <span class="overview__value">{{ number_format($pilotsFlying) }}<small>of {{ number_format($activePilots) }}</small></span>
            <span class="overview__note">
                @if ($topPilotName)
                    {{ $topPilotName }} leads on {{ $topPilotLegs }} legs
                @else
                    —
                @endif
            </span>
        </div>

        <div class="overview__cell">
            <span class="overview__icon overview__icon--amber">@svg('tabler-box')</span>
            <span class="overview__label">{{ __('filament.dashboard.tails_available') }}</span>
            <span class="overview__value">{{ number_format($tailsActive) }}<small>of {{ number_format($tailsTotal) }}</small></span>
            @if ($tailsMaintenance > 0)
                <span class="overview__note overview__note--warn">@svg('tabler-tool') {{ $tailsMaintenance }} in maintenance</span>
            @else
                <span class="overview__note">—</span>
            @endif
        </div>
    </section>
</x-filament-widgets::widget>
