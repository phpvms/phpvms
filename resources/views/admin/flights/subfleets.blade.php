<div id="subfleet_flight_wrapper">
  <div class="header">
    <h3>subfleets</h3>
    @component('admin.components.info')
      The subfleets that are assigned to this flight.
    @endcomponent
  </div>

  @if(count($flight->subfleets) === 0)
    @include('admin.common.none_added', ['type' => 'subfleets'])
  @endif

  <table class="table table-responsive" id="aircrafts-table">
    @if(count($flight->subfleets))
      <thead>
        <th>Airline</th>
        <th>Type</th>
        <th>Name</th>
        <th style="text-align: center;">Actions</th>
      </thead>
    @endif
    <tbody>
      @foreach($flight->subfleets as $sf)
        <tr>
          <td>@if($sf->airline->logo)
                <img src="{{ $sf->airline->logo }}" style="max-width: 60px; width: 55%; height: auto;">
              @else
                &nbsp;{{ $sf->airline->icao }}
              @endif
          </td>
          <td>{{ $sf->type }}</td>
          <td>{{ $sf->name }}</td>
          <td style="width: 10%; text-align: center;" class="form-inline">
            {{ Form::open(['url' => '/admin/flights/'.$flight->id.'/subfleets', 'method' => 'delete', 'class' => 'pjax_subfleet_form']) }}
            {{ Form::hidden('subfleet_id', $sf->id) }}
            <div class='btn-group'>
              {{ Form::button('<i class="fa fa-times"></i>', ['type' => 'submit', 'class' => 'btn btn-danger btn-xs']) }}
            </div>
            {{ Form::close() }}
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <hr/>
  <div class="row">
    <div class="col-xs-12">
      <div class="text-right">
        {{ Form::open(['url' => '/admin/flights/'.$flight->id.'/subfleets', 'method' => 'post', 'class' => 'pjax_form form-inline pjax_subfleet_form']) }}
        {{ Form::select('subfleet_ids[]', $avail_subfleets, null, ['class' => 'select2 form-control input-lg', 'style' => 'width: 450px;', 'multiple' => 'multiple']) }}
        &nbsp;
        {{ Form::button('<i class="fas fa-plus"></i> add', ['type' => 'submit', 'class' => 'btn btn-success btn-s']) }}
        {{ Form::close() }}
      </div>
    </div>
  </div>
</div>
