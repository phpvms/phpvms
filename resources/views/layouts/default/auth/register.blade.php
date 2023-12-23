@extends('app')
@section('title', __('auth.register'))

@section('content')
  <div class="row">
    <div class="col-sm-3"></div>
    <div class="col-sm-6">

      {{ Form::open(['url' => '/register', 'class' => 'form-signin']) }}

      <div class="panel periodic-login">
        <div class="panel-body">
          <h2>@lang('common.register')</h2>
          <label for="name" class="control-label">@lang('auth.fullname')</label>
          <div class="input-group form-group-no-border {{ $errors->has('name') ? 'has-danger' : '' }}">
            {{ Form::text('name', null, ['class' => 'form-control']) }}
          </div>
          @if ($errors->has('name'))
            <p class="text-danger">{{ $errors->first('name') }}</p>
          @endif

          <label for="email" class="control-label">@lang('auth.emailaddress')</label>
          <div class="input-group form-group-no-border {{ $errors->has('email') ? 'has-danger' : '' }}">
            {{ Form::text('email', null, ['class' => 'form-control']) }}
          </div>
          @if ($errors->has('email'))
            <p class="text-danger">{{ $errors->first('email') }}</p>
          @endif

          <label for="airline" class="control-label">@lang('common.airline')</label>
          <div class="input-group form-group-no-border {{ $errors->has('airline') ? 'has-danger' : '' }}">
            {{ Form::select('airline_id', $airlines, null , ['class' => 'form-control select2']) }}
          </div>
          @if ($errors->has('airline_id'))
            <p class="text-danger">{{ $errors->first('airline_id') }}</p>
          @endif

          <label for="home_airport" class="control-label">@lang('airports.home')</label>
          <div class="input-group form-group-no-border {{ $errors->has('home_airport') ? 'has-danger' : '' }}">
            {{ Form::select('home_airport_id', $airports, null , ['class' => 'form-control airport_search '.($hubs_only?'hubs_only':'')]) }}
          </div>
          @if ($errors->has('home_airport_id'))
            <p class="text-danger">{{ $errors->first('home_airport_id') }}</p>
          @endif

          <label for="country" class="control-label">@lang('common.country')</label>
          <div class="input-group form-group-no-border {{ $errors->has('country') ? 'has-danger' : '' }}">
            {{ Form::select('country', $countries, null, ['class' => 'form-control select2' ]) }}
          </div>
          @if ($errors->has('country'))
            <p class="text-danger">{{ $errors->first('country') }}</p>
          @endif

          <label for="timezone" class="control-label">@lang('common.timezone')</label>
          <div class="input-group form-group-no-border {{ $errors->has('timezone') ? 'has-danger' : '' }}">
            {{ Form::select('timezone', $timezones, null, ['id'=>'timezone', 'class' => 'form-control select2' ]) }}
          </div>
          @if ($errors->has('timezone'))
            <p class="text-danger">{{ $errors->first('timezone') }}</p>
          @endif

          @if (setting('pilots.allow_transfer_hours') === true)
            <label for="transfer_time" class="control-label">@lang('auth.transferhours')</label>
            <div class="input-group form-group-no-border {{ $errors->has('transfer_time') ? 'has-danger' : '' }}">
              {{ Form::number('transfer_time', 0, ['class' => 'form-control']) }}
            </div>
            @if ($errors->has('transfer_time'))
              <p class="text-danger">{{ $errors->first('transfer_time') }}</p>
            @endif
          @endif

          <label for="password" class="control-label">@lang('auth.password')</label>
          <div class="input-group form-group-no-border {{ $errors->has('password') ? 'has-danger' : '' }}">
            {{ Form::password('password', ['class' => 'form-control']) }}
          </div>
          @if ($errors->has('password'))
            <p class="text-danger">{{ $errors->first('password') }}</p>
          @endif

          <label for="password_confirmation" class="control-label">@lang('passwords.confirm')</label>
          <div class="input-group form-group-no-border {{ $errors->has('password_confirmation') ? 'has-danger' : '' }}">
            {{ Form::password('password_confirmation', ['class' => 'form-control']) }}
          </div>
          @if ($errors->has('password_confirmation'))
            <p class="text-danger">{{ $errors->first('password_confirmation') }}</p>
          @endif

          @if($userFields)
            @foreach($userFields as $field)
              <label for="field_{{ $field->slug }}" class="control-label">{{ $field->name }}</label>
              <div class="input-group form-group-no-border {{ $errors->has('field_'.$field->slug) ? 'has-danger' : '' }}">
                {{ Form::text('field_'.$field->slug, null, ['class' => 'form-control']) }}
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

          <div>
            @include('auth.toc')
            <br/>
          </div>

          <table>
            <tr>
              <td style="vertical-align: top; padding: 5px 10px 0 0">
                <div class="input-group form-group-no-border">
                  {{ Form::hidden('toc_accepted', 0, false) }}
                  {{ Form::checkbox('toc_accepted', 1, null, ['id' => 'toc_accepted']) }}
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
                  {{ Form::hidden('opt_in', 0, false) }}
                  {{ Form::checkbox('opt_in', 1, null) }}
                </div>
              </td>
              <td>
                <label for="opt_in" class="control-label">@lang('profile.opt-in-descrip')</label>
              </td>
            </tr>
          </table>

          <div style="width: 100%; text-align: right; padding-top: 20px;">
            @if(config('services.discord.enabled'))
              <a href="{{ route('oauth.redirect', ['provider' => 'discord']) }}" class="btn" style="background-color:#738ADB;">
                @lang('auth.loginwith', ['provider' => 'Discord'])
              </a>
            @endif

            {{ Form::submit(__('auth.register'), [
                'id' => 'register_button',
                'class' => 'btn btn-primary',
                'disabled' => true,
               ]) }}
          </div>

        </div>
      </div>
      {{ Form::close() }}
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
