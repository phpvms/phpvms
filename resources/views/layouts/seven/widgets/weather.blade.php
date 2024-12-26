{{--

If you want to edit this, you can reference the CheckWX API docs:
https://api.checkwx.com/#metar-decoded

--}}
<table class="table table-striped">
  @if($config['raw_only'] != true && $metar)
    <tr>
      <td>@lang('widgets.weather.conditions')</td>
      <td>{{ $metar['category'] }}</td>
    </tr>
    <tr>
      <td>@lang('widgets.weather.wind')</td>
      <td>
        @if($metar['wind_speed'] < '1') Calm @else {{ $metar['wind_speed'] }} kts @lang('common.from') {{ $metar['wind_direction_label'] }} ({{ $metar['wind_direction']}}&deg;) @endif
        @if($metar['wind_gust_speed']) @lang('widgets.weather.guststo') {{ $metar['wind_gust_speed'] }} @endif
      </td>
    </tr>
    @if($metar['visibility'])
     <tr>
       <td>Visibility</td>
       <td>{{ $metar['visibility'][$unit_dist] }} {{$unit_dist}}</td>
     </tr>
    @endif
    @if($metar['runways_visual_range'])
     <tr>
      <td>Runway Visual Range</td>
      <td>
        @foreach($metar['runways_visual_range'] as $rvr)
          <b>RWY{{ $rvr['runway'] }}</b>; {{ $rvr['report'] }}<br>
        @endforeach
      </td>
     </tr>
    @endif
    @if($metar['present_weather_report'] && $metar['present_weather_report'] <> 'Dry')
     <tr>
      <td>Phenomena</td>
      <td>{{ $metar['present_weather_report'] }}</td>
     </tr>
    @endif
    @if($metar['clouds'] || $metar['cavok'])
     <tr>
      <td>@lang('widgets.weather.clouds')</td>
      <td>
        @if($unit_alt === 'ft') {{ $metar['clouds_report_ft'] }} @else {{ $metar['clouds_report'] }} @endif 
        @if($metar['cavok'] == 1) Ceiling and Visibility OK @endif
      </td>
     </tr>
    @endif
    @if($metar['temperature'])
      <tr>
        <td>@lang('widgets.weather.temp')</td>
        <td>
          @if($metar['temperature'][$unit_temp]) {{ $metar['temperature'][$unit_temp] }} @else 0 @endif &deg;{{strtoupper($unit_temp)}}
          @if($metar['dew_point']), @lang('widgets.weather.dewpoint') @if($metar['dew_point'][$unit_temp]) {{ $metar['dew_point'][$unit_temp] }} @else 0 @endif &deg;{{strtoupper($unit_temp)}} @endif 
          @if($metar['humidity']), @lang('widgets.weather.humidity') {{ $metar['humidity'] }}%  @endif
        </td>
      </tr>
    @endif
    @if($metar['barometer'])
      <tr>
        <td>@lang('widgets.weather.barometer')</td>
        <td>{{ number_format($metar['barometer']['hPa']) }} hPa / {{ number_format($metar['barometer']['inHg'], 2) }} inHg</td>
      </tr>
    @endif
    @if($metar['recent_weather_report'])
     <tr>
      <td>Recent Phenomena</td>
      <td>{{ $metar['recent_weather_report'] }}</td>
     </tr>
    @endif
    @if($metar['runways_report'])
     <tr>
      <td>Runway Condition</td>
      <td>
        @foreach($metar['runways_report'] as $runway)
          <b>RWY{{ $runway['runway'] }}</b>; {{ $runway['report'] }}<br>
        @endforeach
      </td>
     </tr>	
    @endif
    @if($metar['remarks'])
      <tr>
        <td>@lang('widgets.weather.remarks')</td>
        <td>{{ $metar['remarks'] }}</td>
      </tr>
    @endif
    <tr>
      <td>@lang('widgets.weather.updated')</td>
      <td>{{$metar['observed_time']}} ({{$metar['observed_age']}})</td>
    </tr>
  @endif
    <tr>
      <td>@lang('common.metar')</td>
      <td>@if($metar) {{ $metar['raw'] }} @else @lang('widgets.weather.nometar') @endif</td>
    </tr>
    <tr>
      <td>TAF</td>
      <td>@if($taf) {{ $taf['raw'] }} @else @lang('widgets.weather.nometar') @endif</td>
    </tr>
</table>
