<div id="airframes_table_wrapper">
  <table class="table table-hover table-responsive">
    <thead>
      <th>ICAO</th>
      <th>Name</th>
      <th>SB Airframe ID</th>
      <th>Created At</th>
      <th>Updated At</th>
      <th></th>
    </thead>
    <tbody>
      @foreach($airframes as $af)
        <tr>
          <td>{{ $af->icao }}</a></td>
          <td>{{ $af->name }}</td>
          <td>{{ $af->airframe_id }}</td>
          <td>{{ $af->created_at->format('d.M.y H:i') }}</td>
          <td>{{ $af->updated_at->format('d.M.y H:i') }}</td>
          <td class="text-right">
            {{ Form::open(['route' => ['admin.airframes.destroy', $af->id], 'method' => 'delete']) }}
            <a href="{{ route('admin.airframes.edit', [$af->id]) }}" class='btn btn-sm btn-success btn-icon'>
              <i class="fas fa-pencil-alt"></i></a>
            {{ Form::button('<i class="fa fa-times"></i>', ['type' => 'submit', 'class' => 'btn btn-sm btn-danger btn-icon', 'onclick' => "return confirm('Are you sure?')"]) }}
            {{ Form::close() }}
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
