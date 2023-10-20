<table class="table table-hover table-responsive" id="fares-table">
  <thead>
    <th>Code</th>
    <th>Name</th>
    <th>Type</th>
    <th>Price</th>
    <th>Cost</th>
    <th>Notes</th>
    <th class="text-center">Active</th>
    <th class="text-right">Deleted</th>
    <th class="text-right">Action</th>
  </thead>
  <tbody>
    @foreach($trashed as $fare)
      <tr>
        <td>{{ $fare->code }}</td>
        <td>{{ $fare->name }}</td>
        <td>{{ \App\Models\Enums\FareType::label($fare->type) }}</td>
        <td>{{ $fare->price }}</td>
        <td>{{ $fare->cost }}</td>
        <td>{{ $fare->notes }}</td>
        <td class="text-center">
          @if($fare->active == 1)
            <span class="label label-success">Active</span>
          @else
            <span class="label label-default">Inactive</span>
          @endif
        </td>
        <td class="text-right">{{ $fare->deleted_at->diffForHumans() }}</td>
        <td class="text-right">
          {{ Form::open(['route' => ['admin.fares.trashbin'], 'method' => 'post']) }}
          {{ Form::hidden('object_id', $fare->id) }}
          {{ Form::button('<i class="fa fa-plus"></i> RESTORE', ['type' => 'submit', 'name' => 'action', 'value' => 'restore', 'class' => 'btn btn-sm btn-success btn-icon']) }}
          {{ Form::button('<i class="fa fa-times"></i> DELETE', ['type' => 'submit', 'name' => 'action', 'value' => 'delete', 'class' => 'btn btn-sm btn-danger btn-icon', 'onclick' => "return confirm('Are you REALLY sure?')"]) }}
          {{ Form::close() }}
        </td>
      </tr>
    @endforeach
  </tbody>
</table>
