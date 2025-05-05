@extends('app')
@section('title', __('auth.register'))

@section('content')
  <div class="row">
    <div class="col-sm-3"></div>
    <div class="col-sm-6">

      <form method="post" action="{{ url('/register') }}" class="form-signin">
      @csrf
      <div class="panel periodic-login">
        <div class="panel-body">
          <h2>@lang('common.register')</h2>
          <label for="name" class="control-label">@lang('auth.fullname')</label>
          <div class="input-group form-group-no-border {{ $errors->has('name') ? 'has-danger' : '' }}">
            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" />
          </div>
          @if ($errors->has('name'))
            <p class="text-danger">{{ $errors->first('name') }}</p>
          @endif

          <label for="email" class="control-label">@lang('auth.emailaddress')</label>
          <div class="input-group form-group-no-border {{ $errors->has('email') ? 'has-danger' : '' }}">
            <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" />
          </div>
          @if ($errors->has('email'))
            <p class="text-danger">{{ $errors->first('email') }}</p>
          @endif

          <label for="airline" class="control-label">@lang('common.airline')</label>
          <div class="input-group form-group-no-border {{ $errors->has('airline') ? 'has-danger' : '' }}">
            <select name="airline_id" id="airline_id" class="form-control select2">
              @foreach($airlines as $airline_id => $airline_label)
                <option value="{{ $airline_id }}" @if($airline_id === old('airline_id')) selected @endif>{{ $airline_label }}</option>
              @endforeach
            </select>
          </div>
          @if ($errors->has('airline_id'))
            <p class="text-danger">{{ $errors->first('airline_id') }}</p>
          @endif

          <label for="home_airport" class="control-label">@lang('airports.home')</label>
          <div class="input-group form-group-no-border {{ $errors->has('home_airport') ? 'has-danger' : '' }}">
            <select name="home_airport_id" id="home_airport_id" class="form-control airport_search @if($hubs_only) hubs_only @endif">
              @foreach($airports as $airport_id => $airport_label)
                <option value="{{ $airport_id }}">{{ $airport_label }}</option>
              @endforeach
            </select>
          </div>
          @if ($errors->has('home_airport_id'))
            <p class="text-danger">{{ $errors->first('home_airport_id') }}</p>
          @endif

          <label for="country" class="control-label">@lang('common.country')</label>
          <div class="input-group form-group-no-border {{ $errors->has('country') ? 'has-danger' : '' }}">
            <select name="country" id="country" class="form-control select2">
              @foreach($countries as $country_id => $country_label)
                <option value="{{ $country_id }}" @if($country_id === old('country')) selected @endif>{{ $country_label }}</option>
              @endforeach
            </select>
          </div>
          @if ($errors->has('country'))
            <p class="text-danger">{{ $errors->first('country') }}</p>
          @endif

          <label for="timezone" class="control-label">@lang('common.timezone')</label>
          <div class="input-group form-group-no-border {{ $errors->has('timezone') ? 'has-danger' : '' }}">
            <select name="timezone" id="timezone" class="form-control select2">
              @foreach($timezones as $group_name => $group_timezones)
                <optgroup label="{{ $group_name }}">
                  @foreach($group_timezones as $timezone_id => $timezone_label)
                    <option value="{{ $timezone_id }}" @if($timezone_id === old('timezone')) selected @endif>{{ $timezone_label }}</option>
                  @endforeach
                </optgroup>
              @endforeach
            </select>
          </div>
          @if ($errors->has('timezone'))
            <p class="text-danger">{{ $errors->first('timezone') }}</p>
          @endif

          @if (setting('pilots.allow_transfer_hours') === true)
            <label for="transfer_time" class="control-label">@lang('auth.transferhours')</label>
            <div class="input-group form-group-no-border {{ $errors->has('transfer_time') ? 'has-danger' : '' }}">
              <input type="number" name="transfer_time" id="transfer_time" class="form-control" value="{{ old('transfer_time') }}" />
            </div>
            @if ($errors->has('transfer_time'))
              <p class="text-danger">{{ $errors->first('transfer_time') }}</p>
            @endif
          @endif

          <label for="password" class="control-label">@lang('auth.password')</label>
          <div class="input-group form-group-no-border {{ $errors->has('password') ? 'has-danger' : '' }}">
            <input type="password" name="password" id="password" class="form-control" />
          </div>
          @if ($errors->has('password'))
            <p class="text-danger">{{ $errors->first('password') }}</p>
          @endif

          <label for="password_confirmation" class="control-label">@lang('passwords.confirm')</label>
          <div class="input-group form-group-no-border {{ $errors->has('password_confirmation') ? 'has-danger' : '' }}">
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" />
          </div>
          @if ($errors->has('password_confirmation'))
            <p class="text-danger">{{ $errors->first('password_confirmation') }}</p>
          @endif

          @if($userFields)
            @foreach($userFields as $field)
              <label for="field_{{ $field->slug }}" class="control-label">{{ $field->name }}</label>
              <div class="input-group form-group-no-border {{ $errors->has('field_'.$field->slug) ? 'has-danger' : '' }}">
                <input type="text" name="field_{{ $field->slug }}" id="field_{{ $field->slug }}" class="form-control" value="{{ old('field_' .$field->slug) }}" />
              </div>
              @if ($errors->has('field_'.$field->slug))
                <p class="text-danger">{{ $errors->first('field_'.$field->slug) }}</p>
              @endif
            @endforeach
          @endif

          @if($captcha['enabled'] === true)
            <label for="h-captcha" class="control-label">@lang('auth.fillcaptcha')</label>
            <div class="h-captcha" data-sitekey="{{ $captcha['site_key'] }}"></div>
            @if ($errors->has('h-captcha-response'))
              <p class="text-danger">{{ $errors->first('h-captcha-response') }}</p>
            @endif
          @endif

          @if($invite)
            <input type="hidden" name="invite" value="{{ $invite->id }}" />
            <input type="hidden" name="invite_token" value="{{ base64_encode($invite->token) }}" />
          @endif

          <div>
            @include('auth.toc')
            <br/>
          </div>

          <table>
            <tr>
              <td style="vertical-align: top; padding: 5px 10px 0 0">
                <div class="input-group form-group-no-border">
                  <input type="checkbox" name="toc_accepted" id="toc_accepted" />
                </div>
              </td>
              <td style="vertical-align: top;">
                <label for="toc_accepted" class="control-label">@lang('auth.tocaccept')</label>
                @if ($errors->has('toc_accepted'))
                  <p class="text-danger">{{ $errors->first('toc_accepted') }}</p>
                @endif
              </td>
            </tr>
            <tr>
              <td>
                <div class="input-group form-group-no-border">
                  <input type="hidden" name="opt_in" value="0"/>
                  <input type="checkbox" name="opt_in" id="opt_in" value="1"/>
                </div>
              </td>
              <td>
                <label for="opt_in" class="control-label">@lang('profile.opt-in-descrip')</label>
              </td>
            </tr>
          </table>

          <div style="width: 100%; text-align: right; padding-top: 20px;">
              <button type="submit" class="btn btn-primary" id="register_button" disabled>
                @lang('auth.register')
              </button>
          </div>

        </div>
      </div>
      </form>
    </div>
    <div class="col-sm-4"></div>
  </div>
@endsection

@section('scripts')
  @if ($captcha['enabled'])
    <script src="https://hcaptcha.com/1/api.js" async defer></script>
  @endif

  <script>
    window.addEventListener("load", function () {
    
    $('#toc_accepted').click(function () {
        if ($(this).is(':checked')) {
          $('#register_button').removeAttr('disabled');
        } else {
          $('#register_button').attr('disabled', 'true');
        }
      });
    });
  </script>
@include('scripts.airport_search')
@endsection
