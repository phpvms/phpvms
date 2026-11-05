<div class="vms-last-flight">
    @if ($pirep)
        <div class="vms-last-flight__top">
            <span>{{ $pirep->ident }}</span>
            <span>{{ $pirep->state?->getLabel() }}</span>
        </div>
        <div class="vms-last-flight__route">
            {{ $pirep->dpt_airport?->icao ?? '—' }} → {{ $pirep->arr_airport?->icao ?? '—' }}
        </div>
        <dl class="vms-last-flight__meta">
            <div><dt>{{ __('phpvmsdashboard::widgets.block') }}</dt><dd>{{ $block }}</dd></div>
            <div><dt>{{ __('phpvmsdashboard::widgets.aircraft') }}</dt><dd>{{ $pirep->aircraft?->registration ?? '—' }}</dd></div>
        </dl>
    @else
        <p class="vms-widget-empty">{{ __('phpvmsdashboard::widgets.last_none') }}</p>
    @endif
</div>
