@section('scripts')
  <script>
    const select_id = "select#aircraft_select";
    const destContainer = $('#fares_container');

    $(select_id).change(e => {
      const aircraft_id = $(select_id + ' option:selected').val();
      const url = '/pireps/fares?aircraft_id=' + aircraft_id;
      console.log('aircraft select change: ', aircraft_id);

      phpvms.request(url).then(response => {
        destContainer.html(response.data);
      });
    });
  </script>

@include('scripts.airport_search')

@endsection
