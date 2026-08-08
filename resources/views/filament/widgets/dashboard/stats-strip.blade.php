{{--
    Five-cell operating-figures strip (mockup index.html:471-514, classes
    theme.css:strip__*). No <x-filament::section> wrapper — the strip is its
    own bordered container.
--}}
<x-filament-widgets::widget class="fi-widget">
    <section class="strip" aria-label="{{ __('filament.dashboard.key_figures') }}">
        <div class="strip__cell">
            <span class="strip__icon strip__icon--violet">@svg('tabler-file-text')</span>
            <span class="strip__label">{{ __('filament.dashboard.reports_filed') }}</span>
            <span class="strip__value">{{ number_format($reportsTotal) }}</span>
            <span class="strip__note">{{ $reportsAccepted }} accepted · {{ $reportsPending }} pending</span>
        </div>

        <div class="strip__cell">
            <span class="strip__icon strip__icon--blue">@svg('tabler-clock')</span>
            <span class="strip__label">{{ __('filament.dashboard.block_hours') }}</span>
            <span class="strip__value">{{ number_format($blockHours, 1) }}<small>h</small></span>
            <span class="strip__note">{{ $legsCount }} legs · {{ number_format($avgLegHours, 1) }} h average</span>
        </div>

        <div class="strip__cell">
            <span class="strip__icon strip__icon--teal">@svg('tabler-ruler-measure')</span>
            <span class="strip__label">{{ __('filament.dashboard.distance') }}</span>
            <span class="strip__value">{{ number_format($distanceTotal) }}<small>nm</small></span>
            <span class="strip__note">{{ number_format($avgLegDistance) }} nm average leg</span>
        </div>

        <div class="strip__cell">
            <span class="strip__icon strip__icon--rose">@svg('tabler-users')</span>
            <span class="strip__label">{{ __('filament.dashboard.pilots_flying') }}</span>
            <span class="strip__value">{{ number_format($pilotsFlying) }}<small>of {{ number_format($activePilots) }}</small></span>
            <span class="strip__note">
                @if ($topPilotName)
                    {{ $topPilotName }} leads on {{ $topPilotLegs }} legs
                @else
                    —
                @endif
            </span>
        </div>

        <div class="strip__cell">
            <span class="strip__icon strip__icon--amber">@svg('tabler-box')</span>
            <span class="strip__label">{{ __('filament.dashboard.tails_available') }}</span>
            <span class="strip__value">{{ number_format($tailsActive) }}<small>of {{ number_format($tailsTotal) }}</small></span>
            @if ($tailsMaintenance > 0)
                <span class="strip__note strip__note--warn">@svg('tabler-tool') {{ $tailsMaintenance }} in maintenance</span>
            @else
                <span class="strip__note">—</span>
            @endif
        </div>
    </section>
</x-filament-widgets::widget>
