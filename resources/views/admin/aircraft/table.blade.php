<table class="table table-hover table-responsive" id="aircrafts-table">
  <thead>
    <th>Registration</th>
    <th>Name</th>
    <th>FIN</th>
    <th style="text-align: center;">Subfleet</th>
    <th style="text-align: center;">Home</th>
    <th style="text-align: center;">Location</th>
    <th style="text-align: center;">Last Landing</th>
    <th style="text-align: center;">Hours</th>
    <th style="text-align: center;">Status</th>
    <th style="text-align: center;">State</th>
    <th style="text-align: right;">Actions</th>
  </thead>
  <tbody>
  @foreach($aircraft as $ac)
    <tr>
      <td><a href="{{ route('admin.aircraft.edit', [$ac->id]) }}">{{ $ac->registration }}</a></td>
      <td>{{ $ac->name }}</td>
      <td>{{ $ac->fin }}</td>
      <td style="text-align: center;">@if($ac->subfleet_id && $ac->subfleet)<a href="{{ route('admin.subfleets.edit', [$ac->subfleet_id]) }}">{{ $ac->subfleet->name }}</a>@endif</td>
      <td style="text-align: center;">{{ $ac->hub_id }}</td>
      <td style="text-align: center;">{{ $ac->airport_id }}</td>
      <td style="text-align: center;">@if(filled($ac->landing_time)){{ $ac->landing_time->diffForHumans() }}@endif</td>
      <td style="text-align: center;">@minutestotime($ac->flight_time)</td>
      <td style="text-align: center;">
        @if($ac->status == \App\Models\Enums\AircraftStatus::ACTIVE)
          <span class="label label-success">{{ \App\Models\Enums\AircraftStatus::label($ac->status) }}</span>
        @else
          <span class="label label-default">{{ \App\Models\Enums\AircraftStatus::label($ac->status) }}</span>
        @endif
      </td>
      <td style="text-align: center;">
        @if($ac->state == \App\Models\Enums\AircraftState::PARKED)
          <span class="label label-success">{{ \App\Models\Enums\AircraftState::label($ac->state) }}</span>
        @else
          <span class="label label-default">{{ \App\Models\Enums\AircraftState::label($ac->state) }}</span>
        @endif
      </td>
      <td style="width: 10%; text-align: right;">
        {{ Form::open(['route' => ['admin.aircraft.destroy', $ac->id], 'method' => 'delete']) }}
        <a href="{{ route('admin.aircraft.edit', [$ac->id]) }}" class='btn btn-sm btn-success btn-icon'>
          <i class="fas fa-pencil-alt"></i>
        </a>
        {{ Form::button('<i class="fa fa-times"></i>', ['type' => 'submit', 'class' => 'btn btn-sm btn-danger btn-icon', 'onclick' => "return confirm('Are you sure?')"]) }}
        {{ Form::close() }}
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
