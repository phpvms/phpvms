@section('scripts')
  <script>
    const changeStatus = async (values, fn) => {
      console.log('Changing PIREP ' + values.pirep_id + ' to state ' + values.new_status);

      const opts = {
        method: 'POST',
        url: '{{url('/admin/pireps')}}/' + values.pirep_id + '/status',
        data: values,
      };

      const response = await phpvms.request(opts);
      fn(response.data);
    };

    $(document).ready(() => {
      const select_id = "select#aircraft_select";
      const destContainer = $('#fares_container');

      $(select_id).change(async (e) => {
        const aircraft_id = $(select_id + " option:selected").val();
        console.log('aircraft select change: ', aircraft_id);

        const response = await phpvms.request("{{ url('/admin/pireps/fares') }}?aircraft_id=" + aircraft_id);
        console.log('returned new fares', response.data);
        destContainer.html(response.data);
      });

      $(document).on('submit', 'form.pjax_form', (event) => {
        event.preventDefault();
        $.pjax.submit(event, '#pirep_comments_wrapper', {push: false});
      });

      $(document).on('pjax:complete', function () {
        initPlugins();
      });

      /**
       * Recalculate finances button is clicked
       */
      $('button#recalculate-finances').on('click', async (event) => {
        event.preventDefault();
        console.log('Sending recalculate finances request');
        const pirep_id = $(event.currentTarget).attr('data-pirep-id');

        const opts = {
          method: 'POST',
          url: '{{url('/api/pireps')}}/' + pirep_id + '/finances/recalculate',
        };

        const response = await phpvms.request(opts);
        console.log(response.data);
        location.reload();
      });

      $(document).on('submit', 'form.pirep_submit_status', (event) => {
        event.preventDefault();
        const values = {
          pirep_id: $(event.currentTarget).attr('pirep_id'),
          new_status: $(event.currentTarget).attr('new_status')
        };

        console.log('change status', values);

        changeStatus(values, (data) => {
          const destContainer = '#pirep_' + values.pirep_id + '_actionbar';
          $(destContainer).html(data);

          const statusContainer = '#pirep_' + values.pirep_id + '_status_container';
          let new_badge;
          let new_badge_text;

          if (values.new_status === '{{ App\Models\Enums\PirepState::ACCEPTED }}') {
            new_badge = 'badge badge-success';
            new_badge_text = 'Accepted'
          }

          if (values.new_status === '{{ App\Models\Enums\PirepState::REJECTED }}') {
            new_badge = 'badge badge-danger';
            new_badge_text = 'Rejected'
          }

          $(statusContainer).children(0).removeClass().addClass(new_badge);
          $(statusContainer).children(0).html(new_badge_text);
        });
      });

      $(document).on('submit', 'form.pirep_change_status', (event) => {
        event.preventDefault();

        const values = {
          pirep_id: $(event.currentTarget).attr('pirep_id'),
          new_status: $(event.currentTarget).attr('new_status')
        };

        console.log('change status', values);

        changeStatus(values, (data) => {
          location.reload();
        });
      });

    });
  </script>
  @include('admin.scripts.airport_search')
@endsection
