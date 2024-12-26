<div class="row">
  <div class="col-md-12">
    <table class="table">
      <tr>
        <td>{{ __('common.name') }}</td>
        <td>
          <div class="input-group form-group-no-border{{ $errors->has('name') ? ' has-danger' : '' }}">
            <input type="text" name="name" id="name" class="form-control" value="{{ $user->name }}" />
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
            <input type="email" name="email" id="email" class="form-control" value="{{ $user->email }}" />
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
            <select name="airline_id" id="airline_id" class="form-control select2">
              @foreach($airlines as $airline_id => $airline_label)
                <option value="{{ $airline_id }}" @if($user->airline_id === $airline_id) selected @endif>{{ $airline_label }}</option>
              @endforeach
            </select>
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
            <select name="home_airport_id" id="home_airport_id" class="form-control airport_search @if($hubs_only) hubs_only @endif">
              @foreach($airports as $airport_id => $airport_label)
                <option value="{{ $airport_id }}">{{ $airport_label }}</option>
              @endforeach
            </select>
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
            <select name="country" id="country" class="form-control select2">
              @foreach($countries as $country_id => $country_label)
                <option value="{{ $country_id }}" @if($user->country === $country_id) selected @endif>{{ $country_label }}</option>
              @endforeach
            </select>
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
            <select name="timezone" id="timezone" class="form-control select2">}
              @foreach($timezones as $group_name => $group_timezones)
                <optgroup label="{{ $group_name }}">
                  @foreach($group_timezones as $timezone_id => $timezone_label)
                    <option value="{{ $timezone_id }}" @if($timezone_id === $user->timezone) selected @endif>{{ $timezone_label }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
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
            <input type="password" name="password" id="password" class="form-control" />
          </div>
          @if ($errors->has('password'))
            <p class="text-danger">{{ $errors->first('password') }}</p>
          @endif

          <p>{{ __('passwords.confirm') }}:</p>
          <div class="input-group form-group-no-border{{ $errors->has('password_confirmation') ? ' has-danger' : '' }}">
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" />
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
            <input type="file" name="avatar" id="avatar" />
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
              <input type="text" name="field_{{ $field->slug }}" id="field_{{ $field->slug }}" class="form-control" value="{{ $field->value }}" />
            </div>
            <p class="text-danger">{{ $errors->first('field_'.$field->slug) }}</p>
          </td>
        </tr>
      @endforeach

      <tr>
        <td>{{ __('profile.opt-in') }}</td>
        <td>
          <div class="input-group form-group-no-border">
            <input type="hidden" name="opt_in" value="0" />
            <input type="checkbox" name="opt_in" id="opt_in" checked="{{ $user->opt_in }}" />
          </div>
          <p class="small">{{ __('profile.opt-in-descrip') }}
          </p>
        </td>
      </tr>

    </table>

    <div style="width: 100%; text-align: right; padding-top: 20px;">
      <button type="submit" class="btn btn-primary">
        @lang('profile.updateprofile')
      </button>
    </div>
  </div>
</div>
