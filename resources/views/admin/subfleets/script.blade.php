@section('scripts')
  <script>
    function setEditable() {

      const token = $('meta[name="csrf-token"]').attr('content');
      const api_key = $('meta[name="api-key"]').attr('content');

      @if(isset($subfleet))
      $('#aircraft_fares a').editable({
        type: 'text',
        mode: 'inline',
        emptytext: 'inherited',
        url: '{{ url('/admin/subfleets/'.$subfleet->id.'/fares') }}',
        title: 'Enter override value',
        ajaxOptions: {
          type: 'post',
          headers: {
            'x-api-key': api_key,
            'X-CSRF-TOKEN': token,
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

      $('#subfleet_ranks a').editable({
        type: 'text',
        mode: 'inline',
        emptytext: 'inherited',
        url: '{{ url('/admin/subfleets/'.$subfleet->id.'/ranks') }}',
        title: 'Enter override value',
        ajaxOptions: {
          type: 'post',
          headers: {
            'x-api-key': api_key,
            'X-CSRF-TOKEN': token,
          }
        },
        params: function (params) {
          return {
            _method: 'put',
            rank_id: params.pk,
            name: params.name,
            value: params.value
          }
        }
      });

      $('#subfleet-expenses a.text').editable({
        emptytext: '0',
        url: '{{ url('/admin/subfleets/'.$subfleet->id.'/expenses') }}',
        title: 'Enter override value',
        ajaxOptions: {
          type: 'post',
          headers: {
            'x-api-key': api_key,
            'X-CSRF-TOKEN': token,
          }
        },
        params: function (params) {
          return {
            _method: 'put',
            expense_id: params.pk,
            name: params.name,
            value: params.value
          }
        }
      });

      $('#subfleet-expenses a.dropdown').editable({
        type: 'select',
        emptytext: '0',
        source: {!! json_encode(list_to_editable(\App\Models\Enums\ExpenseType::select())) !!},
        url: '{{ url('/admin/subfleets/'.$subfleet->id.'/expenses') }}',
        title: 'Enter override value',
        ajaxOptions: {
          type: 'post',
          headers: {
            'x-api-key': api_key,
            'X-CSRF-TOKEN': token,
          }
        },
        params: function (params) {
          console.log(params);
          return {
            _method: 'put',
            expense_id: params.pk,
            name: params.name,
            value: params.value
          }
        }
      });
      @endif
    }

    $(document).ready(function () {

      setEditable();

      $(document).on('submit', 'form.rm_fare', function (event) {
        event.preventDefault();
        console.log(event);
        $.pjax.submit(event, '#aircraft_fares_wrapper', {push: false});
        setEditable();
      });

      $(document).on('submit', 'form.modify_rank', function (event) {
        event.preventDefault();
        console.log(event);
        $.pjax.submit(event, '#subfleet_ranks_wrapper', {push: false});
      });

      $(document).on('submit', 'form.modify_typerating', function (event) {
        event.preventDefault();
        console.log(event);
        $.pjax.submit(event, '#subfleet_typeratings_wrapper', {push: false});
      });

      $(document).on('submit', 'form.modify_expense', function (event) {
        event.preventDefault();
        console.log(event);
        $.pjax.submit(event, '#subfleet-expenses-wrapper', {push: false});
      });

      $(document).on('pjax:complete', function () {
        initPlugins();
        setEditable();
      });
    });
  </script>

  @include('admin.scripts.airport_search')
@endsection
