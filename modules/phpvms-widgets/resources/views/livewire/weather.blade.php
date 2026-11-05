<div class="vms-weather">
    <div class="vms-weather__station">
        <span>{{ __('phpvmswidgets::widgets.station') }}</span>
        <span>{{ $icao ?? "—" }}</span>
    </div>

    @if ($loading ?? false)
        <p class="vms-widget-empty" role="status">{{ __('phpvmswidgets::widgets.loading') }}</p>
    @elseif ($weather)
        <dl class="vms-weather__rows">
            @if ($weather->conditions)
                <div><dt>{{ __('phpvmswidgets::widgets.conditions') }}</dt><dd>{{ $weather->conditions }}</dd></div>
            @endif
            @if ($weather->temperature)
                <div><dt>{{ __('phpvmswidgets::widgets.temperature') }}</dt><dd>{{ $weather->temperature }}</dd></div>
            @endif
            @if ($weather->wind)
                <div><dt>{{ __('phpvmswidgets::widgets.wind') }}</dt><dd>{{ $weather->wind }}</dd></div>
            @endif
        </dl>
        @if ($weather->metar)
            <pre class="vms-weather__metar">{{ $weather->metar }}</pre>
        @endif
    @else
        <p class="vms-widget-empty">{{ __('phpvmswidgets::widgets.weather_none') }}</p>
    @endif
</div>
