<div class="row">
  {{ Form::hidden('id') }}
  <div class="form-group col-sm-2">
    {{ Form::label('pilot_id', 'Pilot ID:') }}
    {{ Form::number('pilot_id', null, ['class' => 'form-control']) }}
    <p class="text-danger">{{ $errors->first('pilot_id') }}</p>
  </div>
  <div class="form-group col-sm-2">
    {{ Form::label('callsign', 'Callsign:') }}
    {{ Form::text('callsign', null, ['class' => 'form-control', 'autocomplete' => 'off', 'maxlength' => 4]) }}
    <p class="text-danger">{{ $errors->first('callsign') }}</p>
  </div>
  <div class="form-group col-sm-3">
    {{ Form::label('name', 'Name:') }}
    {{ Form::text('name', null, ['class' => 'form-control', 'autocomplete' => 'off']) }}
    <p class="text-danger">{{ $errors->first('name') }}</p>
  </div>
  <div class="form-group col-sm-3">
    {{ Form::label('email', 'Email:') }}
    {{ Form::text('email', null, ['class' => 'form-control', 'autocomplete' => 'off']) }}
    <p class="text-danger">{{ $errors->first('email') }}</p>
  </div>
  <div class="form-group col-sm-2">
    {{ Form::label('password', 'Password:') }}
    {{ Form::password('password', ['class' => 'form-control', 'autocomplete' => 'off']) }}
    <p class="text-danger">{{ $errors->first('password') }}</p>
  </div>
</div>

<div class="row">
  <div class="form-group col-sm-6">
    <div class="form-group">
      {{ Form::label('country', 'Country:') }} <br/>
      {{ Form::select('country', $countries, null, ['class' => 'select2' ]) }}
      <p class="text-danger">{{ $errors->first('country') }}</p>
    </div>
    <div class="form-group">
      {{ Form::label('timezone', 'Timezone:') }} <br/>
      {{ Form::select('timezone', $timezones, null, ['id' => 'timezone', 'class' => 'select2' ]) }}
      <p class="text-danger">{{ $errors->first('timezone') }}</p>
    </div>
    <div class="form-group col-sm-4">
      {{ Form::label('transfer_time', 'Transferred Hours:') }}
      {{ Form::text('transfer_time', \App\Support\Units\Time::minutesToHours($user->transfer_time), ['class' => 'form-control']) }}
      <p class="text-danger">{{ $errors->first('transfer_time') }}</p>
    </div>
  </div>
  <div class="form-group col-sm-6">
    {{ Form::label('home_airport_id', 'Home Airport:') }}
    {{ Form::select('home_airport_id', $airports, null , ['class' => 'form-control airport_search']) }}
    <p class="text-danger">{{ $errors->first('home_airport_id') }}</p>
    <br/><br/>
    {{ Form::label('curr_airport_id', 'Current Airport:') }}
    {{ Form::select('curr_airport_id', $airports, null , ['class' => 'form-control airport_search']) }}
    <p class="text-danger">{{ $errors->first('curr_airport_id') }}</p>
  </div>
</div>

<div class="row">
  <div class="form-group col-sm-4">
    {{ Form::label('airline_id', 'Airline:') }}
    {{ Form::select('airline_id', $airlines, null, ['class' => 'form-control select2', 'placeholder' => 'Select Airline']) }}
  </div>
  <div class="form-group col-sm-4">
    {{ Form::label('rank_id', 'Rank:') }}
    {{ Form::select('rank_id', $ranks, null, ['class' => 'form-control select2', 'placeholder' => 'Select Rank']) }}
  </div>
  <div class="form-group col-sm-4">
    @ability('admin', 'admin-user')
      {{ Form::label('roles', 'Roles:') }}
      {{ Form::select('roles[]', $roles, $user->roles->pluck('id'), ['class' => 'form-control select2', 'placeholder' => 'Select Roles', 'multiple']) }}
    @endability
  </div>
</div>

<div class="row">
  <div class="form-group col-md-4">
    {{ Form::label('state', 'State:') }}
    {{-- <label class="checkbox-inline"> --}}
      {{ Form::select('state', UserState::labels(), null, ['class' => 'form-control select2', 'style' => 'width: 100%;']) }}
    {{-- </label> --}}
  </div>
  <div class="form-group col-md-8">
    {{ Form::label('notes', 'Management Notes:') }}
    {{ Form::textarea('notes', null, ['class' => 'form-control', 'rows' => 4, 'autocomplete' => 'off']) }}
  </div>
</div>

<div class="row">
  <div class="form-group col-sm-12 text-right">
    {{-- <a href="{{ route('admin.users.regen_apikey', [$user->id]) }}" class="btn btn-warning" onclick="return confirm('Are you sure? This will reset this user\'s API key.')">New API Key</a> --}}
    &nbsp;
    {{ Form::button('Save', ['type' => 'submit', 'class' => 'btn btn-success']) }}
    <a href="{{ route('admin.users.index') }}" class="btn btn-default">Cancel</a>
  </div>
</div>

<div class="row">
  <div class="form-group col-sm-12">
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
      @if($user->fields)
        <tr>
          <td colspan="2"><h5>Custom Fields</h5></td>
        </tr>
        {{-- Custom Fields --}}
        @foreach($user->fields as $field)
          <tr>
            <td>{{ $field->field->name }}</td>
            <td>{{ $field->value }}</td>
          </tr>
        @endforeach
      @endif
    </table>
  </div>
</div>
