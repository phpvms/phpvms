@section('scripts')
  <script>

    const setEditable = () => {
      @if($flight)
      const api_key = $('meta[name="api-key"]').attr('content');
      const csrf_token = $('meta[name="csrf-token"]').attr('content');

      $('#flight_fares a').editable({
        type: 'text',
        mode: 'inline',
        emptytext: 'inherited',
        url: '{{ url('/admin/flights/'.$flight->id.'/fares') }}',
        title: 'Enter override value',
        ajaxOptions: {
          type: 'post',
          headers: {
            'x-api-key': api_key,
            'X-CSRF-TOKEN': csrf_token
          }
        },
        params: function (params) {
          return {
            _method: 'put',
            fare_id: params.pk,
            name: params.name,
            value: params.value
          }
        }
      });
      @endif
    };

    const setFieldsEditable = () => {
      @if($flight)
      const api_key = $('meta[name="api-key"]').attr('content');
      const csrf_token = $('meta[name="csrf-token"]').attr('content');

      $('#flight_fields_wrapper a.inline').editable({
        type: 'text',
        mode: 'inline',
        emptytext: 'no value',
        url: '{{ url('/admin/flights/'.$flight->id.'/fields') }}',
        ajaxOptions: {
          type: 'post',
          headers: {
            'x-api-key': api_key,
            'X-CSRF-TOKEN': csrf_token
          }
        },
        params: function (params) {
          return {
            _method: 'put',
            field_id: params.pk,
            name: params.name,
            value: params.value
          }
        }
      });
      @endif
    };

    $(document).ready(function () {
      $("select#days_of_week").select2();

      setEditable();
      setFieldsEditable();

      const start_date_picker = new Pikaday({
        field: document.getElementById('start_date'),
        minDate: new Date(),
      });

      const end_date_picker = new Pikaday({
        field: document.getElementById('end_date'),
        minDate: new Date(),
      });

      $(document).on('submit', 'form.pjax_flight_fields', function (event) {
        event.preventDefault();
        $.pjax.submit(event, '#flight_fields_wrapper', {push: false});
      });

      $(document).on('submit', 'form.pjax_subfleet_form', function (event) {
        event.preventDefault();
        $.pjax.submit(event, '#subfleet_flight_wrapper', {push: false});
      });

      $(document).on('submit', 'form.pjax_fares_form', function (event) {
        event.preventDefault();
        $.pjax.submit(event, '#flight_fares_wrapper', {push: false});
      });

      $(document).on('pjax:complete', function () {
        initPlugins();
        setEditable();
        setFieldsEditable();
      });

      $('a.airport_distance_lookup').click(async function (e) {
        e.preventDefault();
        const fromIcao = $("select#dpt_airport_id option:selected").val();
        const toIcao = $("select#arr_airport_id option:selected").val();
        console.log('fromIcao="' + fromIcao + '", toIcao="' + toIcao + '"');

        if (fromIcao === '' || toIcao === '') {
          return;
        }

        console.log(`Calculating from ${fromIcao} to ${toIcao}`);
        let response;

        try {
          response = await phpvms.calculate_distance(fromIcao, toIcao);
        } catch (e) {
          console.log('Error calculating distance:', e);
          return;
        }

        $("#distance").val(response.data.distance.nmi);
      });
    });
  </script>

  @include('admin.scripts.airport_search')
@endsection
