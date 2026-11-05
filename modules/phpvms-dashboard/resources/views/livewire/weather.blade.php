<div class="vms-weather">
    <div class="vms-weather__station">
        <span>{{ __('phpvmsdashboard::widgets.station') }}</span>
        <span>{{ $icao ?? "—" }}</span>
    </div>

    @if ($loading ?? false)
        <p class="vms-widget-empty" role="status">{{ __('phpvmsdashboard::widgets.loading') }}</p>
    @elseif ($weather)
        <dl class="vms-weather__rows">
            @if ($weather->conditions)
                <div><dt>{{ __('phpvmsdashboard::widgets.conditions') }}</dt><dd>{{ $weather->conditions }}</dd></div>
            @endif
            @if ($weather->temperature)
                <div><dt>{{ __('phpvmsdashboard::widgets.temperature') }}</dt><dd>{{ $weather->temperature }}</dd></div>
            @endif
            @if ($weather->wind)
                <div><dt>{{ __('phpvmsdashboard::widgets.wind') }}</dt><dd>{{ $weather->wind }}</dd></div>
            @endif
        </dl>
        @if ($weather->metar)
            <pre class="vms-weather__metar">{{ $weather->metar }}</pre>
        @endif
    @else
        <p class="vms-widget-empty">{{ __('phpvmsdashboard::widgets.weather_none') }}</p>
    @endif
</div>
