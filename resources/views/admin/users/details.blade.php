<table class="table table-hover">
  <tr>
    <td colspan="2"><h5>User Details</h5></td>
  </tr>
  <tr>
    <td>Total Flights</td>
    <td>{{ $user->flights }}</td>
  </tr>
  <tr>
    <td>Flight Time</td>
    <td>@minutestotime($user->flight_time)</td>
  </tr>
  <tr>
    <td>Registered On</td>
    <td>{{ show_datetime($user->created_at) }}</td>
  </tr>
  <tr>
    <td>E-Mail Verified On</td>
    <td>
      @if(filled($user->email_verified_at))
        {{ show_datetime($user->email_verified_at) }}
      @else
        <span class="btn btn-sm btn-danger mx-1 my-0 p-1">USER E-MAIL NOT VERIFIED !!!</span>
      @endif
    </td>
  </tr>
  <tr>
    <td>Last Login</td>
    <td>
      @if(filled($user->lastlogin_at))
        {{ show_datetime($user->lastlogin_at) }}
      @endif
    </td>
  </tr>
  <tr>
    <td>IP Address</td>
    <td>{{ $user->last_ip ?? '-' }}</td>
  </tr>
  <tr>
    <td>@lang('toc.title')</td>
    <td>{{ $user->toc_accepted ? __('common.yes') : __('common.no') }}</td>
  </tr>
  <tr>
    <td>@lang('profile.opt-in')</td>
    <td>{{ $user->opt_in ? __('common.yes') : __('common.no') }}</td>
  </tr>
</table>
