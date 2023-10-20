<table class="table table-hover table-responsive" id="aircrafts-table">
  <thead>
    <th>Registration</th>
    <th>Name</th>
    <th>FIN</th>
    <th class="text-center">Subfleet</th>
    <th class="text-center">Home</th>
    <th class="text-center">Location</th>
    <th class="text-center">Last Landing</th>
    <th class="text-center">Hours</th>
    <th class="text-center">Status</th>
    <th class="text-center">State</th>
    <th class="text-right">Deleted</th>
    <th class="text-right">Actions</th>
  </thead>
  <tbody>
  @foreach($trashed as $ac)
    <tr>
      <td>{{ $ac->registration }}</td>
      <td>{{ $ac->name }}</td>
      <td>{{ $ac->fin }}</td>
      <td class="text-center">@if($ac->subfleet_id && $ac->subfleet){{ $ac->subfleet->name }}@endif</td>
      <td class="text-center">{{ $ac->hub_id }}</td>
      <td class="text-center">{{ $ac->airport_id }}</td>
      <td class="text-center">@if(filled($ac->landing_time)){{ $ac->landing_time->diffForHumans() }}@endif</td>
      <td class="text-center">@minutestotime($ac->flight_time)</td>
      <td class="text-center">
        @if($ac->status == \App\Models\Enums\AircraftStatus::ACTIVE)
          <span class="label label-success">{{ \App\Models\Enums\AircraftStatus::label($ac->status) }}</span>
        @else
          <span class="label label-default">{{ \App\Models\Enums\AircraftStatus::label($ac->status) }}</span>
        @endif
      </td>
      <td class="text-center">
        @if($ac->state == \App\Models\Enums\AircraftState::PARKED)
          <span class="label label-success">{{ \App\Models\Enums\AircraftState::label($ac->state) }}</span>
        @else
          <span class="label label-default">{{ \App\Models\Enums\AircraftState::label($ac->state) }}</span>
        @endif
      </td>
      <td class="text-right">{{ $ac->deleted_at->diffForHumans() }}</td>
      <td class="text-right">
        {{ Form::open(['route' => ['admin.aircraft.trashbin'], 'method' => 'post']) }}
        {{ Form::hidden('object_id', $ac->id) }}
        {{ Form::button('<i class="fa fa-plus"></i> RESTORE', ['type' => 'submit', 'name' => 'action', 'value' => 'restore', 'class' => 'btn btn-sm btn-success btn-icon']) }}
        {{ Form::button('<i class="fa fa-times"></i> DELETE', ['type' => 'submit', 'name' => 'action', 'value' => 'delete', 'class' => 'btn btn-sm btn-danger btn-icon', 'onclick' => "return confirm('Are you REALLY sure?')"]) }}
        {{ Form::close() }}
      </td>
    </tr>
  @endforeach
  </tbody>
</table>
