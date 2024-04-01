<div class="content table-responsive table-full-width">
  <table class="table table-hover" id="activities-table">
    <thead>
    <th>Action</th>
    <th>Causer</th>
    <th>Date</th>
    <th class="text-right">Actions</th>
    </thead>
    <tbody>
    @foreach($activities as $activity)
      <tr>
        <td>{{ class_basename($activity->subject_type).' '. $activity->event}}</td>
        <td>
          @if (class_basename($activity->causer_type) === 'User')
            <a href="{{ route('admin.users.edit', [$activity->causer_id]) }}">
              {{ $activity->causer_id .' | '. $activity->causer->name_private }}
            </a>
          @else
            {{ $activity->causer_id.' | '. class_basename($activity->causer_type) }}
          @endif
        </td>
        <td>{{ $activity->created_at->diffForHumans().' | '.$activity->created_at->format('d.M') }}</td>
        <td class="text-right">
          <a href="{{ route('admin.activities.show', [$activity->id]) }}" class='btn btn-sm btn-success btn-icon'><i class="fas fa-eye"></i> View Details</a>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
