@section('scripts')
  <script>
    function setEditable() {

      const token = $('meta[name="csrf-token"]').attr('content');
      const api_key = $('meta[name="api-key"]').attr('content');
    }

    $(document).ready(function () {

      setEditable();

      $(document).on('submit', 'form.modify_typerating', function (event) {
        event.preventDefault();
        console.log(event);
        $.pjax.submit(event, '#user_typeratings_wrapper', {push: false});
      });

      $(document).on('pjax:complete', function () {
        initPlugins();
        setEditable();
      });
    });
  </script>

  @include('admin.scripts.airport_search')
@endsection
