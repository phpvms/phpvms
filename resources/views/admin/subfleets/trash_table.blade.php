<div class="content table-responsive table-full-width">
  <table class="table table-hover table-responsive" id="subfleets-table">
    <thead>
      <th>Name</th>
      <th>Airline</th>
      <th>Type</th>
      <th>Hub</th>
      <th>Aircraft</th>
      <th class="text-right">Deleted</th>
      <th class="text-right">Actions</th>
    </thead>
    <tbody>
      @foreach($trashed as $subfleet)
        <tr>
          <td>{{ $subfleet->name }}</td>
          <td>{{ optional($subfleet->airline)->name }}</td>
          <td>{{ $subfleet->type }}</td>
          <td>{{ $subfleet->hub_id }}</td>
          <td>{{ $subfleet->aircraft->count() }}</td>
          <td class="text-right">{{ $subfleet->deleted_at->diffForHumans() }}</td>
          <td class="text-right">
            {{ Form::open(['route' => ['admin.subfleets.trashbin'], 'method' => 'post']) }}
            {{ Form::hidden('object_id', $subfleet->id) }}
            {{ Form::button('<i class="fa fa-plus"></i> RESTORE', ['type' => 'submit', 'name' => 'action', 'value' => 'restore', 'class' => 'btn btn-sm btn-success btn-icon']) }}
            {{ Form::button('<i class="fa fa-times"></i> DELETE', ['type' => 'submit', 'name' => 'action', 'value' => 'delete', 'class' => 'btn btn-sm btn-danger btn-icon', 'onclick' => "return confirm('Are you REALLY sure?')"]) }}
            {{ Form::close() }}
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
