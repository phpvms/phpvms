@extends('auth.login_layout')
@section('title', __('common.login'))

@section('content')
  <div class="col-md-4 ml-auto mr-auto content-center">
    <div class="card card-login card-plain">
      <form method="post" action="{{ url('/login') }}" class="form">
        @csrf
      <div class="header header-primary text-center">
        <div class="logo-container" style="width: 320px;">
          <img src="{{ public_asset('/assets/frontend/img/logo.svg') }}" width="320" height="320"
               style="background: #FFF">
        </div>
      </div>
      <div class="card-body">
        <div class="input-group form-group-no-border{{ $errors->has('email') ? ' has-error' : '' }} input-lg">
          <div class="input-group-prepend">
            <span class="input-group-text">
              <i class="now-ui-icons users_circle-08"></i>
            </span>
          </div>
          <input
            type="text"
            name="email"
            id="email"
            class="form-control"
            value="{{ old('email') }}"
            placeholder="@lang('common.email') @lang('common.or') @lang('common.pilot_id')"
            required
          />
        </div>
        @if ($errors->has('email'))
          <span class="help-block">
              <strong>{{ $errors->first('email') }}</strong>
          </span>
        @endif

        <div class="input-group form-group-no-border{{ $errors->has('password') ? ' has-error' : '' }} input-lg">
          <div class="input-group-prepend">
            <span class="input-group-text">
              <i class="now-ui-icons text_caps-small"></i>
            </span>
          </div>
          <input
            type="password"
            name="password"
            id="password"
            class="form-control"
            placeholder="@lang('auth.password')"
            required
          />
        </div>
        @if ($errors->has('password'))
          <span class="help-block">
              <strong>{{ $errors->first('password') }}</strong>
          </span>
        @endif

      </div>
      <div class="footer text-center">
        @if(config('services.discord.enabled'))
          <a href="{{ route('oauth.redirect', ['provider' => 'discord']) }}" class="btn btn-round btn-lg btn-block" style="background-color:#738ADB;">
            @lang('auth.loginwith', ['provider' => 'Discord'])
          </a>
        @endif

        @if(config('services.ivao.enabled'))
          <a href="{{ route('oauth.redirect', ['provider' => 'ivao']) }}" class="btn btn-round btn-lg btn-block" style="background-color:#0d2c99;">
            @lang('auth.loginwith', ['provider' => 'IVAO'])
          </a>
        @endif

        @if(config('services.vatsim.enabled'))
          <a href="{{ route('oauth.redirect', ['provider' => 'vatsim']) }}" class="btn btn-round btn-lg btn-block" style="background-color:#29B473;">
            @lang('auth.loginwith', ['provider' => 'VATSIM'])
          </a>
        @endif
        <button class="btn btn-primary btn-round btn-lg btn-block">@lang('common.login')</button>
      </div>
      <div class="pull-left">
        <h6>
          <a href="{{ url('/register') }}" class="link">@lang('auth.createaccount')</a>
        </h6>
      </div>
      <div class="pull-right">
        <h6>
          <a href="{{ url('/password/reset') }}" class="link">@lang('auth.forgotpassword')?</a>
        </h6>
      </div>
      </form>
    </div>
  </div>
@endsection
