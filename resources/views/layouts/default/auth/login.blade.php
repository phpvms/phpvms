@extends('auth.login_layout')
@section('title', __('common.login'))

@section('content')
  <div class="col-md-4 ml-auto mr-auto content-center">
    <div class="card card-login card-plain">
      {{ Form::open(['url' => url('/login'), 'method' => 'post', 'class' => 'form']) }}
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
              <i class="fa fa-user"></i>
            </span>
          </div>
          {{
            Form::text('email', old('email'), [
              'id' => 'email',
              'placeholder' => __('common.email').' '.__('common.or').' '.__('common.pilot_id'),
              'class' => 'form-control',
              'required' => true,
            ])
          }}
        </div>
        @if ($errors->has('email'))
          <span class="help-block">
              <strong>{{ $errors->first('email') }}</strong>
          </span>
        @endif

        <div class="input-group form-group-no-border{{ $errors->has('password') ? ' has-error' : '' }} input-lg">
          <div class="input-group-prepend">
            <span class="input-group-text">
              <i class="fa fa-lock"></i>
            </span>
          </div>
          {{
              Form::password('password', [
                  'name' => 'password',
                  'class' => 'form-control',
                  'placeholder' => __('auth.password'),
                  'required' => true,
              ])
          }}
        </div>
        @if ($errors->has('password'))
          <span class="help-block">
              <strong>{{ $errors->first('password') }}</strong>
          </span>
        @endif

      </div>
      <div class="footer text-center">
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
      {{ Form::close() }}
    </div>
  </div>
@endsection
