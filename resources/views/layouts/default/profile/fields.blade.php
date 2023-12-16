<div class="row">
  <div class="col-md-12">
    <table class="table">
      <tr>
        <td>{{ __('common.name') }}</td>
        <td>
          <div class="input-group form-group-no-border{{ $errors->has('name') ? ' has-danger' : '' }}">
            {{ Form::text('name', null, ['class' => 'form-control']) }}
          </div>
          @if ($errors->has('name'))
            <p class="text-danger">{{ $errors->first('name') }}</p>
          @endif
        </td>
      </tr>

      <tr>
        <td>{{ __('common.email') }}</td>
        <td>
          <div class="input-group form-group-no-border{{ $errors->has('email') ? ' has-danger' : '' }}">
            {{ Form::text('email', null, ['class' => 'form-control']) }}
          </div>
          @if ($errors->has('email'))
            <p class="text-danger">{{ $errors->first('email') }}</p>
          @endif
        </td>
      </tr>

      <tr>
        <td>{{ __('common.airline') }}</td>
        <td>
          <div class="input-group form-group-no-border{{ $errors->has('airline') ? ' has-danger' : '' }}">
            {{ Form::select('airline_id', $airlines, null , ['class' => 'form-control select2']) }}
          </div>
          @if ($errors->has('airline_id'))
            <p class="text-danger">{{ $errors->first('airline_id') }}</p>
          @endif
        </td>
      </tr>

      <tr>
        <td>{{ __('airports.home') }}</td>
        <td>
          <div class="input-group form-group-no-border{{ $errors->has('home_airport_id') ? ' has-danger' : '' }}">
            {{ Form::select('home_airport_id', $airports, null , ['class' => 'form-control airport_search '.($hubs_only?'hubs_only':'')]) }}
          </div>
          @if ($errors->has('home_airport_id'))
            <p class="text-danger">{{ $errors->first('home_airport_id') }}</p>
          @endif
        </td>
      </tr>

      <tr>
        <td>{{ __('common.country') }}</td>
        <td>
          <div class="input-group form-group-no-border{{ $errors->has('country') ? ' has-danger' : '' }}">
            {{ Form::select('country', $countries, null, ['class' => 'form-control select2' ]) }}
          </div>
          @if ($errors->has('country'))
            <p class="text-danger">{{ $errors->first('country') }}</p>
          @endif
        </td>
      </tr>

      <tr>
        <td>{{ __('common.timezone') }}</td>
        <td>
          <div class="input-group form-group-no-border{{ $errors->has('timezone') ? ' has-danger' : '' }}">
            {{ Form::select('timezone', $timezones, null, ['class' => 'form-control select2' ]) }}
          </div>
          @if ($errors->has('timezone'))
            <p class="text-danger">{{ $errors->first('timezone') }}</p>
          @endif
        </td>
      </tr>

      <tr>
        <td>{{ __('profile.changepassword') }}</td>
        <td>
          <p>{{ __('profile.newpassword') }}:</p>
          <div class="input-group form-group-no-border{{ $errors->has('password') ? ' has-danger' : '' }}">
            {{ Form::password('password', ['class' => 'form-control']) }}
          </div>
          @if ($errors->has('password'))
            <p class="text-danger">{{ $errors->first('password') }}</p>
          @endif

          <p>{{ __('passwords.confirm') }}:</p>
          <div class="input-group form-group-no-border{{ $errors->has('password_confirmation') ? ' has-danger' : '' }}">
            {{ Form::password('password_confirmation', ['class' => 'form-control']) }}
          </div>
          @if ($errors->has('password_confirmation'))
            <p class="text-danger">{{ $errors->first('password_confirmation') }}</p>
          @endif
        </td>
      </tr>
      <tr>
        <td>{{ __('profile.avatar') }}</td>
        <td>
          <div class="input-group form-group-no-border{{ $errors->has('avatar') ? ' has-danger' : '' }}">
            {{ Form::file('avatar', null) }}
          </div>
          <p class="small">{{ __('profile.avatarresize', [
                        'width' => config('phpvms.avatar.width'),
                        'height' => config('phpvms.avatar.height')]) }}
          </p>
          @if ($errors->has('avatar'))
            <p class="text-danger">{{ $errors->first('avatar') }}</p>
          @endif
        </td>
      </tr>

      {{-- Custom fields --}}
      @foreach($userFields as $field)
        <tr>
          <td>
            {{ $field->name }}
            @if($field->required === true)
              <span class="text-danger">*</span>
            @endif
          </td>
          <td>
            <div class="input-group input-group-sm form-group">
              {{ Form::text('field_'.$field->slug, $field->value, [
                  'class' => 'form-control',
                  ]) }}
            </div>
            <p class="text-danger">{{ $errors->first('field_'.$field->slug) }}</p>
          </td>
        </tr>
      @endforeach

      <tr>
        <td>{{ __('profile.opt-in') }}</td>
        <td>
          <div class="input-group form-group-no-border">
            {{ Form::hidden('opt_in', 0, false) }}
            {{ Form::checkbox('opt_in', 1, null) }}
          </div>
          <p class="small">{{ __('profile.opt-in-descrip') }}
          </p>
        </td>
      </tr>

    </table>

    <div style="width: 100%; text-align: right; padding-top: 20px;">
      {{ Form::submit(__('profile.updateprofile'), ['class' => 'btn btn-primary']) }}
    </div>
  </div>
</div>
