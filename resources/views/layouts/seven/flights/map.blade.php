<div class="row">
  <div class="col-12">
    <div class="box-body">
      <div id="map" style="width: 100%; height: 600px"></div>
    </div>
  </div>
</div>

@section('scripts')
  <script type="text/javascript">
    phpvms.map.render_route_map({
      route_points: {!! json_encode($map_features['route_points']) !!},
      planned_route_line: {!! json_encode($map_features['planned_route_line']) !!},
      metar_wms: {!! json_encode(config('map.metar_wms')) !!},
      circle_color: '#056093',
      flightplan_route_color: '#8B008B',
    });
  </script>
@endsection
