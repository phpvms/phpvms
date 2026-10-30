{{-- Decoded METAR widget. Data comes from App\Widgets\Weather: config, metar, taf, unit_alt, unit_dist, unit_temp. --}}
@if ($metar)
    <section class="pv-widget grid gap-2 rounded-md border border-line bg-panel p-4" aria-label="@lang('dashboard.weatherat', ['ICAO' => $config['icao']])">
        <p class="micro">@lang('dashboard.weatherat', ['ICAO' => $config['icao']])</p>
        <p class="break-words font-mono text-[11px] leading-relaxed text-ink">{{ $metar['raw'] }}</p>

        <details class="text-[13px]">
            <summary class="cursor-pointer text-accent">{{ __('widgets.weather.showdecoded') }}</summary>
            <dl class="mt-2 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1">
                @if ($config['raw_only'] != true)
                    <dt class="text-ink-dim">@lang('widgets.weather.conditions')</dt>
                    <dd>{{ $metar['category'] }}</dd>

                    <dt class="text-ink-dim">@lang('widgets.weather.wind')</dt>
                    <dd>
                        @if ($metar['wind_speed'] < '1')
                            Calm
                        @else
                            {{ $metar['wind_speed'] }} kts @lang('common.from') {{ $metar['wind_direction_label'] }} ({{ $metar['wind_direction'] }}&deg;)
                        @endif
                        @if ($metar['wind_gust_speed'])
                            @lang('widgets.weather.guststo') {{ $metar['wind_gust_speed'] }}
                        @endif
                    </dd>

                    @if ($metar['visibility'])
                        <dt class="text-ink-dim">Visibility</dt>
                        <dd>{{ $metar['visibility'][$unit_dist] }} {{ $unit_dist }}</dd>
                    @endif

                    @if ($metar['present_weather_report'] && $metar['present_weather_report'] != 'Dry')
                        <dt class="text-ink-dim">Phenomena</dt>
                        <dd>{{ $metar['present_weather_report'] }}</dd>
                    @endif

                    @if ($metar['clouds'] || $metar['cavok'])
                        <dt class="text-ink-dim">@lang('widgets.weather.clouds')</dt>
                        <dd>
                            {{ $unit_alt === 'ft' ? $metar['clouds_report_ft'] : $metar['clouds_report'] }}
                            @if ($metar['cavok'] == 1)
                                Ceiling and Visibility OK
                            @endif
                        </dd>
                    @endif

                    @if ($metar['temperature'])
                        <dt class="text-ink-dim">@lang('widgets.weather.temp')</dt>
                        <dd>
                            {{ $metar['temperature'][$unit_temp] ?: 0 }} &deg;{{ strtoupper($unit_temp) }}
                            @if ($metar['dew_point'])
                                , @lang('widgets.weather.dewpoint') {{ $metar['dew_point'][$unit_temp] ?: 0 }} &deg;{{ strtoupper($unit_temp) }}
                            @endif
                            @if ($metar['humidity'])
                                , @lang('widgets.weather.humidity') {{ $metar['humidity'] }}%
                            @endif
                        </dd>
                    @endif

                    @if ($metar['barometer'])
                        <dt class="text-ink-dim">@lang('widgets.weather.barometer')</dt>
                        <dd>{{ number_format($metar['barometer']['hPa']) }} hPa / {{ number_format($metar['barometer']['inHg'], 2) }} inHg</dd>
                    @endif

                    @if ($metar['remarks'])
                        <dt class="text-ink-dim">@lang('widgets.weather.remarks')</dt>
                        <dd>{{ $metar['remarks'] }}</dd>
                    @endif

                    <dt class="text-ink-dim">@lang('widgets.weather.updated')</dt>
                    <dd>{{ $metar['observed_time'] }} ({{ $metar['observed_age'] }})</dd>
                @endif

                <dt class="text-ink-dim">TAF</dt>
                <dd class="break-words font-mono text-[11px]">{{ $taf ? $taf['raw'] : __('widgets.weather.nometar') }}</dd>
            </dl>
        </details>
    </section>
@else
    <p class="pv-widget rounded-md border border-dashed border-line-strong p-4 text-center text-[13px] text-ink-dim">@lang('widgets.weather.nometar')</p>
@endif
