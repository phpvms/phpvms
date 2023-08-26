<table class="table table-hover" id="users-table">
  <thead>
  <th></th>
  <th>@sortablelink('name', __('common.name'))</th>
  <th style="text-align: center"></th>
  <th style="text-align: center">@sortablelink('airline_id', __('common.airline'))</th>
  <th style="text-align: center">@sortablelink('curr_airport_id', __('user.location'))</th>
  <th style="text-align: center">@sortablelink('flights', trans_choice('common.flight', 2))</th>
  <th style="text-align: center">@sortablelink('flight_time', trans_choice('common.hour', 2))</th>
  </thead>
  <tbody>
  @foreach($users as $user)
    <tr>
      <td style="width: 80px;">
        <div class="photo-container">
          @if ($user->avatar == null)
            <img class="rounded-circle"
                 src="{{ $user->gravatar(256) }}&s=256"/>
          @else
            <img src="{{ $user->avatar->url }}">
          @endif
        </div>
      </td>
      <td>
        <a href="{{ route('frontend.users.show.public', [$user->id]) }}">
          {{$user->ident}}&nbsp;{{ $user->name_private }}
        </a>
      </td>
      <td align="center">
        @if(filled($user->country))
          <span class="flag-icon flag-icon-{{ $user->country }}"
                title="{{ $country->alpha2($user->country)['name'] }}"></span>
        @endif
      </td>
      <td class="text-center">{{ $user->airline->icao }}</td>
      <td class="text-center">
        @if($user->current_airport)
          {{ $user->curr_airport_id }}
        @else
          -
        @endif
      </td>
      <td align="center">{{ $user->flights }}</td>
      <td align="center">@minutestotime($user->flight_time)</td>
    </tr>
  @endforeach
  </tbody>
</table>
