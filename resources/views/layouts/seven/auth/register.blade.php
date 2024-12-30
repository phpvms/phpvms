@extends('app')
@section('title', __('auth.register'))

@section('content')
  <div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
      <form method="post" action="{{ url('/register') }}" class="form-signin">
        @csrf
        <div class="card">
          <div class="card-header">
            <h2>@lang('common.register')</h2>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="name" class="form-label">@lang('auth.fullname')</label>
              <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" />
              @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="email" class="form-label">@lang('auth.emailaddress')</label>
              <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" />
              @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="airline_id" class="form-label">@lang('common.airline')</label>
              <select name="airline_id" id="airline_id" class="form-select select2 @error('airline_id') is-invalid @enderror">
                @foreach($airlines as $airline_id => $airline_label)
                  <option value="{{ $airline_id }}" @if($airline_id === old('airline_id')) selected @endif>{{ $airline_label }}</option>
                @endforeach
              </select>
              @error('airline_id')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="home_airport_id" class="form-label">@lang('airports.home')</label>
              <select name="home_airport_id" id="home_airport_id" class="form-select airport_search @if($hubs_only) hubs_only @endif @error('home_airport_id') is-invalid @enderror">
                @foreach($airports as $airport_id => $airport_label)
                  <option value="{{ $airport_id }}">{{ $airport_label }}</option>
                @endforeach
              </select>
              @error('home_airport_id')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="country" class="form-label">@lang('common.country')</label>
              <select name="country" id="country" class="form-select select2 @error('country') is-invalid @enderror">
                @foreach($countries as $country_id => $country_label)
                  <option value="{{ $country_id }}" @if($country_id === old('country')) selected @endif>{{ $country_label }}</option>
                @endforeach
              </select>
              @error('country')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="timezone" class="form-label">@lang('common.timezone')</label>
              <select name="timezone" id="timezone" class="form-select select2 @error('timezone') is-invalid @enderror">
                @foreach($timezones as $group_name => $group_timezones)
                  <optgroup label="{{ $group_name }}">
                    @foreach($group_timezones as $timezone_id => $timezone_label)
                      <option value="{{ $timezone_id }}" @if($timezone_id === old('timezone')) selected @endif>{{ $timezone_label }}</option>
                    @endforeach
                  </optgroup>
                @endforeach
              </select>
              @error('timezone')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            @if (setting('pilots.allow_transfer_hours') === true)
              <div class="mb-3">
                <label for="transfer_time" class="form-label">@lang('auth.transferhours')</label>
                <input type="number" name="transfer_time" id="transfer_time" class="form-control @error('transfer_time') is-invalid @enderror" value="{{ old('transfer_time') }}" />
                @error('transfer_time')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            @endif

            <div class="mb-3">
              <label for="password" class="form-label">@lang('auth.password')</label>
              <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" />
              @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="password_confirmation" class="form-label">@lang('passwords.confirm')</label>
              <input type="password" name="password_confirmation" id="password_confirmation" class="form-control @error('password_confirmation') is-invalid @enderror" />
              @error('password_confirmation')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            @if($userFields)
              @foreach($userFields as $field)
                <div class="mb-3">
                  <label for="field_{{ $field->slug }}" class="form-label">{{ $field->name }}</label>
                  <input type="text" name="field_{{ $field->slug }}" id="field_{{ $field->slug }}" class="form-control @error('field_'.$field->slug) is-invalid @enderror" value="{{ old('field_' .$field->slug) }}" />
                  @error('field_'.$field->slug)
                    <div class="invalid-feedback">{{ $message }}</div>
                  @enderror
                </div>
              @endforeach
            @endif

            @if($captcha['enabled'] === true)
              <div class="mb-3">
                <label for="h-captcha" class="form-label">@lang('auth.fillcaptcha')</label>
                <div class="h-captcha" data-bs-sitekey="{{ $captcha['site_key'] }}"></div>
                @error('h-captcha-response')
                  <div class="invalid-feedback">{{ $message }}</div>
                @enderror
              </div>
            @endif

            @if($invite)
              <input type="hidden" name="invite" value="{{ $invite->id }}" />
              <input type="hidden" name="invite_token" value="{{ base64_encode($invite->token) }}" />
            @endif

            <div class="mb-3">
              @include('auth.toc')
              <br/>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input @error('toc_accepted') is-invalid @enderror" type="checkbox" name="toc_accepted" id="toc_accepted">
              <label class="form-check-label" for="toc_accepted">
                @lang('auth.tocaccept')
              </label>
              @error('toc_accepted')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="hidden" name="opt_in" value="0"/>
              <input class="form-check-input" type="checkbox" name="opt_in" id="opt_in" value="1"/>
              <label class="form-check-label" for="opt_in">
                @lang('profile.opt-in-descrip')
              </label>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary" id="register_button" disabled>
                @lang('auth.register')
              </button>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
@endsection

@section('scripts')
  @if ($captcha['enabled'])
    <script src="https://hcaptcha.com/1/api.js" async defer></script>
  @endif

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      new TomSelect('#airline_id');
      new TomSelect('#country');
      new TomSelect('#timezone');
    });
    document.getElementById('toc_accepted').addEventListener('click', function () {
      var registerButton = document.getElementById('register_button');
      if (this.checked) {
      registerButton.removeAttribute('disabled');
      } else {
      registerButton.setAttribute('disabled', 'true');
      }
    });
  </script>
@include('scripts.airport_search')
@endsection
