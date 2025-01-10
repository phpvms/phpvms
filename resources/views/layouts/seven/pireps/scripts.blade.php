@section('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const select_id = "select#aircraft_select";
      const destContainer = document.getElementById('fares_container');

      document.querySelector(select_id).addEventListener('change', function(e) {
        const aircraft_id = document.querySelector(select_id + ' option:checked').value;
        const url = '/pireps/fares?aircraft_id=' + aircraft_id;
        console.log('aircraft select change: ', aircraft_id);

        phpvms.request(url).then(response => {
          if (response.data === '') {console.log('no fare data'); return;}
          destContainer.innerHTML = response.data;
        });
      });
    });
  </script>

@include('scripts.airport_search')

@endsection
