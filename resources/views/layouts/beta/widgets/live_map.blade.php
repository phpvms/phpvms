<div class="row">
  <div class="col-md-12">
    <div class="box-body">

      {{--
      This map uses rivets.js to fill in the updates from the livemap
      So the single brackets are used by rivets to fill in the values
      And then the rv-* attributes are data-binds that will automatically
      update whenever the base model behind it updates:

          http://rivetsjs.com/docs/guide

      Look in resources/js/maps/live_map.js to see where the actual binding
      and update() call is made
      --}}
      <div id="map" style="width: {{ $config['width'] }}; height: {{ $config['height'] }}">

        {{--
        This is the bottom bar that appears when you click on a flight in the map.
        You can show any data you want - use a JS debugger to see the value of "pirep",
        or look up the API documentation for the /api/pirep/{id}/acars call

        It's basically any of the fields from the database and pirep.position.X is any
        column from the ACARS table - holds the latest position.

        Again, this is updated automatically via the rivets.js bindings, so be mindful
        when you're editing the { } - single brackets == rivets, double brackets == laravel

        A couple of places (like the distance) use both to output the correct bindings.
        --}}
        <div id="map-info-box" class="map-info-box" rv-show="pirep.id" style="width: {{ $config['width'] }};">
          <div style="float: left; width: 50%;">
            <h3 style="margin: 0" id="map_flight_id">
              <a rv-href="pirep.id | prepend '{{url('/pireps/')}}/'" target="_blank">
                { pirep.airline.icao }{ pirep.flight_number }
              </a>
            </h3>
            <p id="map_flight_info">
              { pirep.dpt_airport.name } ({ pirep.dpt_airport.icao }) @lang('common.to')
              { pirep.arr_airport.name } ({ pirep.arr_airport.icao })
            </p>
          </div>
          <div style="float: right; margin-left: 30px; margin-right: 30px;">
            <p id="map_flight_stats_right">
              @lang('widgets.livemap.groundspeed'): <span style="font-weight: bold">{ pirep.position.gs }</span><br/>
              @lang('widgets.livemap.altitude'): <span style="font-weight: bold">{ pirep.position.altitude }</span><br/>
              @lang('widgets.livemap.heading'): <span style="font-weight: bold">{ pirep.position.heading }</span><br/>
            </p>
          </div>
          <div style="float: right; margin-left: 30px;">
            <p id="map_flight_stats_middle">
              @lang('common.status'): <span style="font-weight: bold">{ pirep.status_text }</span><br/>
              @lang('flights.flighttime'): <span style="font-weight: bold">{ pirep.flight_time | time_hm }</span><br/>
              @lang('common.distance'): <span style="font-weight: bold">{ pirep.position.distance.{{setting('units.distance')}} }</span>
              / <span style="font-weight: bold">
                                        { pirep.planned_distance.{{setting('units.distance')}} }</span>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

@if($config['table'] === true)
<div class="clearfix" style="padding-top: 25px"></div>

{{--
This table is also handled/rendered by rivets from the livemap
Handles the updates by automatically updating the data in the row.

Same note applies from above about the data from the PIREP API being available
and being mindful of the rivets bindings
--}}
<div id="live_flights" class="row">
  <div class="col-md-12">
    <div rv-hide="has_data" class="jumbotron text-center">@lang('widgets.livemap.noflights')</div>
    <table rv-show="has_data" id="live_flights_table" class="table table-striped">
      <thead>
      <tr class="text-small header">
        <td class="text-small">{{ trans_choice('common.flight', 2) }}</td>
        <td class="text-small">@lang('common.departure')</td>
        <td class="text-small">@lang('common.arrival')</td>
        <td class="text-small">@lang('common.aircraft')</td>
        <td class="text-small">@lang('widgets.livemap.altitude')</td>
        <td class="text-small">@lang('widgets.livemap.gs')</td>
        <td class="text-small">@lang('widgets.livemap.distance')</td>
        <td class="text-small">@lang('common.status')</td>
      </tr>
      </thead>
      <tbody>
      <tr rv-each-pirep="pireps">
        <td><a href="#top_anchor" rv-on-click="controller.focusMarker">{ pirep.ident }</a></td>
        {{-- Show the full airport name on hover --}}
        <td><span rv-title="pirep.dpt_airport.name">{ pirep.dpt_airport.icao }</span></td>
        <td><span rv-title="pirep.arr_airport.name">{ pirep.arr_airport.icao }</span></td>
        <td>{ pirep.aircraft.registration }</td>
        <td>{ pirep.position.altitude }</td>
        <td>{ pirep.position.gs }</td>
        <td>{ pirep.position.distance.{{setting('units.distance')}} | fallback 0 } /
          { pirep.planned_distance.{{setting('units.distance')}} | fallback 0 }
        </td>
        <td>{ pirep.status_text }</td>
      </tr>
      </tbody>
    </table>
  </div>
</div>
@endif

@section('scripts')
  <script>
    phpvms.map.render_live_map({
      center: ['{{ $center[0] }}', '{{ $center[1] }}'],
      zoom: '{{ $zoom }}',
      aircraft_icon: '{!! public_asset('/assets/img/acars/aircraft.png') !!}',
      refresh_interval: {{ setting('acars.update_interval', 60) }},
      units: '{{ setting('units.distance') }}',
      flown_route_color: '#067ec1',
      leafletOptions: {
        scrollWheelZoom: false,
      }
    });
  </script>
@endsection
