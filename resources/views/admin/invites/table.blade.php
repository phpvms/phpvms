<div class="content table-responsive table-full-width">
  <table class="table table-hover table-responsive" id="pirepFields-table">
    <thead>
    <th>Invite Type</th>
    <th>Invited Email/Invite Link</th>
    <th>Usage Count</th>
    <th>Usage Limit</th>
    <th>Expires In</th>
    <th></th>
    </thead>
    <tbody>
    @foreach($invites as $invite)
      <tr>
        <td>{{ is_null($invite->email) ? 'Link' : 'Email' }}</td>
        <td>
          @if(is_null($invite->email))
            <a href="{{ $invite->link }}">{{ $invite->link }}</a>
          @else
            {{ $invite->email }}
          @endif
        </td>
        <td>{{ $invite->usage_count }}</td>
        <td>{{ $invite->usage_limit ?? 'No limit' }}</td>
        <td>{{ $invite->expires_at?->diffForHumans() ?? 'Never' }}
        <td class="text-right">
          {{ Form::open(['route' => ['admin.invites.destroy', $invite->id], 'method' => 'delete']) }}
          {{ Form::button('<i class="fa fa-times"></i>',
                       ['type' => 'submit', 'class' => 'btn btn-sm btn-danger btn-icon',
                        'onclick' => "return confirm('Are you sure?')"]) }}
          {{ Form::close() }}
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
