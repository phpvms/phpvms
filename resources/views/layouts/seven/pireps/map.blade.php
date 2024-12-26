<div class="row">
  <div class="col-12">
    <div class="box-body">
      <div id="map" style="width: 100%; height: 800px"></div>
    </div>
  </div>
</div>

@section('scripts')
  <script type="text/javascript">
    phpvms.map.render_route_map({
      pirep_uri: '{!! url('/api/pireps/'.$pirep->id.'/acars/geojson') !!}',
      route_points: {!! json_encode($map_features['planned_rte_points'])  !!},
      planned_route_line: {!! json_encode($map_features['planned_rte_line']) !!},
      actual_route_line: {!! json_encode($map_features['actual_route_line']) !!},
      actual_route_points: {!! json_encode($map_features['actual_route_points']) !!},
      aircraft_icon: '{!! public_asset('/assets/img/acars/aircraft.png') !!}',
      flown_route_color: '#067ec1',
      circle_color: '#056093',
      flightplan_route_color: '#8B008B',
      leafletOptions: {
        scrollWheelZoom: false,
      },
    });
  </script>
@endsection
