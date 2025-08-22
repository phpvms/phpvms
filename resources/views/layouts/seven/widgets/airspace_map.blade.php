<div id="map" style="width: {{ $config['width'] }}; height: {{ $config['height'] }}"></div>

@section('css')
  @parent
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" integrity="sha512-Zcn6bjR/8RZbLEpLIeOwNtzREBAJnUKESxces60Mpoj+2okopSAcSUIUOseddDm0cxnGQzxIR7vJgsLZbdLE3w==" crossorigin="anonymous" referrerpolicy="no-referrer" />
@endsection

@section('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      phpvms.map.render_airspace_map({
        lat: "{{$config['lat']}}",
        lon: "{{$config['lon']}}",
        metar_wms: {!! json_encode(config('map.metar_wms')) !!},
      });
    });
  </script>
@endsection
